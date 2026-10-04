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
 * Adapter for Psalm https://github.com/vimeo/psalm/
 */
final readonly class Psalm implements ToolAdapter, DiffableToolAdapter, FixableToolAdapter
{
    /**
     * Fixable errors' types, taken from https://psalm.dev/docs/manipulating_code/fixing/
     */
    private const array FIXABLE_ERRORS = [
        'MissingReturnType',
        'MissingClosureReturnType',
        'InvalidReturnType',
        'InvalidNullableReturnType',
        'InvalidFalsableReturnType',
        'MissingParamType',
        'MissingPropertyType',
        'MismatchingDocblockParamType',
        'MismatchingDocblockReturnType',
        'LessSpecificReturnType',
        'PossiblyUndefinedVariable',
        'PossiblyUndefinedGlobalVariable',
        'UnusedMethod',
        'PossiblyUnusedMethod',
        'UnusedProperty',
        'PossiblyUnusedProperty',
        'UnusedVariable',
        'UnnecessaryVarAnnotation',
        'ParamNameMismatch',
    ];

    /**
     * @param string $project_root Path to project root
     * @param bool   $full_scan    Whether to scan the full project or just the provided path
     */
    public function __construct(private string $project_root, private bool $full_scan) {}

    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string
    {
        return 'Psalm';
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
        if (\stripos($base_command, 'psalm') === false) {
            throw new \UnexpectedValueException('Not a Psalm command');
        }
        // Enforce JSON format
        $base_command = \preg_replace('/--output-format=\S+/uir', '', $base_command).' --output-format=json';
        // Remove --taint-analysis flag, since it results in different kind of output, and --alter since it would modify the file
        $base_command = \str_ireplace(['--taint-analysis', '--alter'], '', $base_command);
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
        foreach ($data as $finding) {
            if (!isset($wanted[$finding['file_path']])) {
                continue;
            }
            $findings[] = new Finding(
                tool: $this->name(),
                rule: $finding['type'] ?? 'unknown',
                file: $finding['file_path'],
                line: (int) ($finding['line_from'] ?? 0),
                column: (int) ($finding['column_from'] ?? 0),
                message: $finding['message'] ?? '',
                type: $finding['severity'] ?? 'WARNING',
                fixable: \in_array($finding['type'] ?? 'unknown', self::FIXABLE_ERRORS, true),
            );
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
        if (\stripos($base_command, 'psalter') === false && ((\stripos($base_command, 'psalm') === false || \stripos($base_command, '--alter') === false))) {
            throw new \UnexpectedValueException('Not a Psalter command');
        }
        // Force dry-run
        if (\stripos($base_command, '--dry-run') === false) {
            $base_command .= ' --dry-run';
        }
        // Add issues list, if not provided
        if (\stripos($base_command, '--issues') === false) {
            $base_command .= ' --issues=all';
        }

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
        if (\stripos($base_command, 'psalter') === false && ((\stripos($base_command, 'psalm') === false || \stripos($base_command, '--alter') === false))) {
            throw new \UnexpectedValueException('Not a Psalter command');
        }
        // Remove --dry-run flag
        $base_command = \str_ireplace('--dry-run', '', $base_command);
        // Add issues list, if not provided
        if (\stripos($base_command, '--issues') === false) {
            $base_command .= ' --issues=all';
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
