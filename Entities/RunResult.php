<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Entities;

/**
 * Result of a command, that runs several tools: what the tools returned and which of them failed to run.
 */
final readonly class RunResult
{
    /**
     * @param array<array-key, mixed> $items    Results of the tools, that ran properly
     * @param array<string, string>   $failures Tool name => reason, why the tool did not run properly
     */
    public function __construct(
        public array $items,
        public array $failures = [],
    ) {}
}
