<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Helpers\PathEscaper;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Simbiat\QARunner\Interfaces\ToolAdapter;

/**
 * Runner for Diff command
 */
final readonly class Diff
{
    /**
     * @param array<string, ToolAdapter> $adapters List of adapters to use
     * @param array<string, string>      $commands Commands to run
     */
    public function __construct(
        private array $adapters,
        private array $commands,
    ) {}

    /**
     * Get dry-run diff report from tools that support them.
     *
     * @param array<string> $paths Paths to process
     *
     * @return array<string, string> Results of all the commands, that were ran
     */
    public function run(array $paths): array
    {
        $running = [];
        foreach ($this->adapters as $class => $adapter) {
            if (!$adapter instanceof DiffableToolAdapter) {
                continue;
            }

            $process = $adapter->buildDiffProcess(PathEscaper::escapedPaths($paths), $this->commands[$class]);
            $process->setTimeout(null);
            $process->start();
            $running[] = [$adapter, $process];
        }

        $diffs = [];
        foreach ($running as [$adapter, $process]) {
            $process->wait();
            $diff = $adapter->parseDiff($process, $paths);
            if ($diff !== '') {
                $diffs[$adapter->name()] = $diff;
            }
        }

        return $diffs;
    }
}
