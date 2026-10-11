<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Interfaces;

use Simbiat\QARunner\Exceptions\ToolFailure;
use Simbiat\QARunner\Helpers\Paths;
use Symfony\Component\Process\Process;

/**
 * Extension to ToolAdapter to work with `diff` reports, if the tool supports the format.
 */
interface DiffableToolAdapter extends ToolAdapter
{
    /**
     * Method to prepare Diff command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param Paths $paths Paths to be scanned. Taken from CLI.
     *
     * @return Process
     * @throws \UnexpectedValueException If the command from composer.json is not a command of the tool
     */
    public function buildDiffProcess(Paths $paths): Process;

    /**
     * Parse the tool output.
     *
     * @param Process $process Symfony's Process instance, that has ended
     * @param Paths   $paths   Paths to be scanned. Taken from CLI.
     *
     * @return string
     * @throws ToolFailure If the tool did not run properly
     */
    public function parseDiff(Process $process, Paths $paths): string;
}
