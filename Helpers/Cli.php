<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Helpers to read and change command lines, that come from composer.json.
 * Options are matched as whole words, so `--diff` does not match `--diff-only`.
 */
final class Cli
{
    /**
     * Check if the command has any of the options. Both `--name` and `--name=value` forms match.
     *
     * @param string        $command Command line
     * @param array<string> $names   Option names, with dashes
     *
     * @return bool
     */
    public static function has(string $command, array $names): bool
    {
        return array_any($names, fn(string $name): bool => \preg_match(self::pattern($name), $command) === 1);
    }

    /**
     * Check if the command line contains all the strings (case-insensitive).
     *
     * @param string $command    Command line
     * @param string ...$needles Strings to look for
     *
     * @return bool
     */
    public static function mentions(string $command, string ...$needles): bool
    {
        return array_all($needles, fn(string $needle): bool => \stripos($command, $needle) !== false);
    }

    /**
     * Remove the options from the command. Both `--name` and `--name=value` forms are removed.
     *
     * @param string        $command Command line
     * @param array<string> $names   Option names, with dashes
     *
     * @return string
     */
    public static function strip(string $command, array $names): string
    {
        foreach ($names as $name) {
            $command = \preg_replace(self::pattern($name), '', $command) ?? $command;
        }

        return \preg_replace('/\s{2,}/', ' ', \trim($command)) ?? $command;
    }

    /**
     * Set the value of the option, replacing any existing value.
     *
     * @param string $command Command line
     * @param string $name    Option name, with dashes
     * @param string $value   New value
     *
     * @return string
     */
    public static function setOption(string $command, string $name, string $value): string
    {
        return self::strip($command, [$name]).' '.$name.'='.$value;
    }

    /**
     * Add the flag, if the command does not have it (or any of its aliases) already.
     *
     * @param string        $command Command line
     * @param string        $name    Flag name, with dashes
     * @param array<string> $aliases Other names of the same flag
     *
     * @return string
     */
    public static function ensureFlag(string $command, string $name, array $aliases = []): string
    {
        return self::has($command, [$name, ...$aliases]) ? $command : $command.' '.$name;
    }

    /**
     * Program, that the command line starts with. Variable assignments in front of it are skipped, and quotes are removed.
     *
     * @param string $command Command line
     *
     * @return null|string Null if the program cannot be told without a shell (variables, substitutions, `@` references, operators right after the word)
     */
    public static function program(string $command): ?string
    {
        $command = \ltrim($command);
        while (\preg_match('/^[A-Za-z_]\w*=(?:\'[^\']*\'|"[^"]*"|\S*)\s+/', $command, $matches) === 1) {
            $command = \substr($command, \strlen($matches[0]));
        }

        foreach (['/^\'([^\']+)\'(?=\s|$)/', '/^"([^"$`\\\\]+)"(?=\s|$)/', '/^([^\s;&|<>(){}$`\\\\"\'*?\[~#@]+)(?=\s|$)/'] as $pattern) {
            if (\preg_match($pattern, $command, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Check if the program of the command can be run: it exists and has the permission to be executed.
     * The lookup is the same as the one of the process: the binaries directory first, then PATH. Not checked on Windows.
     *
     * @param string      $command      Command line
     * @param string      $project_root Project root, that relative paths are resolved against
     * @param null|string $bin_dir      Directory with binaries of Composer packages
     *
     * @return null|string Reason, why the program cannot be run. Null if it can, or if it cannot be checked.
     */
    public static function unrunnableReason(string $command, string $project_root, ?string $bin_dir): ?string
    {
        $program = self::program($command);
        if ($program === null || \PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        if (\str_contains($program, '/')) {
            $candidates = [\str_starts_with($program, '/') ? $program : \rtrim($project_root, '/').'/'.$program];
        } else {
            $directories = [...($bin_dir !== null ? [$bin_dir] : []), ...\explode(\PATH_SEPARATOR, (string) \getenv('PATH'))];
            $candidates = \array_map(
                static fn(string $directory): string => \rtrim($directory, '/').'/'.$program,
                \array_filter($directories, static fn(string $directory): bool => $directory !== ''),
            );
        }

        $not_executable = null;
        foreach ($candidates as $candidate) {
            if (!\is_file($candidate)) {
                continue;
            }

            if (\is_executable($candidate)) {
                return null;
            }

            $not_executable ??= $candidate;
        }

        return $not_executable === null ? "'$program' was not found" : "'$not_executable' is not executable";
    }

    /**
     * Regular expression for a whole-word option with optional `=value`.
     *
     * @param string $name Option name
     *
     * @return string
     */
    private static function pattern(string $name): string
    {
        return '/(?<![^\s])'.\preg_quote($name, '/').'(?:=\S*)?(?![^\s])/i';
    }
}
