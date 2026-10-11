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
 */
#[AsCommand(name: 'fix', description: 'Apply fixes with the tools, that can fix things (changes files!)')]
final class FixCommand extends AbstractQACommand
{
    /**
     * @inheritDoc
     */
    #[\Override]
    protected function requiredInterface(): string
    {
        return FixableToolAdapter::class;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function runTools(array $adapters, Paths $paths, ?float $timeout, InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string, FixableToolAdapter> $adapters */
        $results = new Fix($adapters, $timeout)->run($paths);

        $failed = false;
        foreach ($results as $iteration => $result) {
            // Raw output: the tools can print `<` and `>` and their own ANSI colors, which must stay as they are
            $output->writeln(
                ($iteration > 0 ? "\n" : '')."========= Start of $result->tool output =========\n\n"
                .($result->output !== '' ? $result->output."\n\n" : '')
                ."========= End of $result->tool output =========",
                OutputInterface::OUTPUT_RAW,
            );
            $failed = $failed || !$result->success;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
