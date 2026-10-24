<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Console;

use Simbiat\QARunner\Commands\Fix;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show what the fixers would change, without changing anything.
 * The fixers run on temporary copies of the files, one after another, and the command shows one combined diff for each file.
 */
#[AsCommand(name: 'diff', description: 'Show one combined diff of what the fixers would change (no file is changed)')]
final class DiffCommand extends AbstractQACommand
{
    /**
     * The fixers do the work, so the commands are the same as for `fix`.
     *
     * {@inheritDoc}
     */
    #[\Override]
    protected function configMode(): string
    {
        return 'fix';
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function requiredInterface(): string
    {
        return FixableToolAdapter::class;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function runTools(array $adapters, Paths $paths, ?float $timeout, InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string, \Simbiat\QARunner\Interfaces\FixableToolAdapter> $adapters */
        $fixer = new Fix($adapters, $timeout);
        $run = $fixer->run($paths, apply: false);

        $this->writeDiffs($output, $run->diffs);

        $failed = false;
        foreach ($run->results as $result) {
            if (!$result->success) {
                $failed = true;
                $this->error($output, $result->tool.': '.$result->output);
            }
        }

        foreach ($run->errors as $error) {
            $failed = true;
            $this->error($output, $error);
        }

        return match (true) {
            $failed => self::EXIT_ERROR,
            $run->diffs !== [] => self::FAILURE,
            default => self::SUCCESS,
        };
    }
}
