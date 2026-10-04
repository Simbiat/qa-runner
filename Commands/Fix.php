<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Helpers\PathEscaper;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Simbiat\QARunner\Interfaces\ToolAdapter;

/**
 * Runner for Fix command
 */
final readonly class Fix
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
     * Apply fixes. Deliberately sequential: fixers rewrite the real file, and two of them rewriting the same file at once can corrupt it.
     *
     * @param array<string> $paths Paths to process
     *
     * @return list<FixResult> Results of all the commands, that were ran
     */
    public function run(array $paths): array
    {
        $results = [];
        foreach ($this->adapters as $class => $adapter) {
            if (!$adapter instanceof FixableToolAdapter) {
                continue;
            }

            $process = $adapter->buildFixProcess(PathEscaper::escapedPaths($paths), $this->commands[$class]);
            $process->setTimeout(null);
            $process->run();
            $results[] = $adapter->parseFix($process, $paths);
        }

        return $results;
    }
}
