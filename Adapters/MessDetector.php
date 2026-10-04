<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Adapter for PHP Mess Detector https://github.com/phpmd/phpmd
 */
final readonly class MessDetector implements ToolAdapter
{
    /**
     * @param string $project_root Path to project root
     */
    public function __construct(private string $project_root) {}

    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string
    {
        return 'PHP Mess Detector';
    }

    /**
     * Method to prepare Check command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for regular check by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildCheckProcess(string $paths, string $base_command): Process
    {
        if (\stripos($base_command, 'phpmd analyze') === false) {
            throw new \UnexpectedValueException('Not a PHPMD command');
        }
        // Enforce JSON format
        $base_command = \preg_replace('/--format=\S+/uir', '', $base_command).' --format=json';

        return Process::fromShellCommandline($base_command.$paths, $this->project_root);
    }


    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return array<\Simbiat\QARunner\Entities\Finding>
     *
     * @throws \JsonException
     */
    public function parseCheck(Process $process, array $paths): array
    {
        $data = \json_decode($process->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            return [];
        }

        $findings = [];
        foreach ($data['files'] ?? [] as $file_data) {
            foreach ($file_data['violations'] ?? [] as $message) {
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: $message['rule'] ?? 'unknown',
                    file: $file_data['file'],
                    line: (int) ($message['beginLine'] ?? 0),
                    message: $message['description'] ?? '',
                    fixable: (bool) ($message['fixable'] ?? false),
                );
            }
        }

        return $findings;
    }
}
