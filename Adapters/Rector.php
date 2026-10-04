<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use ReflectionClass;
use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Adapter for Rector https://github.com/rectorphp/rector
 */
final readonly class Rector implements ToolAdapter, DiffableToolAdapter, FixableToolAdapter
{
    /**
     * @param string $project_root Path to project root
     */
    public function __construct(private string $project_root) {}

    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string
    {
        return 'Rector';
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
        if (\stripos($base_command, 'rector') === false) {
            throw new \UnexpectedValueException('Not a Rector command');
        }
        // Enforce JSON format
        $base_command = \preg_replace('/--output-format=\S+/uir', '', $base_command).' --output-format=json';
        // Force dry-run
        if (\stripos($base_command, '--dry-run') === false) {
            $base_command .= ' --dry-run';
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

        $findings = [];
        foreach ($data['file_diffs'] ?? [] as $file_data) {
            foreach ($file_data['changes'] ?? [] as $finding) {
                $path = $file_data['file'] ?? '';
                if ($path !== '' && !\str_starts_with($path, '/')) {
                    $path = $this->project_root.'/'.$path;
                }
                try {
                    $description = new ReflectionClass($finding['rector'])->newInstanceWithoutConstructor()->getRuleDefinition()->getDescription();
                } catch (\Throwable) {
                    $description = '';
                }
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: \substr($finding['rector'], \strrpos($finding['rector'], '\\') + 1),
                    file: $path,
                    line: (int) ($finding['line'] ?? 0),
                    message: $description,
                    // Rector has only fixable issues by design
                    fixable: true,
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
        if (\stripos($base_command, 'rector') === false) {
            throw new \UnexpectedValueException('Not a Rector command');
        }
        // Enforce console format with diffs
        $base_command = \preg_replace('/--output-format=\S+/uir', '', $base_command).' --output-format=console';
        $base_command = \str_ireplace('--no-diffs', '', $base_command);
        // Force dry-run
        if (\stripos($base_command, '--dry-run') === false) {
            $base_command .= ' --dry-run';
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
        if (\stripos($base_command, 'rector') === false) {
            throw new \UnexpectedValueException('Not a Rector command');
        }
        // Enforce console format with diffs and remove dry-run
        $base_command = \preg_replace('/--output-format=\S+/uir', '', $base_command).' --output-format=console';
        $base_command = \str_ireplace(['--no-diffs', '--dry-run'], '', $base_command);

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
