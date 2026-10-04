<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * Config class for QA Runner
 */
final readonly class Config
{
    /** @param array<string, mixed> $data full composer.json content */
    private function __construct(private array $data) {}

    /**
     * Attempt to get composer.json content from project root
     *
     * @param string $project_root Path to project root directory
     *
     * @throws \JsonException
     */
    public static function fromProjectRoot(string $project_root): self
    {
        $file = \rtrim($project_root, '/').'/composer.json';
        $data = \is_file($file) ? \json_decode((string) \file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR) : null;

        return new self(\is_array($data) ? $data : []);
    }

    /**
     * Get commands from compose.json for respective set
     *
     * @param string $mode Respective mode to get commands for
     *
     * @return array<class-string<\Simbiat\QARunner\Interfaces\ToolAdapter>, string>
     */
    public function commandsFor(#[ExpectedValues(['check', 'diff', 'fix'])] string $mode): array
    {
        $entries = $this->data['extra']['qa-runner'][$mode] ?? [];
        if (!\is_array($entries)) {
            return [];
        }

        $resolved = [];
        foreach ($entries as $class => $spec) {
            if (\is_string($spec)) {
                $resolved[$class] = $this->resolveCommand($spec);
            }
        }

        return $resolved;
    }

    /**
     * Resolve `@` references, that should take command from respective command from composer.json `scripts` node.
     *
     * @param string $spec Literal value from composer.json
     *
     * @return string
     */
    private function resolveCommand(string $spec): string
    {
        if (!str_starts_with($spec, '@')) {
            return $spec;
        }

        $script_name = \substr($spec, 1);
        $script = $this->data['scripts'][$script_name] ?? null;

        if (\is_string($script)) {
            return $script;
        }

        \fwrite(\STDERR, "qa-runner: composer script '$script_name' not found (or not a plain string) for '$spec', using it literally.\n");

        return $spec;
    }
}
