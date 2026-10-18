<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Helpers\FailureMessage;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;

/**
 * Runner for Fix command.
 */
final readonly class Fix
{
    /**
     * @param array<string, FixableToolAdapter> $adapters List of adapters to use
     * @param null|float                        $timeout  Timeout in seconds for each tool. Null means no timeout.
     */
    public function __construct(
        private array  $adapters,
        private ?float $timeout = null,
    ) {}

    /**
     * Apply fixes. Deliberately sequential: fixers rewrite the real file, and two of them rewriting the same file at once can corrupt it.
     * A tool, that fails to run, gives an unsuccessful result, and the next tool still runs. If the process was created, the result has the full command line.
     *
     * @param Paths $paths Paths to process
     *
     * @return list<FixResult> Results of all the commands, that were ran
     */
    public function run(Paths $paths): array
    {
        $results = [];
        foreach ($this->adapters as $adapter) {
            $process = null;

            try {
                $process = $adapter->buildFixProcess($paths);
                $process->setTimeout($this->timeout);
                $process->run();
                $results[] = $adapter->parseFix($process, $paths);
            } catch (\Throwable $exception) {
                $results[] = new FixResult($adapter->name(), false, $process === null ? $exception->getMessage() : FailureMessage::forRun($process, $exception));
            }
        }

        return $results;
    }
}
