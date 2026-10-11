<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Formatters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Interfaces\OutputFormatter;

/**
 * Format findings in a way suitable to be fed to IDE or similar tools, for example to be used by JetBrains File Watcher.
 */
final readonly class IDE implements OutputFormatter
{
    /**
     * Main function to prepare the output. Line and column are always 1 or more (IDEs, at least PhpStorm, do not show items with line 0), because `Finding` ensures it.
     *
     * @param array<Finding> $findings List of found issues
     *
     * @return string
     */
    public function format(array $findings): string
    {
        $lines = [];
        foreach ($findings as $finding) {
            $lines[] = \sprintf(
                '%s:%d:%d: [%s, %s%s] %s (%s)',
                $finding->file,
                $finding->line,
                $finding->column,
                $finding->tool,
                $finding->fixable ? 'Fixable ' : '',
                $finding->type->value,
                $finding->message,
                $finding->rule,
            );
        }

        return \implode("\n", $lines);
    }
}
