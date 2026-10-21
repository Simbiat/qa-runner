<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * Config class for QA Runner.
 */
final readonly class Config
{
    /**
     * Maximum depth of `@script` references, to stop circular references.
     */
    private const int MAX_DEPTH = 10;

    /**
     * @param array<string, mixed> $data         full composer.json content
     * @param string               $project_root Path to project root
     */
    private function __construct(
        private array $data,
        private string $project_root
    ) {}

    /**
     * Attempt to get composer.json content from project root.
     *
     * @param string $project_root Path to project root directory
     *
     * @throws \JsonException
     */
    public static function fromProjectRoot(string $project_root): self
    {
        $root = \rtrim($project_root, '/');
        $file = $root.'/composer.json';
        $data = \is_file($file) ? \json_decode((string) \file_get_contents($file), associative: true, depth: 512, flags: \JSON_THROW_ON_ERROR) : null;

        return new self(\is_array($data) ? $data : [], $root);
    }

    /**
     * Get commands from composer.json for respective set.
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
     * Absolute path to the directory with binaries of Composer packages. Composer adds it to PATH for its scripts, so commands there use short names.
     *
     * @return string
     */
    public function binDir(): string
    {
        $vendor = $this->data['config']['vendor-dir'] ?? 'vendor';
        $bin = $this->data['config']['bin-dir'] ?? '{$vendor-dir}/bin';
        $bin = \str_replace('{$vendor-dir}', \is_string($vendor) ? $vendor : 'vendor', \is_string($bin) ? $bin : 'vendor/bin');

        return \str_starts_with($bin, '/') ? \rtrim($bin, '/') : $this->project_root.'/'.\trim($bin, '/');
    }

    /**
     * Resolve `@` references, that should take command from respective command from composer.json `scripts` node.
     * Supports `@php`, `@script-name`, `@script-name extra arguments`, scripts with a single command in an array, and references inside scripts.
     *
     * @param string $spec  Literal value from composer.json
     * @param int    $depth Current depth of references
     *
     * @return string
     */
    private function resolveCommand(string $spec, int $depth = 0): string
    {
        if (!\str_starts_with($spec, '@')) {
            return $spec;
        }

        if (\preg_match('/^@php(?=\s|$)(.*)$/s', $spec, $matches) === 1) {
            return \escapeshellarg(\PHP_BINARY).$matches[1];
        }

        [$script_name, $arguments] = \array_pad(\explode(' ', \substr($spec, 1), 2), 2, '');
        $script = $this->data['scripts'][$script_name] ?? null;
        if (\is_array($script) && \count($script) === 1) {
            $script = \reset($script);
        }

        if (\is_string($script) && $depth < self::MAX_DEPTH) {
            $command = $this->resolveCommand($script, $depth + 1);

            return $arguments === '' ? $command : $command.' '.$arguments;
        }

        \fwrite(\STDERR, "qa-runner: composer script '$script_name' not found (or not a plain string, or a single command in an array) for '$spec', using it literally.\n");

        return $spec;
    }
}
