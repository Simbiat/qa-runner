<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Entities\FixRun;
use Simbiat\QARunner\Helpers\FailureMessage;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Helpers\TempCopies;
use Simbiat\QARunner\Helpers\UnifiedDiff;

/**
 * Runner for `fix` and `diff` commands.
 */
final readonly class Fix
{
    /**
     * @param array<string, \Simbiat\QARunner\Interfaces\FixableToolAdapter> $adapters List of adapters to use
     * @param float|null                                                     $timeout  Timeout in seconds for each tool. Null means no timeout.
     */
    public function __construct(
        private array  $adapters,
        private ?float $timeout = null,
    ) {}

    /**
     * Run the fixers on temporary copies of the files, one tool after another. Each tool gets the result of the previous one.
     * Deliberately sequential: two fixers, that change the same file at the same time, can corrupt it.
     * A tool, that fails to run, gives an unsuccessful result, and the next tool still runs. If the process was created, the result has the full command line.
     * The copies are always removed at the end.
     *
     * @param Paths $paths Paths to process
     * @param bool  $apply True to write the result over the original files. False to only create a diff for each file and leave the originals as they are.
     *
     * @return \Simbiat\QARunner\Entities\FixRun
     *
     * @throws \RuntimeException If a path is a directory, or a temporary copy cannot be made
     */
    public function run(Paths $paths, bool $apply): FixRun
    {
        $copies = TempCopies::create($paths);

        try {
            if ($copies->isEmpty()) {
                return new FixRun([]);
            }

            $results = $this->runTools($copies);
            $diffs = [];
            $errors = [];
            foreach ($copies->changed() as $original) {
                try {
                    $diffs[$original] = UnifiedDiff::between($original, $copies->tempFor($original), $original);
                } catch (\Throwable $exception) {
                    $errors[] = "Failed to create the diff for '{$original}': ".$exception->getMessage();
                }
            }

            if ($apply) {
                // The diffs are made before this, because the copies are still the source of the changes
                $errors = [...$errors, ...$copies->apply()];
            }

            return new FixRun($results, $diffs, $errors);
        } finally {
            $copies->cleanup();
        }
    }

    /**
     * Run all the tools on the copies.
     *
     * @param \Simbiat\QARunner\Helpers\TempCopies $copies Temporary copies
     *
     * @return list<\Simbiat\QARunner\Entities\FixResult>
     */
    private function runTools(TempCopies $copies): array
    {
        $tool_paths = $copies->paths();
        $results = [];
        foreach ($this->adapters as $adapter) {
            $process = null;

            try {
                $process = $adapter->buildFixProcess($tool_paths);
                $process->setTimeout($this->timeout);
                $process->run();
                $result = $adapter->parseFix($process, $tool_paths);
                $results[] = new FixResult($result->tool, $result->success, $copies->restoreNames($result->output));
            } catch (\Throwable $exception) {
                $message = $process === null ? $exception->getMessage() : FailureMessage::forRun($process, $exception);
                $results[] = new FixResult($adapter->name(), success: false, output: $copies->restoreNames($message));
            }
        }

        return $results;
    }
}
