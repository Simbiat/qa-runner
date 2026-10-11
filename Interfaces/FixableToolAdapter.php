<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Interfaces;

use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Helpers\Paths;
use Symfony\Component\Process\Process;

/**
 * Extension to ToolAdapter to work with fixes, if the tool can fix things.
 */
interface FixableToolAdapter extends ToolAdapter
{
    /**
     * Method to prepare fix command. Adapter appends whatever flags *it* needs for the fix, then the paths.
     *
     * @param Paths $paths Paths to be processed. Taken from CLI.
     *
     * @return Process
     * @throws \UnexpectedValueException If the command from composer.json is not a command of the tool
     */
    public function buildFixProcess(Paths $paths): Process;

    /**
     * Parse the tool output.
     *
     * @param Process $process Symfony's Process instance, that has ended
     * @param Paths   $paths   Paths to be processed. Taken from CLI.
     *
     * @return FixResult
     */
    public function parseFix(Process $process, Paths $paths): FixResult;
}
