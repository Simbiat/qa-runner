<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Interfaces;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Exceptions\ToolFailure;
use Simbiat\QARunner\Helpers\Paths;
use Symfony\Component\Process\Process;

/**
 * Interface for tools to allow plugging them into the Runner.
 * The runner creates adapters as `new Adapter(string $project_root, string $command, bool $full_scan, ?string $bin_dir)`. Extend `AbstractAdapter` to get this constructor.
 */
interface ToolAdapter
{
    /**
     * Display label used as Finding::tool, e.g. 'PHP CodeSniffer'.
     */
    public function name(): string;

    /**
     * Method to prepare Check command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param Paths $paths Paths to be scanned. Taken from CLI.
     *
     * @return Process
     *
     * @throws \UnexpectedValueException If the command from composer.json is not a command of the tool
     */
    public function buildCheckProcess(Paths $paths): Process;

    /**
     * Parse the tool output.
     *
     * @param Process $process Symfony's Process instance, that has ended
     * @param Paths   $paths   Paths to be scanned. Taken from CLI.
     *
     * @return array<Finding>
     *
     * @throws ToolFailure    If the tool did not run properly
     */
    public function parseCheck(Process $process, Paths $paths): array;
}
