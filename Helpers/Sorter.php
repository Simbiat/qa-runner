<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Sorts Finding arrays.
 */
final class Sorter
{
    /**
     * Sort findings by file path, line and column.
     *
     * @param array<array-key, \Simbiat\QARunner\Entities\Finding> $findings List of findings
     *
     * @return array<array-key, \Simbiat\QARunner\Entities\Finding>
     */
    public static function sortByFile(array $findings): array
    {
        $sorted = \array_values($findings);
        \usort($sorted, static fn($a, $b) => self::compareByFile($a, $b));

        return $sorted;
    }

    /**
     * Sort findings by tool, fixability, rule, file, line and column.
     *
     * @param array<array-key, \Simbiat\QARunner\Entities\Finding> $findings List of findings
     *
     * @return array<array-key, \Simbiat\QARunner\Entities\Finding>
     */
    public static function sortByType(array $findings): array
    {
        $sorted = \array_values($findings);
        \usort($sorted, static fn($a, $b) => self::compareByType($a, $b));

        return $sorted;
    }

    /**
     * Sort findings by file path, line and column.
     *
     * @param object $a Object to compare
     * @param object $b Object to compare
     *
     * @return int
     */
    private static function compareByFile(object $a, object $b): int
    {
        return \strnatcasecmp($a->file, $b->file)
            ?: ($a->line <=> $b->line)
                ?: ($a->column <=> $b->column);
    }

    /**
     * Sort findings by tool, fixability, type, rule, file, line and column.
     *
     * @param object $a Object to compare
     * @param object $b Object to compare
     *
     * @return int
     */
    private static function compareByType(object $a, object $b): int
    {
        return \strnatcasecmp($a->tool, $b->tool)
            // Fixable is boolean, so need to "reverse" the order
            ?: ($b->fixable <=> $a->fixable)
                ?: \strnatcasecmp($a->type, $b->type)
                    ?: \strnatcasecmp($a->rule, $b->rule)
                        ?: \strnatcasecmp($a->file, $b->file)
                            ?: ($a->line <=> $b->line)
                                ?: ($a->column <=> $b->column);
    }
}
