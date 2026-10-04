<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Formatters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\Sorter;
use Simbiat\QARunner\Interfaces\OutputFormatter;

/**
 * A prettier and more human-readable format for the console with optional colors and 2 ways to sort things
 */
final class Console implements OutputFormatter
{
    /**
     * Color value for reset
     */
    private const string RESET = "\033[0m";

    /**
     * List of values for colors based on finding type
     */
    private const array COLORS = [
        'ERROR' => "\033[31m",
        'WARNING' => "\033[33m",
        'NOTICE' => "\033[36m",
    ];

    /**
     * @param bool $colors  Whether to color the output
     * @param bool $by_rule Whether sorting by rule should be used
     */
    public function __construct(private readonly bool $colors = true, private readonly bool $by_rule = false) {}

    /**
     * Main function to prepare the output
     *
     * @param array<Finding> $findings List of found issues
     *
     * @return string
     */
    public function format(array $findings): string
    {
        if ($findings === []) {
            return '';
        }

        $findings = $this->by_rule
            ? Sorter::sortByType($findings)
            : Sorter::sortByFile($findings);

        $lines = [];
        $notch_outer = null;
        $notch_bucket = null;
        $notch_rule = null;

        foreach ($findings as $finding) {
            $type = \strtoupper($finding->type);

            if (($this->by_rule ? $finding->tool : $finding->file) !== $notch_outer) {
                if ($notch_outer !== null) {
                    $lines[] = '';
                }
                $notch_outer = $this->by_rule ? $finding->tool : $finding->file;
                $notch_bucket = null;
                $notch_rule = null;
                $lines[] = $notch_outer.':';
            }

            if ($this->by_rule) {
                $bucket = ($finding->fixable ? 'Fixable ' : '').$type;
                if ($bucket !== $notch_bucket) {
                    $notch_bucket = $bucket;
                    $notch_rule = null;
                    $header = "\t".$bucket.':';
                    if ($this->colors && isset(self::COLORS[$type])) {
                        $header = self::COLORS[$type].$header.self::RESET;
                    }
                    $lines[] = $header;
                }

                if ($finding->rule !== $notch_rule) {
                    $notch_rule = $finding->rule;
                    $lines[] = "\t\t".$notch_rule.':';
                }

                $lines[] = \sprintf(
                    "\t\t\t%s:%d:%d: %s",
                    $finding->file,
                    $finding->line,
                    $finding->column,
                    $finding->message,
                );
            } else {
                $tag = \sprintf('[%s%s%s]', $finding->tool.', ', $finding->fixable ? 'Fixable ' : '', $type);
                if ($this->colors && isset(self::COLORS[$type])) {
                    $tag = self::COLORS[$type].$tag.self::RESET;
                }
                $lines[] = \sprintf(
                    "\t%d:%d: %s %s (%s)",
                    $finding->line,
                    $finding->column,
                    $tag,
                    $finding->message,
                    $finding->rule,
                );
            }
        }

        return \implode(\PHP_EOL, $lines);
    }
}
