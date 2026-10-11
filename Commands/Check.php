<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\RunResult;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Runner for Check command
 */
final readonly class Check extends AbstractCommand
{
    /**
     * Get list of findings from QA tools. All tools run in parallel (safe, since they only read).
     *
     * @param Paths $paths Paths to process
     *
     * @return RunResult `items` is a list of parsed findings (not sorted), `failures` lists the tools, that did not run properly
     */
    public function run(Paths $paths): RunResult
    {
        $result = $this->runParallel(
            static fn(ToolAdapter $adapter): Process => $adapter->buildCheckProcess($paths),
            static fn(ToolAdapter $adapter, Process $process): array => $adapter->parseCheck($process, $paths),
        );

        /** @var list<Finding> $findings */
        $findings = \array_merge([], ...\array_values($result->items));

        return new RunResult($findings, $result->failures);
    }
}
