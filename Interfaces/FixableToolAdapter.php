<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Interfaces;

use Simbiat\QARunner\Entities\FixResult;
use Symfony\Component\Process\Process;

/**
 * Extension to ToolAdapter to work with fixes, if the tool can fix things.
 */
interface FixableToolAdapter extends ToolAdapter
{
    /**
     * Method to prepare fix command. Adapter appends whatever flags *it* needs for the fix, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for Diff by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildFixProcess(string $paths, string $base_command): Process;

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return \Simbiat\QARunner\Entities\FixResult
     */
    public function parseFix(Process $process, array $paths): FixResult;
}
