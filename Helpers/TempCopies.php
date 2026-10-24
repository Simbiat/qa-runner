<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Temporary copies of files. The fixers work on the copies, so the original files stay as they are until the end of the run.
 *
 * Each copy is in the same directory as its original. A copy outside the project would not match the filters of the tools (finders, `exclude-pattern`, `--path-mode=intersection`).
 * The name of a copy has a random part before the extension: `Foo.php` becomes `Foo.qa-runner-1a2b3c4d.php`. The name does not start with a dot, because Symfony Finder skips such files by default.
 */
final class TempCopies
{
    /**
     * Marker in the names of the copies.
     */
    private const string MARKER = '.qa-runner-';

    /**
     * @param array<string, array{temp: string, hash: string}> $copies Original path => path of the copy and hash of the original content at the time of the copy
     */
    private function __construct(
        private array $copies
    ) {}

    /**
     * Write the changed copies over their originals. An original is not touched if it changed on the disk after the copy was made (for example, you saved it in the IDE during the run).
     *
     * @return list<string> Errors. An empty list means that all changed copies were written.
     */
    public function apply(): array
    {
        $errors = [];
        foreach ($this->changed() as $original) {
            $current = \md5_file($original);
            if ($current === false || $current !== $this->copies[$original]['hash']) {
                $errors[] = "'{$original}' changed during the run, so the fixes for it were not applied.";

                continue;
            }

            $content = \file_get_contents($this->copies[$original]['temp']);
            // Writing to the existing file keeps its owner, permissions and inode
            if ($content === false || \file_put_contents($original, $content) === false) {
                $errors[] = "Failed to write the fixed content to '{$original}'.";
            }
        }

        return $errors;
    }

    /**
     * Files, whose copies are different now.
     *
     * @return list<string> Original paths
     */
    public function changed(): array
    {
        $changed = [];
        foreach ($this->copies as $original => $copy) {
            if (\is_file($copy['temp']) && \md5_file($copy['temp']) !== $copy['hash']) {
                $changed[] = $original;
            }
        }

        return $changed;
    }

    /**
     * Remove all the copies.
     */
    public function cleanup(): void
    {
        foreach ($this->copies as $copy) {
            if (\is_file($copy['temp'])) {
                \unlink($copy['temp']);
            }
        }
    }

    /**
     * Check if there are no copies.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->copies === [];
    }

    /**
     * Paths of the copies, for the tools.
     *
     * @return \Simbiat\QARunner\Helpers\Paths
     *
     * @throws \InvalidArgumentException
     */
    public function paths(): Paths
    {
        return Paths::fromCli(\array_column($this->copies, 'temp'), '/');
    }

    /**
     * Path of the copy of a file.
     *
     * @param string $original Original path
     *
     * @return string
     */
    public function tempFor(string $original): string
    {
        return $this->copies[$original]['temp'];
    }

    /**
     * Replace the names of the copies with the names of the originals in a text (for example, in the output of a tool).
     *
     * @param string $text Text
     *
     * @return string
     */
    public function restoreNames(string $text): string
    {
        $map = [];
        foreach ($this->copies as $original => $copy) {
            $map[$copy['temp']] = $original;
            $map[\basename($copy['temp'])] = \basename($original);
        }

        return $map === [] ? $text : \strtr($text, $map);
    }

    /**
     * Make copies of the files in the paths. Directories are not supported: a directory has no file list without a filter, and the filters belong to the tools.
     *
     * @param \Simbiat\QARunner\Helpers\Paths $paths Paths from CLI
     *
     * @return self
     *
     * @throws \RuntimeException If a path is a directory, or a copy cannot be made. The copies, that were made before it, are removed.
     * @throws \Throwable
     */
    public static function create(Paths $paths): self
    {
        foreach ($paths->absolute as $path) {
            if (\is_dir($path)) {
                throw new \RuntimeException("'{$path}' is a directory. The `diff` and `fix` commands accept only files.");
            }
        }

        $instance = new self([]);

        try {
            foreach ($paths->absolute as $file) {
                $content = \file_get_contents($file);
                $temp = self::tempName($file);
                if ($content === false || \file_put_contents($temp, $content) === false) {
                    throw new \RuntimeException("Failed to make a temporary copy of '{$file}'. Check that the directory is writable.");
                }

                $instance->copies[$file] = ['temp' => $temp, 'hash' => \md5($content)];
            }
        } catch (\Throwable $throwable) {
            $instance->cleanup();

            throw $throwable instanceof \RuntimeException ? $throwable : new \RuntimeException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        return $instance;
    }

    /**
     * Name of the copy: a random part is added before the last extension.
     *
     * @param string $file Original path
     *
     * @return string
     *
     * @throws \Random\RandomException
     */
    private static function tempName(string $file): string
    {
        $directory = \dirname($file);
        $name = \basename($file);
        $dot = \strrpos($name, '.');
        $token = self::MARKER.\bin2hex(\random_bytes(4));

        return $directory.'/'.($dot === false ? $name.$token : \substr($name, 0, $dot).$token.\substr($name, $dot));
    }
}
