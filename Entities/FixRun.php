<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Entities;

/**
 * Result of a run of the fixers on temporary copies of the files.
 */
final readonly class FixRun
{
    /**
     * @param list<\Simbiat\QARunner\Entities\FixResult> $results Result of each tool, that was run
     * @param array<string, string>                      $diffs   Original path => combined diff of all the tools. Files without changes are not included.
     * @param list<string>                               $errors  Problems, that are not a failure of a tool (for example, a file that changed during the run, or `diff` that failed)
     */
    public function __construct(
        public array $results,
        public array $diffs = [],
        public array $errors = [],
    ) {}
}
