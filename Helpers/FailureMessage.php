<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use Simbiat\QARunner\Exceptions\ToolFailure;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Texts for failures of tools. They always have the full command line, that was run.
 */
final class FailureMessage
{
    /**
     * The process ended, but its result was not accepted: the exit code is not an expected one, or the output cannot be parsed.
     *
     * @param Process    $process Process, that has ended
     * @param \Throwable $reason  What happened
     *
     * @return string
     */
    public static function forParse(Process $process, \Throwable $reason): string
    {
        return self::wrap($process, $reason instanceof ToolFailure ? $reason->getMessage() : 'Failed to parse the output: '.$reason->getMessage());
    }

    /**
     * The process did not start, did not end properly, or timed out.
     *
     * @param Process    $process Process, that was run
     * @param \Throwable $reason  What happened
     *
     * @return string
     */
    public static function forRun(Process $process, \Throwable $reason): string
    {
        $text = $reason instanceof ProcessTimedOutException
            ? 'Timed out after '.$reason->getExceededTimeout().' seconds'
            : $reason->getMessage();

        return self::wrap($process, $text);
    }

    /**
     * Add the command line.
     *
     * @param Process $process Process
     * @param string  $text    Reason
     *
     * @return string
     */
    private static function wrap(Process $process, string $text): string
    {
        return 'Command `'.$process->getCommandLine().'` failed: '.$text;
    }
}
