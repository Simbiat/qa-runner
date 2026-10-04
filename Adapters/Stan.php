<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Adapter for PHPStan https://github.com/phpstan/phpstan
 */
final readonly class Stan implements ToolAdapter
{
    /**
     * @param string $project_root Path to project root
     * @param bool   $full_scan    Whether to scan the full project or just the provided path
     */
    public function __construct(private string $project_root, private bool $full_scan) {}

    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string
    {
        return 'PHPStan';
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
        if (\stripos($base_command, 'phpstan analyse') === false) {
            throw new \UnexpectedValueException('Not a PHPStan\'s analyse command');
        }
        // Enforce JSON format
        $base_command = \preg_replace('/--error-format=\S+/uir', '', $base_command).' --error-format=json';

        if ($this->full_scan) {
            return Process::fromShellCommandline($base_command, $this->project_root);
        }

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
        $wanted = \array_flip($paths);
        $findings = [];
        foreach ($data['files'] ?? [] as $file => $file_data) {
            if (!isset($wanted[$file])) {
                continue;
            }
            foreach ($file_data['messages'] ?? [] as $message) {
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: $message['identifier'] ?? 'unknown',
                    file: $file,
                    line: (int) ($message['line'] ?? 0),
                    message: $message['message'] ?? '',
                );
            }
        }

        return $findings;
    }
}
