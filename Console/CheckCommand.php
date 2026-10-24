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
    private const array FORMATS = ['ide', 'console', 'csv', 'tsv'];

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: '.\implode(' or ', self::FORMATS), 'ide', self::FORMATS)
            ->addOption('by-type', mode: InputOption::VALUE_NONE, description: 'Group findings by tool, type and rule instead of by file')
            ->addOption('fixable', mode: InputOption::VALUE_NONE, description: 'Show only the findings, that the tools can fix. Failures of the tools are still shown')
            ->addOption('count', mode: InputOption::VALUE_NONE, description: 'Add a `Count` column with the number of findings of the same rule (only for `csv` and `tsv` formats)')
            ->addOption('by-count', mode: InputOption::VALUE_NONE, description: 'Sort findings by `Count`, from the highest to the lowest. Implies `--count`')
            ->addOption('no-full-scan', mode: InputOption::VALUE_NONE, description: 'Let tools like PHPStan and Psalm scan only the given paths instead of the full project');
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function requiredInterface(): string
    {
        return ToolAdapter::class;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function runTools(array $adapters, Paths $paths, ?float $timeout, InputInterface $input, OutputInterface $output): int
    {
        $by_type = (bool) $input->getOption('by-type');
        $format = (string) $input->getOption('format');
        $by_count = (bool) $input->getOption('by-count');
        if ($by_count) {
            $count = true;
        } else {
            $count = (bool) $input->getOption('count');
        }
        if ($count && !\in_array($format, ['csv', 'tsv'], strict: true)) {
            $this->warn($output, '`--count` works only with `csv` and `tsv` formats, so it is ignored.');
            $count = false;
        }

        $formatter = self::formatter($format, $output->isDecorated(), $by_type, $count);

        $result = new Check($adapters, $timeout)->run($paths);

        // Failures are shown among the findings, since the IDE shows only the output, that has file and line in it
        $items = (bool) $input->getOption('fixable') ? \array_values(\array_filter($result->items, static fn(Finding $finding): bool => $finding->fixable)) : $result->items;
        $findings = $items;
        foreach ($result->failures as $tool => $message) {
            $findings[] = new Finding(tool: $tool, rule: 'qa-runner-failure', file: $paths->first(), message: $message, type: Severity::ERROR);
        }

        $findings = match (true) {
            $by_count => Sorter::sortByCount($findings),
            $by_type => Sorter::sortByType($findings),
            default => Sorter::sortByFile($findings),
        };

        $text = $formatter->format($findings);
        if ($text !== '') {
            // Raw output: the text can contain `<` and `>` (for example, in messages), which must not be read as formatting tags
            $output->writeln($text, OutputInterface::OUTPUT_RAW);
        }

        return match (true) {
            $result->failures !== [] => self::EXIT_ERROR,
            $items !== [] => self::FAILURE,
            default => self::SUCCESS,
        };
    }

    /**
     * Create the formatter by its name.
     *
     * @param string $name    Formatter name
     * @param bool   $colors  Whether the output supports colors
     * @param bool   $by_type Whether findings are grouped by tool, type and rule
     * @param bool   $count   Whether `csv` and `tsv` formats have the `Count` column
     *
     * @return OutputFormatter
     */
    private static function formatter(string $name, bool $colors, bool $by_type, bool $count): OutputFormatter
    {
        return match ($name) {
            'ide' => new IDE(),
            'csv' => new CSV($count),
            'tsv' => new TSV($count),
            'console' => new Console($colors, $by_type),
            default => throw new InvalidOptionException("Unknown format '{$name}'. Available: ".\implode(', ', self::FORMATS)),
        };
    }
}
