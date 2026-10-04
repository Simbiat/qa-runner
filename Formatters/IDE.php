<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Formatters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\Sorter;
use Simbiat\QARunner\Interfaces\OutputFormatter;

/**
 * Format findings in a way suitable to be fed to IDE or similar tools, for example to be used by JetBrains File Watcher.
 */
final readonly class IDE implements OutputFormatter
{
    /**
     * @param bool $by_rule Whether sorting by rule should be used
     */
    public function __construct(private bool $by_rule = false) {}

    /**
     * Main function to prepare the output
     *
     * @param array<Finding> $findings List of found issues
     *
     * @return string
     */
    public function format(array $findings): string
    {
        if ($this->by_rule) {
            $findings = Sorter::sortByType($findings);
        } else {
            $findings = Sorter::sortByFile($findings);
        }
        $lines = [];
        foreach ($findings as $finding) {
            $lines[] = \sprintf(
                '%s:%d:%d: [%s, %s%s] %s (%s)',
                $finding->file,
                // At least PHPStorm does not show items with line 0
                ($finding->line > 0 ? $finding->line : 1),
                ($finding->column > 0 ? $finding->column : 1),
                $finding->tool,
                $finding->fixable ? 'Fixable ' : '',
                \mb_strtoupper($finding->type),
                $finding->message,
                $finding->rule,
            );
        }

        return \implode("\n", $lines);
    }
}
