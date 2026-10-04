<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Entities;

/**
 * Shape of QA tool finding objects
 */
final readonly class Finding
{
    /**
     * @param string $tool    Tool name
     * @param string $rule    Rule name
     * @param string $file    File path
     * @param int    $line    Line, where the finding is. Defaults to 1.
     * @param int    $column  Column, where the finding is. Defaults to 1.
     * @param string $message Finding description
     * @param string $type    Type of finding. Defaults to NOTICE.
     * @param bool   $fixable Whether finding is fixable.
     */
    public function __construct(
        public string $tool,
        public string $rule,
        public string $file,
        public int    $line = 1,
        public int    $column = 1,
        public string $message = 'Something is wrong here',
        public string $type = 'WARNING',
        public bool   $fixable = false,
    ) {}
}
