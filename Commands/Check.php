<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Commands;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\PathEscaper;
use Simbiat\QARunner\Interfaces\ToolAdapter;

/**
 * Runner for Check command
 */
final readonly class Check
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
     * Get list of findings from QA tools.
     *
     * @param array<string> $paths Paths to process
     *
     * @return array<Finding> List of parsed findings
     */
    public function run(array $paths): array
    {
        // Start every applicable tool's process without blocking, so they all run in parallel (safe since read-only).
        $running = [];
        foreach ($this->adapters as $class => $adapter) {
            $process = $adapter->buildCheckProcess(PathEscaper::escapedPaths($paths), $this->commands[$class]);
            $process->setTimeout(null);
            $process->start();
            $running[] = [$adapter, $process];
        }

        // wait() on an already-started process just blocks until *that* one finishes - the others keep running in the background, so this loop doesn't re-serialize anything.
        $chunks = [];
        foreach ($running as [$adapter, $process]) {
            $process->wait();
            try {
                $chunks[] = $adapter->parseCheck($process, $paths);
            } catch (\Throwable $exception) {
                \fwrite(\STDERR, 'Failed to parse the output of command `'.$process->getCommandLine()."`.\n".$exception->getMessage()."\n".$exception->getTraceAsString()."\n");
                exit(2);
            }
        }
        $findings = \array_merge([], ...$chunks);

        \usort(
            $findings,
            static fn(Finding $a, Finding $b) => [$a->file, $a->line, !$a->fixable]
                <=> [$b->file, $b->line, !$b->fixable],
        );

        return $findings;
    }
}
