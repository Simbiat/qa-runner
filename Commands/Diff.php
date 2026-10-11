<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Entities\RunResult;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Runner for Diff command
 */
final readonly class Diff extends AbstractCommand
{
    /**
     * Get dry-run diff report from tools that support them. The tools run in parallel (safe, since they only read).
     *
     * @param Paths $paths Paths to process
     *
     * @return RunResult `items` is a map of tool name => diff (tools without a diff are not included), `failures` lists the tools, that did not run properly
     */
    public function run(Paths $paths): RunResult
    {
        $result = $this->runParallel(
            static fn(DiffableToolAdapter $adapter): Process => $adapter->buildDiffProcess($paths),
            static fn(DiffableToolAdapter $adapter, Process $process): string => $adapter->parseDiff($process, $paths),
        );

        return new RunResult(\array_filter($result->items, static fn(string $diff): bool => $diff !== ''), $result->failures);
    }
}
