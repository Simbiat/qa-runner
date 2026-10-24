<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Formatters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\RuleCounter;
use Simbiat\QARunner\Interfaces\OutputFormatter;

/**
 * Format findings in CSV format compliant with RFC 4180.
 */
final readonly class CSV implements OutputFormatter
{
    /**
     * @param bool $count Add the `Count` column: how many times the rule of the finding was found in total
     */
    public function __construct(
        private bool $count = false,
    ) {}

    /**
     * Main function to prepare the output.
     *
     * @param array<Finding> $findings List of found issues
     *
     * @return string
     */
    public function format(array $findings): string
    {
        $counts = $this->count ? RuleCounter::count($findings) : [];
        $lines = [];
        $lines[] = '"File","Line","Column","Tool","Fixable","Type","Message","Rule"'.($this->count ? ',"Count"' : '');
        foreach ($findings as $finding) {
            $fields = [
                self::escapeField($finding->file),
                (string) $finding->line,
                (string) $finding->column,
                self::escapeField($finding->tool),
                $finding->fixable ? '"Y"' : '"N"',
                self::escapeField($finding->type->value),
                self::escapeField($finding->message),
                self::escapeField($finding->rule),
            ];
            if ($this->count) {
                $fields[] = (string) $counts[RuleCounter::key($finding)];
            }

            $lines[] = \implode(',', $fields);
        }

        return \implode("\r\n", $lines);
    }

    /**
     * Escape a field for RFC 4180 CSV compliance.
     *
     * @param string $string The value to escape
     *
     * @return string
     */
    private static function escapeField(string $string): string
    {
        return '"'.\str_replace('"', '""', $string).'"';
    }
}
