<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use JetBrains\PhpStorm\Pure;
use Simbiat\QARunner\Entities\Finding;

/**
 * Counts how many times each rule was found. A rule is identified by the tool and the rule name, since different tools can have rules with the same name.
 */
final class RuleCounter
{
    /**
     * Count the findings of each rule.
     *
     * @param array<array-key, \Simbiat\QARunner\Entities\Finding> $findings List of findings
     *
     * @return array<string, int> Key from `key()` => number of findings
     */
    #[Pure]
    public static function count(array $findings): array
    {
        $counts = [];
        foreach ($findings as $finding) {
            $key = self::key($finding);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Key of the rule of the finding.
     *
     * @param \Simbiat\QARunner\Entities\Finding $finding Finding
     *
     * @return string
     */
    public static function key(Finding $finding): string
    {
        return $finding->tool."\0".$finding->rule;
    }
}
