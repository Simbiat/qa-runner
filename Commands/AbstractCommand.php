<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Entities\RunResult;
use Simbiat\QARunner\Helpers\FailureMessage;
use Simbiat\QARunner\Helpers\ProcessRunner;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Code, that commands share.
 */
abstract readonly class AbstractCommand
{
    /**
     * @param array<string, ToolAdapter> $adapters List of adapters to use. They must support the command.
     * @param float|null                 $timeout  Timeout in seconds for each tool. Null means no timeout.
     */
    public function __construct(
        protected array  $adapters,
        protected ?float $timeout = null,
    ) {}

    /**
     * Run the tool of every adapter in parallel. A failure of one tool does not stop the others.
     * A failure, that happens after the process was created, has the full command line in its text.
     *
     * @param \Closure(ToolAdapter): Process        $build Creates the process
     * @param \Closure(ToolAdapter, Process): mixed $parse Converts the ended process to a result
     *
     * @return RunResult Results are keyed by the names of the tools
     */
    protected function runParallel(\Closure $build, \Closure $parse): RunResult
    {
        $failures = [];
        $running = [];
        foreach ($this->adapters as $adapter) {
            try {
                $running[$adapter->name()] = [$adapter, $build($adapter)];
            } catch (\Throwable $exception) {
                $failures[$adapter->name()] = $exception->getMessage();
            }
        }

        $errors = ProcessRunner::runAll(\array_map(static fn(array $pair): Process => $pair[1], $running), $this->timeout);

        $results = [];
        foreach ($running as $name => [$adapter, $process]) {
            if (isset($errors[$name])) {
                $failures[$name] = FailureMessage::forRun($process, $errors[$name]);

                continue;
            }

            try {
                $results[$name] = $parse($adapter, $process);
            } catch (\Throwable $exception) {
                $failures[$name] = FailureMessage::forParse($process, $exception);
            }
        }

        return new RunResult($results, $failures);
    }
}
