<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use Simbiat\QARunner\Entities\Finding;

/**
 * Replaces path prefixes, for example to change paths inside a container to paths on the host, which the IDE can open.
 */
final readonly class PathMap
{
    /**
     * @param list<array{from: string, to: string, backslashes: bool}> $rules Rules, longest prefix first
     */
    private function __construct(
        private array $rules,
    ) {}

    /**
     * Create the map from `from=to` strings.
     *
     * @param list<string> $options Strings like `/app=C:\projects\site`. The first `=` splits the string.
     *
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public static function fromOptions(array $options): self
    {
        $rules = [];
        foreach ($options as $option) {
            $position = \strpos($option, '=');
            if ($position === false || $position === 0 || $position === \strlen($option) - 1) {
                throw new \InvalidArgumentException("Option `--path-map` needs the format `from=to`, for example `/app=C:\\projects\\site`. Got '{$option}'.");
            }

            $from = \rtrim(\substr($option, 0, $position), '/\\');
            $to = \substr($option, $position + 1);
            // Windows style, if the target has backslashes and no slashes
            $backslashes = \str_contains($to, '\\') && !\str_contains($to, '/');
            $rules[] = ['from' => $from, 'to' => \rtrim($to, '/\\'), 'backslashes' => $backslashes];
        }

        // Longest prefix first, so the most specific rule wins. The sort is stable, so for the same prefix the first rule wins.
        \usort($rules, static fn(array $a, array $b): int => \strlen($b['from']) <=> \strlen($a['from']));

        return new self($rules);
    }

    /**
     * Check if the map has no rules.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * Change the path, if it is inside the `from` directory of a rule. The prefix must end at a directory boundary.
     *
     * @param string $path Path to change
     *
     * @return string
     */
    public function apply(string $path): string
    {
        foreach ($this->rules as $rule) {
            if ($path !== $rule['from'] && !\str_starts_with($path, $rule['from'].'/')) {
                continue;
            }

            $rest = \substr($path, \strlen($rule['from']));
            if ($rule['backslashes']) {
                $rest = \str_replace('/', '\\', $rest);
            }

            return $rule['to'].$rest;
        }

        return $path;
    }

    /**
     * Change the file paths of the findings.
     *
     * @param array<Finding> $findings Findings
     *
     * @return array<Finding>
     */
    public function applyToFindings(array $findings): array
    {
        if ($this->rules === []) {
            return $findings;
        }

        return \array_map(
            fn(Finding $finding): Finding => new Finding(
                tool: $finding->tool,
                rule: $finding->rule,
                file: $this->apply($finding->file),
                line: $finding->line,
                column: $finding->column,
                message: $finding->message,
                type: $finding->type,
                fixable: $finding->fixable,
            ),
            $findings,
        );
    }
}
