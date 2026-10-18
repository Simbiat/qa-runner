<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Console;

use Simbiat\QARunner\Commands\Check;
use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\Severity;
use Simbiat\QARunner\Formatters\Console;
use Simbiat\QARunner\Formatters\CSV;
use Simbiat\QARunner\Formatters\IDE;
use Simbiat\QARunner\Formatters\TSV;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Helpers\Sorter;
use Simbiat\QARunner\Interfaces\OutputFormatter;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run all configured tools and combine their findings.
 */
#[AsCommand(name: 'check', description: 'Run all configured tools and combine their findings into one report')]
final class CheckCommand extends AbstractQACommand
{
    /**
     * Available output formats.
     */
    private const array FORMATS = ['ide', 'console'];

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: '.\implode(' or ', self::FORMATS), 'ide', self::FORMATS)
            ->addOption('by-type', null, InputOption::VALUE_NONE, 'Group findings by tool, type and rule instead of by file')
            ->addOption('no-full-scan', null, InputOption::VALUE_NONE, 'Let tools like PHPStan and Psalm scan only the given paths instead of the full project');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function requiredInterface(): string
    {
        return ToolAdapter::class;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function runTools(array $adapters, Paths $paths, ?float $timeout, InputInterface $input, OutputInterface $output): int
    {
        $by_type = (bool) $input->getOption('by-type');
        $formatter = $this->formatter((string) $input->getOption('format'), $output->isDecorated(), $by_type);

        $result = new Check($adapters, $timeout)->run($paths);

        // Failures are shown among the findings, since the IDE shows only the output, that has file and line in it
        $findings = $result->items;
        foreach ($result->failures as $tool => $message) {
            $findings[] = new Finding(tool: $tool, rule: 'qa-runner-failure', file: $paths->first(), message: $message, type: Severity::ERROR);
        }
        $findings = $by_type ? Sorter::sortByType($findings) : Sorter::sortByFile($findings);

        $text = $formatter->format($findings);
        if ($text !== '') {
            // Raw output: the text can contain `<` and `>` (for example, in messages), which must not be read as formatting tags
            $output->writeln($text, OutputInterface::OUTPUT_RAW);
        }

        return match (true) {
            $result->failures !== [] => self::EXIT_ERROR,
            $result->items !== [] => self::FAILURE,
            default => self::SUCCESS,
        };
    }

    /**
     * Create the formatter by its name.
     *
     * @param string $name    Formatter name
     * @param bool   $colors  Whether the output supports colors
     * @param bool   $by_type Whether findings are grouped by tool, type and rule
     *
     * @return OutputFormatter
     */
    private function formatter(string $name, bool $colors, bool $by_type): OutputFormatter
    {
        return match ($name) {
            'ide' => new IDE(),
            'csv' => new CSV(),
            'tsv' => new TSV(),
            'console' => new Console($colors, $by_type),
            default => throw new InvalidOptionException("Unknown format '$name'. Available: ".\implode(', ', self::FORMATS)),
        };
    }
}
