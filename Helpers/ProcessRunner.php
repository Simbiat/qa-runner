<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use Symfony\Component\Process\Process;

/**
 * Runs several processes in parallel.
 */
final class ProcessRunner
{
    /**
     * Start all the processes and wait until all of them end or time out.
     * The loop reads the output of every process all the time. If only one process was waited for, the others could stop on a full pipe buffer.
     *
     * @param array<string, Process> $processes Processes to run
     * @param float|null             $timeout   Timeout in seconds for each process. Null means no timeout.
     *
     * @return array<string, \Throwable> Errors (for example, timeouts) by the keys of the processes, that did not end properly
     */
    public static function runAll(array $processes, ?float $timeout): array
    {
        $errors = [];
        foreach ($processes as $key => $process) {
            try {
                $process->setTimeout($timeout);
                $process->start();
            } catch (\Throwable $exception) {
                $errors[$key] = $exception;
                unset($processes[$key]);
            }
        }

        while ($processes !== []) {
            foreach ($processes as $key => $process) {
                try {
                    if ($process->isRunning()) {
                        $process->checkTimeout();
                        continue;
                    }
                } catch (\Throwable $exception) {
                    $errors[$key] = $exception;
                    $process->stop(0);
                }
                unset($processes[$key]);
            }
            if ($processes !== []) {
                \usleep(10_000);
            }
        }

        return $errors;
    }
}
