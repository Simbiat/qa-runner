<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Adapter for PHP CodeSniffer https://github.com/PHPCSStandards/PHP_CodeSniffer
 */
final readonly class CodeSniffer implements ToolAdapter, DiffableToolAdapter, FixableToolAdapter
{
    /**
     * @param string $project_root Path to project root
     */
    public function __construct(private string $project_root) {}

    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string
    {
        return 'PHP CodeSniffer';
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
        if (\stripos($base_command, 'phpcs') === false) {
            throw new \UnexpectedValueException('Not a PHPCS command');
        }
        // Enforce JSON format
        $base_command = \preg_replace('/--report=\S+/uir', '', $base_command).' --report=json';

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
        // Code Sniffer can return empty string, that will fail to be decoded
        $output = $process->getOutput();
        if ($output === '') {
            return [];
        }
        $data = \json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            return [];
        }

        $findings = [];
        foreach ($data['files'] ?? [] as $file => $file_data) {
            foreach ($file_data['messages'] ?? [] as $message) {
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: $message['source'] ?? 'unknown',
                    file: $file,
                    line: (int) ($message['line'] ?? 0),
                    column: (int) ($message['column'] ?? 0),
                    message: $message['message'] ?? '',
                    type: $message['type'] ?? 'WARNING',
                    fixable: (bool) ($message['fixable'] ?? false),
                );
            }
        }

        return $findings;
    }

    /**
     * Method to prepare Diff command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for Diff by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildDiffProcess(string $paths, string $base_command): Process
    {
        if (\stripos($base_command, 'phpcs') === false) {
            throw new \UnexpectedValueException('Not a PHPCS command');
        }
        // Enforce Diff format
        $base_command = \preg_replace('/--report=\S+/uir', '', $base_command).' --report=diff';

        return Process::fromShellCommandline($base_command.$paths, $this->project_root);
    }

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return string
     */
    public function parseDiff(Process $process, array $paths): string
    {
        return \trim($process->getOutput());
    }

    /**
     * Method to prepare fix command. Adapter appends whatever flags *it* needs for the fix, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for Diff by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildFixProcess(string $paths, string $base_command): Process
    {
        if (\stripos($base_command, 'phpcbf') === false) {
            throw new \UnexpectedValueException('Not a PHPCBF command');
        }

        return Process::fromShellCommandline($base_command.$paths, $this->project_root);
    }

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return \Simbiat\QARunner\Entities\FixResult
     */
    public function parseFix(Process $process, array $paths): FixResult
    {
        $code = $process->getExitCode();

        return new FixResult(
            tool: $this->name(),
            success: $code === 0,
            output: \trim($process->getOutput()."\n".$process->getErrorOutput()),
        );
    }
}
