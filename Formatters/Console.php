<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Formatters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\Severity;
use Simbiat\QARunner\Interfaces\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * A prettier and more human-readable format for the console with optional colors and 2 ways to group things.
 */
final readonly class Console implements OutputFormatter
{
    /**
     * Colors by finding type (names of Symfony Console colors).
     */
    private const array COLORS = [
        'ERROR' => 'red',
        'NOTICE' => 'cyan',
        'WARNING' => 'yellow',
    ];

    /**
     * @param bool $colors  Whether to color the output (pass `$output->isDecorated()`, so `--ansi`, `--no-ansi`, `NO_COLOR` and TTY detection work)
     * @param bool $by_rule Whether grouping by tool, type and rule should be used (the findings must be sorted accordingly) instead of grouping by file
     */
    public function __construct(
        private bool $colors = true,
        private bool $by_rule = false,
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
        $lines = [];
        $notch_outer = null;
        $notch_bucket = null;
        $notch_rule = null;

        foreach ($findings as $finding) {
            $outer = $this->by_rule
                ? $finding->tool
                : $finding->file;
            if ($outer !== $notch_outer) {
                if ($notch_outer !== null) {
                    $lines[] = '';
                }

                $notch_outer = $outer;
                $notch_bucket = null;
                $notch_rule = null;
                $lines[] = $outer.':';
            }

            if ($this->by_rule) {
                $bucket = ($finding->fixable ? 'Fixable ' : '').$finding->type->value;
                if ($bucket !== $notch_bucket) {
                    $notch_bucket = $bucket;
                    $notch_rule = null;
                    $lines[] = $this->color("\t".$bucket.':', $finding->type);
                }

                if ($finding->rule !== $notch_rule) {
                    $notch_rule = $finding->rule;
                    $lines[] = "\t\t".$notch_rule.':';
                }

                $lines[] = \sprintf("\t\t\t%s:%d:%d: %s", $finding->file, $finding->line, $finding->column, $finding->message);
            } else {
                $lines[] = \sprintf(
                    "\t%d:%d: %s %s (%s)",
                    $finding->line,
                    $finding->column,
                    $this->color(\sprintf('[%s, %s%s]', $finding->tool, $finding->fixable ? 'Fixable ' : '', $finding->type->value), $finding->type),
                    $finding->message,
                    $finding->rule,
                );
            }
        }

        return \implode(\PHP_EOL, $lines);
    }

    /**
     * Color the text, if colors are enabled.
     *
     * @param string   $text Text to color
     * @param Severity $type Type of finding, that decides the color
     *
     * @return string
     */
    private function color(string $text, Severity $type): string
    {
        return $this->colors
            ? new OutputFormatterStyle(self::COLORS[$type->value])->apply($text)
            : $text;
    }
}
