<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Exceptions\ToolFailure;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Code, that all adapters share: constructor, process creation, exit code checks and the default parsing of `diff` and `fix` results.
 */
abstract readonly class AbstractAdapter implements ToolAdapter
{
    /**
     * Exit codes, that mean the tool ran properly in `check` and `diff` modes, with or without findings.
     * Any other code is a failure of the tool (crash, bad configuration, missing binary).
     */
    protected const array OK_EXIT_CODES = [0];

    /**
     * Exit codes, that mean the tool fixed everything it could in `fix` mode.
     */
    protected const array FIX_OK_EXIT_CODES = [0];

    /**
     * Maximum length of the tool output, that goes to a failure message.
     */
    private const int MESSAGE_LIMIT = 500;

    /**
     * @param string      $project_root Path to project root
     * @param string      $command      Command from composer.json for the current mode
     * @param bool        $full_scan    Whether to scan the full project or just the provided paths (used only by tools, that benefit from full scans)
     * @param null|string $bin_dir      Directory with binaries of Composer packages, to be added to PATH
     */
    public function __construct(
        protected string $project_root,
        protected string $command,
        protected bool $full_scan = true,
        protected ?string $bin_dir = null,
    ) {}

    /**
     * Parse the tool output.
     *
     * @param Process $process Symfony's Process instance, that has ended
     * @param Paths   $paths   Paths to be scanned
     *
     * @return array<Finding>
     *
     * @throws \JsonException|\RuntimeException|ToolFailure
     */
    final public function parseCheck(Process $process, Paths $paths): array
    {
        $this->assertExitCode($process);

        return $this->extractFindings($process->getOutput(), $paths);
    }

    /**
     * Parse the tool output.
     *
     * @param Process $process Symfony's Process instance, that has ended
     * @param Paths   $paths   Paths to be scanned
     *
     * @return string
     *
     * @throws ToolFailure
     *
     * @noinspection PhpUnusedParameterInspection $paths can be used by overriden versions of the function.
     */
    final public function parseDiff(Process $process, Paths $paths): string
    {
        $this->assertExitCode($process);

        return \trim($process->getOutput());
    }

    /**
     * Parse the tool output.
     *
     * @param Process $process Symfony's Process instance, that has ended
     * @param Paths   $paths   Paths to be processed
     *
     * @return FixResult
     *
     * @noinspection PhpUnusedParameterInspection $paths can be used by overriden versions of the function.
     */
    final public function parseFix(Process $process, Paths $paths): FixResult
    {
        /* @noinspection InArrayMissUseInspection Direct comparison is not suitable, since the array with exit codes can be overriden */
        return new FixResult(
            tool: $this->name(),
            success: \in_array($process->getExitCode(), static::FIX_OK_EXIT_CODES, strict: true),
            output: \trim($process->getOutput()."\n".$process->getErrorOutput()),
        );
    }

    /**
     * Convert the tool output to findings.
     *
     * @param string $output Standard output of the tool
     * @param Paths  $paths  Paths to be scanned
     *
     * @return array<Finding>
     *
     * @throws \JsonException|\RuntimeException If the output is not in the expected format
     */
    abstract protected function extractFindings(string $output, Paths $paths): array;

    /**
     * Create the process. The tools run in the project root, with the binaries directory of Composer in PATH.
     *
     * @param string                               $command Command line
     * @param null|\Simbiat\QARunner\Helpers\Paths $paths   Paths to append to the command. Null if the command must run without paths.
     *
     * @return Process
     */
    protected function shell(string $command, ?Paths $paths): Process
    {
        $env = [];
        if ($this->bin_dir !== null) {
            $env['PATH'] = $this->bin_dir.\PATH_SEPARATOR.(\getenv('PATH') ?: '');
        }

        return Process::fromShellCommandline($command.($paths?->escaped() ?? ''), $this->project_root, $env);
    }

    /**
     * Throw if the process did not end with one of the expected exit codes.
     *
     * @param Process $process Symfony's Process instance, that has ended
     *
     * @throws ToolFailure
     */
    protected function assertExitCode(Process $process): void
    {
        $code = $process->getExitCode();
        /* @noinspection InArrayMissUseInspection Direct comparison is not suitable, since the array with exit codes can be overriden */
        if ($code !== null && \in_array($code, static::OK_EXIT_CODES, strict: true)) {
            return;
        }

        $details = \trim($process->getErrorOutput());
        if ($details === '') {
            $details = \trim($process->getOutput());
        }

        $details = \mb_substr($details, 0, self::MESSAGE_LIMIT);

        throw new ToolFailure(
            ($code === null ? 'Process did not finish' : 'Exit code '.$code.($code === 127 ? ' (command not found?)' : ''))
            .($details === '' ? '' : ': '.$details)
        );
    }

    /**
     * Decode JSON output of a tool. Empty output means no findings.
     *
     * @param string $output Standard output of the tool
     *
     * @return array<array-key, mixed>
     *
     * @throws \JsonException
     */
    protected function decodeJson(string $output): array
    {
        if (\trim($output) === '') {
            return [];
        }

        $data = \json_decode($output, associative: true, depth: 512, flags: \JSON_THROW_ON_ERROR);

        return \is_array($data) ? $data : [];
    }

    /**
     * Make a path absolute, if the tool returned it relative to the project root.
     *
     * @param string $path Path from the tool
     *
     * @return string
     */
    protected function absolute(string $path): string
    {
        return $path !== '' && !\str_starts_with($path, '/') ? $this->project_root.'/'.$path : $path;
    }

    /**
     * Last part of a fully qualified class name. Returns the value as is, if there are no namespace separators.
     *
     * @param string $name Class name
     *
     * @return string
     */
    protected static function shortName(string $name): string
    {
        $position = \strrpos($name, '\\');

        return $position === false ? $name : \substr($name, $position + 1);
    }
}
