<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Paths from the command line. They are always absolute and exist, so the tools (which run in the project root) and the filters get the same paths.
 */
final readonly class Paths
{
    /**
     * @param list<string> $absolute Absolute canonical paths
     */
    private function __construct(public array $absolute) {}

    /**
     * Resolve paths against the current directory.
     *
     * @param array<string> $raw Paths as they came from CLI
     * @param string        $cwd Directory to resolve relative paths against
     *
     * @return self
     *
     * @throws \InvalidArgumentException If a path does not exist or is not readable
     */
    public static function fromCli(array $raw, string $cwd): self
    {
        $absolute = [];
        foreach ($raw as $path) {
            $real = \realpath(\str_starts_with($path, '/') ? $path : \mb_rtrim($cwd, '/', 'UTF-8').'/'.$path);
            if ($real === false || !\is_readable($real)) {
                throw new \InvalidArgumentException('Not a readable path: '.$path);
            }
            $absolute[] = $real;
        }

        return new self(\array_values(\array_unique($absolute)));
    }

    /**
     * Paths as a string for the command line. Each path has a space before it.
     *
     * @return string
     */
    public function escaped(): string
    {
        return \implode('', \array_map(static fn(string $path): string => ' '.\escapeshellarg($path), $this->absolute));
    }

    /**
     * First path. Used as a place to attach findings, that are not tied to a file.
     *
     * @return string
     */
    public function first(): string
    {
        return $this->absolute[0] ?? '';
    }

    /**
     * Check if the file is one of the paths or is inside one of them.
     *
     * @param string $file File path as returned by a tool
     *
     * @return bool
     */
    public function covers(string $file): bool
    {
        if ($this->matches($file)) {
            return true;
        }
        $real = \realpath($file);

        return $real !== false && $this->matches($real);
    }

    /**
     * Compare the file with the paths without touching the file system.
     *
     * @param string $file File path
     *
     * @return bool
     */
    private function matches(string $file): bool
    {
        return \array_any($this->absolute, static fn(string $path): bool => $file === $path || \str_starts_with($file, \mb_rtrim($path, '/', 'UTF-8').'/'));
    }
}
