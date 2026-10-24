<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use Simbiat\QARunner\Entities\Finding;

/**
 * Sorts Finding arrays.
 */
final class Sorter
{
    /**
     * Sort findings by the number of findings of their rule (from highest to lowest), then like `sortByType`.
     *
     * @param array<array-key, \Simbiat\QARunner\Entities\Finding> $findings List of findings
     *
     * @return array<array-key, \Simbiat\QARunner\Entities\Finding>
     */
    public static function sortByCount(array $findings): array
    {
        $counts = RuleCounter::count($findings);
        $sorted = \array_values($findings);
        \usort($sorted, static fn(Finding $a, Finding $b): int => ($counts[RuleCounter::key($b)] <=> $counts[RuleCounter::key($a)]) ?: self::compareByType($a, $b));

        return $sorted;
    }

    /**
     * Sort findings by file path, line, column and fixability (fixable first).
     *
     * @param array<array-key, Finding> $findings List of findings
     *
     * @return array<array-key, Finding>
     */
    public static function sortByFile(array $findings): array
    {
        $sorted = \array_values($findings);
        \usort($sorted, self::compareByFile(...));

        return $sorted;
    }

    /**
     * Sort findings by tool, fixability, type, rule, file, line and column.
     *
     * @param array<array-key, Finding> $findings List of findings
     *
     * @return array<array-key, Finding>
     */
    public static function sortByType(array $findings): array
    {
        $sorted = \array_values($findings);
        \usort($sorted, self::compareByType(...));

        return $sorted;
    }

    /**
     * Compare by file path, line, column and fixability.
     *
     * @param Finding $a Finding to compare
     * @param Finding $b Finding to compare
     *
     * @return int
     */
    private static function compareByFile(Finding $a, Finding $b): int
    {
        return \strnatcasecmp($a->file, $b->file)
            ?: $a->line <=> $b->line
                ?: $a->column <=> $b->column
                    // Fixable is boolean, so need to "reverse" the order
                    ?: ($b->fixable <=> $a->fixable);
    }

    /**
     * Compare by tool, fixability, type, rule, file, line and column.
     *
     * @param Finding $a Finding to compare
     * @param Finding $b Finding to compare
     *
     * @return int
     */
    private static function compareByType(Finding $a, Finding $b): int
    {
        return \strnatcasecmp($a->tool, $b->tool)
            // Fixable is boolean, so need to "reverse" the order
            ?: $b->fixable <=> $a->fixable
                ?: \strnatcasecmp($a->type->value, $b->type->value)
                    ?: \strnatcasecmp($a->rule, $b->rule)
                        ?: \strnatcasecmp($a->file, $b->file)
                            ?: $a->line <=> $b->line
                                ?: ($a->column <=> $b->column);
    }
}
