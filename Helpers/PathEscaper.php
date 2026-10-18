<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Escape paths.
 */
final class PathEscaper
{
    /**
     * Escape paths.
     *
     * @param array $paths Paths to escape
     *
     * @return string Escapted paths as a string for CLI
     */
    public static function escapedPaths(array $paths): string
    {
        return \implode('', \array_map(static fn(string $p) => ' '.\escapeshellarg($p), $paths));
    }
}
