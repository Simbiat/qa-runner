<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Entities;

/**
 * Result or autofix from a tool
 */
final readonly class FixResult
{
    /**
     * @param string $tool    Tool name
     * @param bool   $success Whether run was successful (on exit code was 0)
     * @param string $output  Output of the tool
     */
    public function __construct(
        public string $tool,
        public bool   $success,
        public string $output,
    ) {}
}
