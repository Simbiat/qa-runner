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
 * Adapter for PHP CS Fixer https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
 */
final readonly class CSFixer implements ToolAdapter, DiffableToolAdapter, FixableToolAdapter
{
    /**
     * @param string $project_root Path to project root
     */
    public function __construct(private string $project_root) {}

    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string
    {
        return 'PHP CS Fixer';
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
        if (\stripos($base_command, 'php-cs-fixer check') === false && (\stripos($base_command, 'php-cs-fixer fix') === false || \stripos($base_command, '--dry-run') === false)) {
            throw new \UnexpectedValueException('Not a CS Fixer dry-run command');
        }
        // Enforce Gitlab format, since it is JSON and provide description of the rules
        $base_command = \preg_replace('/--format=\S+/uir', '', $base_command).' --format=gitlab';
        // Force --diff, since this also adds lines values.
        // They seem to be wrong in most cases, and there were multiple issues and PRs related to this with no proper resolution, but maybe at some point it will be properly fixed.
        if (\stripos($base_command, '--diff') === false) {
            $base_command .= ' --diff';
        }
        // Force --verbose flag to get rules' names
        if (\stripos($base_command, '--verbose') === false) {
            $base_command .= ' --verbose';
        }
        // Force dry-run
        if (\stripos($base_command, '--dry-run') === false) {
            $base_command .= ' --dry-run';
        }
        // Remove --stop-on-violation ifp resent, since the goal is to get the whole list
        $base_command = \str_ireplace('--stop-on-violation', '', $base_command);

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
        foreach ($data as $finding) {
            $path = $finding['location']['path'] ?? '';
            if ($path !== '' && !\str_starts_with($path, '/')) {
                $path = $this->project_root.'/'.$path;
            }

            $findings[] = new Finding(
                tool: $this->name(),
                // Strip the prefix
                rule: \str_ireplace('PHP-CS-Fixer.', '', $finding['check_name']),
                file: $path,
                line: $finding['location']['lines']['begin'] ?? 0,
                message: $finding['description'],
                fixable: true,
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
        if (\stripos($base_command, 'php-cs-fixer check') === false && (\stripos($base_command, 'php-cs-fixer fix') === false || \stripos($base_command, '--dry-run') === false)) {
            throw new \UnexpectedValueException('Not a CS Fixer dry-run command');
        }
        // Enforce text format
        $base_command = \preg_replace('/--format=\S+/uir', '', $base_command).' --format=txt';
        // Force dry-run
        if (\stripos($base_command, '--dry-run') === false) {
            $base_command .= ' --dry-run';
        }
        // Force --diff mode
        if (\stripos($base_command, '--diff') === false) {
            $base_command .= ' --diff';
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
        if (\stripos($base_command, 'php-cs-fixer fix') === false) {
            throw new \UnexpectedValueException('Not a CS Fixer fix command');
        }
        // Remove --dry-run flag
        $base_command = \str_ireplace('--dry-run', '', $base_command);

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
