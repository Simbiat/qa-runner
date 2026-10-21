<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Entities;

/**
 * Shape of QA tool finding objects.
 */
final readonly class Finding
{
    /**
     * @var int Line, where the finding is. Always 1 or more.
     */
    public int $line;

    /**
     * @var int Column, where the finding is. Always 1 or more.
     */
    public int $column;

    /**
     * @var string Finding description, always on a single line.
     */
    public string $message;

    /**
     * @param string   $tool    Tool name
     * @param string   $rule    Rule name
     * @param string   $file    File path
     * @param int      $line    Line, where the finding is. Defaults to 1. Values below 1 become 1, since IDEs do not show them.
     * @param int      $column  Column, where the finding is. Defaults to 1. Values below 1 become 1.
     * @param string   $message Finding description. Line breaks are replaced with spaces.
     * @param Severity $type    Type of finding. Defaults to WARNING.
     * @param bool     $fixable Whether finding is fixable.
     */
    public function __construct(
        public string   $tool,
        public string   $rule,
        public string   $file,
        int             $line = 1,
        int             $column = 1,
        string          $message = 'Something is wrong here',
        public Severity $type = Severity::WARNING,
        public bool     $fixable = false,
    ) {
        $this->line = \max(1, $line);
        $this->column = \max(1, $column);
        $this->message = \trim(\preg_replace('/\s*\R\s*/u', ' ', $message) ?? $message);
    }
}
