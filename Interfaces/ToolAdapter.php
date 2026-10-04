<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Interfaces;

use Symfony\Component\Process\Process;

/**
 * Interface for tools to allow plugging them into the Runner
 */
interface ToolAdapter
{
    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string;

    /**
     * Method to prepare Check command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for regular check by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildCheckProcess(string $paths, string $base_command): Process;

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return array<\Simbiat\QARunner\Entities\Finding>
     */
    public function parseCheck(Process $process, array $paths): array;
}
