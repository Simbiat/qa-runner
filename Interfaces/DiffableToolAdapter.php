<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Interfaces;

use Symfony\Component\Process\Process;

/**
 * Extension to ToolAdapter to work with `diff` reports, if the tool supports the format.
 */
interface DiffableToolAdapter extends ToolAdapter
{
    /**
     * Method to prepare Diff command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for Diff by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildDiffProcess(string $paths, string $base_command): Process;

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return string
     */
    public function parseDiff(Process $process, array $paths): string;
}
