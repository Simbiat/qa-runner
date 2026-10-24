<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Colors a unified diff for the console: added lines are green, removed lines are red, hunk headers are cyan.
 * Based on the diff formatter of Rector, which is based on the one of PHP CS Fixer.
 * The result has Symfony Console formatting tags, so write it without `OUTPUT_RAW`.
 */
final class ColorDiff
{
    /**
     * Color the diff of one file. The `---` and `+++` headers are replaced with a header with the path of the file.
     *
     * @param string $path Path of the file
     * @param string $diff Unified diff from `UnifiedDiff::between()`
     *
     * @return string
     */
    public static function format(string $path, string $diff): string
    {
        $lines = \preg_split('/\R/', OutputFormatter::escape(\rtrim($diff))) ?: [];
        $result = [];
        foreach ($lines as $line) {
            if (\str_starts_with($line, '--- ') || \str_starts_with($line, '+++ ')) {
                continue;
            }

            $result[] = match (true) {
                $line === ' ' => '',
                \str_starts_with($line, '+') => '<fg=green>'.$line.'</>',
                \str_starts_with($line, '-') => '<fg=red>'.$line.'</>',
                \str_starts_with($line, '@@') => '<fg=cyan>'.self::simplifyHunkHeader($line).'</>',
                default => $line,
            };
        }

        return '<comment>---------- begin diff: '.OutputFormatter::escape($path).' ----------</comment>'."\n"
            .\implode("\n", $result)."\n"
            .'<comment>----------- end diff -----------</comment>';
    }

    /**
     * Simplify the hunk header: `@@ -67,6 +67,8 @@` becomes `@@ Line 67 @@`.
     *
     * @param string $line Hunk header
     *
     * @return string
     */
    private static function simplifyHunkHeader(string $line): string
    {
        return \preg_replace('/^@@ -(\d+)(?:,\d+)? \+\d+(?:,\d+)? @@/', '@@ Line $1 @@', $line) ?? $line;
    }
}
