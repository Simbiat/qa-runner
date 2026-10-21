<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Console;

use Simbiat\QARunner\Commands\Diff;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show what the fixers would change, without changing anything.
 */
#[AsCommand(name: 'diff', description: 'Show dry-run diffs from the tools, that support them')]
final class DiffCommand extends AbstractQACommand
{
    /**
     * @inheritDoc
     */
    #[\Override]
    protected function requiredInterface(): string
    {
        return DiffableToolAdapter::class;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function runTools(array $adapters, Paths $paths, ?float $timeout, InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string, DiffableToolAdapter> $adapters */
        $result = new Diff($adapters, $timeout)->run($paths);

        foreach ($result->items as $tool => $diff) {
            // Raw output: diffs can contain `<` and `>`, which must not be read as formatting tags
            $output->writeln("========= Start of $tool output =========\n\n$diff\n\n========= End of $tool output =========", OutputInterface::OUTPUT_RAW);
        }

        foreach ($result->failures as $tool => $message) {
            $this->error($output, "$tool: $message");
        }

        return match (true) {
            $result->failures !== [] => self::EXIT_ERROR,
            $result->items !== [] => self::FAILURE,
            default => self::SUCCESS,
        };
    }
}
