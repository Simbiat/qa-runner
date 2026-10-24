<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Unified diff between two files. Written in PHP (Myers algorithm), so it does not need the `diff` program of the system.
 */
final class UnifiedDiff
{
    /**
     * Number of unchanged lines around each change.
     */
    private const int CONTEXT = 3;

    /**
     * Maximum number of changed lines for the exact algorithm. If a file has more, all the lines between the common start and the common end are shown as replaced.
     */
    private const int MAX_EDIT_DISTANCE = 3000;

    /**
     * Create the diff.
     *
     * @param string $original Path to the original file
     * @param string $modified Path to the changed file
     * @param string $label    Name to show in the headers of the diff
     *
     * @return string Diff without a trailing line break. Empty string if the files are the same.
     *
     * @throws \RuntimeException If a file cannot be read
     */
    public static function between(string $original, string $modified, string $label): string
    {
        $old = \file_get_contents($original);
        $new = \file_get_contents($modified);
        if ($old === false || $new === false) {
            throw new \RuntimeException('Failed to read the files for the diff.');
        }

        if ($old === $new) {
            return '';
        }

        $operations = self::operations(self::lines($old), self::lines($new));

        return \rtrim("--- {$label} (original)\n+++ {$label} (fixed)\n".self::hunks($operations));
    }

    /**
     * Split text to lines. Each line keeps its line break, so a missing line break at the end of the file is a difference too.
     *
     * @param string $text Text
     *
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        return $text === '' ? [] : (\preg_split('/(?<=\n)/', $text, -1, \PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Compare 2 lists of lines.
     *
     * @param list<string> $old Old lines
     * @param list<string> $new New lines
     *
     * @return list<array{0: string, 1: string}> Operations in order: ` ` (same), `-` (removed) or `+` (added), and the line
     */
    private static function operations(array $old, array $new): array
    {
        $old_count = \count($old);
        $new_count = \count($new);
        $start = 0;
        while ($start < $old_count && $start < $new_count && $old[$start] === $new[$start]) {
            ++$start;
        }

        $end = 0;
        while ($end < $old_count - $start && $end < $new_count - $start && $old[$old_count - 1 - $end] === $new[$new_count - 1 - $end]) {
            ++$end;
        }

        $middle = self::myers(\array_slice($old, $start, $old_count - $start - $end), \array_slice($new, $start, $new_count - $start - $end));

        $operations = [];
        for ($i = 0; $i < $start; ++$i) {
            $operations[] = [' ', $old[$i]];
        }

        foreach ($middle as $operation) {
            $operations[] = $operation;
        }

        for ($i = $old_count - $end; $i < $old_count; ++$i) {
            $operations[] = [' ', $old[$i]];
        }

        return $operations;
    }

    /**
     * Myers diff algorithm.
     *
     * @param list<string> $old Old lines
     * @param list<string> $new New lines
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function myers(array $old, array $new): array
    {
        $old_count = \count($old);
        $new_count = \count($new);
        $replace = static function () use ($old, $new): array {
            return [...\array_map(static fn(string $line): array => ['-', $line], $old), ...\array_map(static fn(string $line): array => ['+', $line], $new)];
        };
        if ($old_count === 0 || $new_count === 0) {
            return $replace();
        }

        $max = \min($old_count + $new_count, self::MAX_EDIT_DISTANCE);
        $v = [1 => 0];
        $trace = [];
        $found = false;
        for ($d = 0; $d <= $max && !$found; ++$d) {
            $trace[$d] = $v;
            for ($k = -$d; $k <= $d; $k += 2) {
                $x = ($k === -$d || ($k !== $d && $v[$k - 1] < $v[$k + 1])) ? $v[$k + 1] : $v[$k - 1] + 1;
                $y = $x - $k;
                while ($x < $old_count && $y < $new_count && $old[$x] === $new[$y]) {
                    ++$x;
                    ++$y;
                }

                $v[$k] = $x;
                if ($x >= $old_count && $y >= $new_count) {
                    $found = true;

                    break;
                }
            }
        }

        if (!$found) {
            return $replace();
        }

        $operations = [];
        $x = $old_count;
        $y = $new_count;
        for ($d = \count($trace) - 1; $d >= 0; --$d) {
            $previous = $trace[$d];
            $k = $x - $y;
            $previous_k = ($k === -$d || ($k !== $d && $previous[$k - 1] < $previous[$k + 1])) ? $k + 1 : $k - 1;
            $previous_x = $d === 0 ? 0 : $previous[$previous_k];
            $previous_y = $d === 0 ? 0 : $previous_x - $previous_k;
            while ($x > $previous_x && $y > $previous_y) {
                $operations[] = [' ', $old[$x - 1]];
                --$x;
                --$y;
            }

            if ($d > 0) {
                $operations[] = $x === $previous_x ? ['+', $new[$y - 1]] : ['-', $old[$x - 1]];
                $x = $previous_x;
                $y = $previous_y;
            }
        }

        return \array_reverse($operations);
    }

    /**
     * Group operations to hunks with context lines and format them.
     *
     * @param list<array{0: string, 1: string}> $operations Operations
     *
     * @return string
     */
    private static function hunks(array $operations): string
    {
        $changes = [];
        $old_before = [];
        $new_before = [];
        $old_line = 0;
        $new_line = 0;
        foreach ($operations as $index => [$type]) {
            $old_before[$index] = $old_line;
            $new_before[$index] = $new_line;
            if ($type !== '+') {
                ++$old_line;
            }

            if ($type !== '-') {
                ++$new_line;
            }

            if ($type !== ' ') {
                $changes[] = $index;
            }
        }

        $ranges = [];
        foreach ($changes as $index) {
            $from = \max(0, $index - self::CONTEXT);
            $to = \min(\count($operations) - 1, $index + self::CONTEXT);
            $last = \count($ranges) - 1;
            if ($last >= 0 && $from <= $ranges[$last][1] + 1) {
                $ranges[$last][1] = $to;
            } else {
                $ranges[] = [$from, $to];
            }
        }

        $text = '';
        foreach ($ranges as [$from, $to]) {
            $old_count = 0;
            $new_count = 0;
            $body = '';
            for ($i = $from; $i <= $to; ++$i) {
                [$type, $line] = $operations[$i];
                $old_count += $type === '+' ? 0 : 1;
                $new_count += $type === '-' ? 0 : 1;
                $body .= $type.$line.(\str_ends_with($line, "\n") ? '' : "\n\\ No newline at end of file\n");
            }

            $old_start = $old_before[$from] + ($old_count === 0 ? 0 : 1);
            $new_start = $new_before[$from] + ($new_count === 0 ? 0 : 1);
            $text .= '@@ -'.$old_start.($old_count === 1 ? '' : ','.$old_count).' +'.$new_start.($new_count === 1 ? '' : ','.$new_count)." @@\n".$body;
        }

        return $text;
    }
}
