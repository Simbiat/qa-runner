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
 * Apply the fixes of the tools, that can fix things.
 * The fixers run on temporary copies of the files, one after another. Then each changed file is replaced with its fixed copy.
 */
#[AsCommand(name: 'fix', description: 'Apply fixes with the tools, that can fix things (changes files!)')]
final class FixCommand extends AbstractQACommand
{
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
        $run = new Fix($adapters, $timeout)->run($paths, apply: true);

        $failed = false;
        foreach ($run->results as $iteration => $result) {
            // Raw output: the tools can print `<` and `>` and their own ANSI colors, which must stay as they are
            $output->writeln(
                ($iteration > 0 ? "\n" : '')."========= Start of {$result->tool} output =========\n\n"
                .($result->output !== '' ? $result->output."\n\n" : '')
                ."========= End of {$result->tool} output =========",
                OutputInterface::OUTPUT_RAW,
            );
            $failed = $failed || !$result->success;
        }

        foreach ($run->errors as $error) {
            $this->error($output, $error);
        }

        return match (true) {
            $run->errors !== [] => self::EXIT_ERROR,
            $failed => self::FAILURE,
            default => self::SUCCESS,
        };
    }
}
