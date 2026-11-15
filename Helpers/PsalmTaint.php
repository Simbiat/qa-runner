<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Splits Psalm taint issues (issues with `taint_trace`) into separate issues, one for each place in the flow of tainted data.
 *
 * The new issues have the same shape as the Psalm ones, so the adapter can process them with the same code as other issues:
 * - `sink`: the original issue without `taint_trace`. The type is the original one (for example, `TaintedFile`).
 * - `source`: the first place of the trace. The type has the `.source` suffix.
 * - `step`: each place between the source and the sink. The type has the `.step` suffix, the severity is `info`, and `is_security` is false.
 *
 * Fields of each new issue are copied from the original one. Fields that exist in the trace node (`line_from`, `file_path`, `snippet` and so on) replace the copied ones. `selected_text` and `snippet_to` do not exist in trace nodes. They are removed from sources and steps, because they would describe the sink.
 *
 * The messages describe only the place itself and its direct neighbours, never the whole flow. So the same place from different flows gives identical issues, and the adapter can remove the duplicates.
 *
 * Nodes without a location (property markers) and nodes from Psalm stubs are dropped. Consecutive nodes on the same line of the same file become one step.
 */
final class PsalmTaint
{
    /**
     * Maximum length of a code snippet in a message.
     */
    private const int SNIPPET_LIMIT = 120;

    /**
     * Check if the issue has a taint trace.
     *
     * @param array<array-key, mixed> $issue Issue from the JSON output of Psalm
     *
     * @return bool
     */
    public static function isTaint(array $issue): bool
    {
        return isset($issue['taint_trace']) && \is_array($issue['taint_trace']) && $issue['taint_trace'] !== [];
    }

    /**
     * Split the issue. An issue without a taint trace is returned as is.
     *
     * @param array<array-key, mixed> $issue Issue from the JSON output of Psalm
     *
     * @return list<array<array-key, mixed>>
     */
    public static function unpack(array $issue): array
    {
        if (!self::isTaint($issue)) {
            return [$issue];
        }

        $base = $issue;
        unset($base['taint_trace']);
        $rule = (string) ($issue['type'] ?? 'unknown');
        $message = (string) ($issue['message'] ?? '');
        $steps = self::steps($issue);

        // The last node is the sink. The original issue describes it, so it is not a separate step
        $sink_label = $rule;
        $last = \array_key_last($steps);
        if ($last !== null && self::sameLine($steps[$last]['node'], $issue)) {
            $sink_label = $steps[$last]['label'];
            \array_pop($steps);
        }

        $items = [];
        $last = \count($steps) - 1;
        foreach ($steps as $index => $step) {
            $item = self::merge($base, $step['node']);
            $previous = $index > 0 ? self::location($steps[$index - 1]['node']) : null;
            $next = self::location($index < $last ? $steps[$index + 1]['node'] : $issue);
            if ($index === 0) {
                $item['type'] = $rule.'.source';
                $item['message'] = self::sentence(
                    'Taint source: `'.$step['label'].'`'.self::in(self::snippet((string) ($step['node']['snippet'] ?? ''))).'.',
                    'Leads to: '.$message.'.',
                    'Next step: '.$next.'.',
                );
            } else {
                $item['type'] = $rule.'.step';
                $item['severity'] = $issue['is_security'] ?? 'ERROR';
                $item['is_security'] = $issue['is_security'] ?? false;
                $item['message'] = self::sentence(
                    'Taint flow step: `'.$step['label'].'`.',
                    'Leads to: '.$message.'.',
                    'Previous step: '.$previous.'.',
                    'Next step: '.$next.'.',
                );
            }

            $items[] = $item;
        }

        $base['message'] = self::sentence(
            'Taint sink: '.$message.'.',
            '`'.$sink_label.'`'.self::in(self::snippet((string) ($issue['snippet'] ?? ''))).'.',
            $steps === [] ? '' : 'Previous step: '.self::location($steps[$last]['node']).'.',
        );
        $items[] = $base;

        return $items;
    }

    /**
     * Get usable trace nodes, from the source to the sink.
     *
     * @param array<array-key, mixed> $issue Issue from the JSON output of Psalm
     *
     * @return list<array{node: array<array-key, mixed>, label: string}>
     */
    private static function steps(array $issue): array
    {
        $steps = [];
        $last_label = '';
        foreach ($issue['taint_trace'] ?? [] as $node) {
            if (!\is_array($node)) {
                continue;
            }

            $file = (string) ($node['file_path'] ?? '');
            if ($file === '' || (int) ($node['line_from'] ?? 0) < 1 || \str_ends_with($file, '.phpstub')) {
                continue;
            }

            $last_label = self::label((string) ($node['label'] ?? ''));
            $previous = \array_key_last($steps);
            if ($previous !== null && self::sameLine($steps[$previous]['node'], $node)) {
                continue;
            }

            $steps[] = ['node' => $node, 'label' => $last_label];
        }

        // If the last line has several nodes, the last node is the one, that matters (for example, the call of the sink)
        $last = \array_key_last($steps);
        if ($last !== null) {
            $steps[$last]['label'] = $last_label;
        }

        return $steps;
    }

    /**
     * Copy the issue and replace its fields with the ones from the trace node.
     *
     * @param array<array-key, mixed> $base Issue without the trace
     * @param array<array-key, mixed> $node Trace node
     *
     * @return array<array-key, mixed>
     */
    private static function merge(array $base, array $node): array
    {
        foreach ($node as $key => $value) {
            if ($key !== 'label' && $key !== 'entry_path_type') {
                $base[$key] = $value;
            }
        }

        foreach (['selected_text', 'snippet_to'] as $key) {
            if (!\array_key_exists($key, $node)) {
                unset($base[$key]);
            }
        }

        return $base;
    }

    /**
     * Check if both nodes are on the same line of the same file.
     *
     * @param array<array-key, mixed> $a Issue or trace node
     * @param array<array-key, mixed> $b Issue or trace node
     *
     * @return bool
     */
    private static function sameLine(array $a, array $b): bool
    {
        return ($a['file_path'] ?? '') === ($b['file_path'] ?? '') && ($a['line_from'] ?? 0) === ($b['line_from'] ?? 0);
    }

    /**
     * Location in the `file:line:column` form, which IDEs can open.
     *
     * @param array<array-key, mixed> $node Issue or trace node
     *
     * @return string
     */
    private static function location(array $node): string
    {
        return ($node['file_path'] ?? '').':'.\max(1, (int) ($node['line_from'] ?? 0)).':'.\max(1, (int) ($node['column_from'] ?? 0));
    }

    /**
     * Join the non-empty parts of a message.
     *
     * @param string ...$parts Parts of the message
     *
     * @return string
     */
    private static function sentence(string ...$parts): string
    {
        return \implode(' ', \array_filter($parts, static fn(string $part): bool => $part !== ''));
    }

    /**
     * Snippet as a part of a message. Empty snippet gives an empty string.
     *
     * @param string $snippet Normalized snippet
     *
     * @return string
     */
    private static function in(string $snippet): string
    {
        return $snippet === '' ? '' : ' in `'.$snippet.'`';
    }

    /**
     * Make a Psalm node label readable: `Class::method#1` becomes `parameter 1 of Class::method`.
     *
     * @param string $label Label from the trace
     *
     * @return string
     */
    private static function label(string $label): string
    {
        return \preg_replace('/^(.+)#(\d+)$/u', 'parameter $2 of $1', $label) ?? $label;
    }

    /**
     * Collapse whitespace of a snippet and limit its length.
     *
     * @param string $snippet Code snippet
     *
     * @return string
     */
    private static function snippet(string $snippet): string
    {
        $snippet = \trim(\preg_replace('/\s+/u', ' ', $snippet) ?? $snippet);

        return \mb_strlen($snippet, 'UTF-8') > self::SNIPPET_LIMIT
            ? \mb_substr($snippet, 0, self::SNIPPET_LIMIT, 'UTF-8').'...'
            : $snippet;
    }
}
