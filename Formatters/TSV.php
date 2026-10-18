<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Formatters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Interfaces\OutputFormatter;

/**
 * Format findings in CSV format compliant with RFC 4180.
 */
final readonly class TSV implements OutputFormatter
{
    /**
     * Main function to prepare the output.
     *
     * @param array<Finding> $findings List of found issues
     *
     * @return string
     */
    public function format(array $findings): string
    {
        $lines = [];
        $lines[] = "\"File\"\t\"Line\"\t\"Column\"\t\"Tool\"\t\"Fixable\"\t\"Type\"\t\"Message\"\t\"Rule\"";
        foreach ($findings as $finding) {
            $fields = [
                $this->escapeField($finding->file),
                (string) $finding->line,
                (string) $finding->column,
                $this->escapeField($finding->tool),
                $finding->fixable ? '"Y"' : '"N"',
                $this->escapeField($finding->type->value),
                $this->escapeField($finding->message),
                $this->escapeField($finding->rule),
            ];
            $lines[] = \implode("\t", $fields);
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
    private function escapeField(string $string): string
    {
        return '"'.\str_replace('"', '""', $string).'"';
    }
}
