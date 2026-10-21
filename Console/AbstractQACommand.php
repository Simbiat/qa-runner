<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Console;

use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Config;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Code, that the commands share: common arguments and options, validation, creation of the adapters.
 *
 * Exit codes: 0 - nothing found, 1 - findings (or diffs, or failed fixes), 2 - wrong usage or a tool, that did not run properly.
 */
abstract class AbstractQACommand extends Command
{
    /**
     * Exit code for wrong usage or a tool, that did not run properly. Exit code 1 means findings.
     */
    public const int EXIT_ERROR = self::INVALID;

    /**
     * Timeout for each tool in seconds, if `--timeout` is not provided.
     */
    private const string DEFAULT_TIMEOUT = '600';

    /**
     * Interface, that an adapter must implement to be used by the command.
     *
     * @return class-string<\Simbiat\QARunner\Interfaces\ToolAdapter>
     */
    abstract protected function requiredInterface(): string;

    /**
     * Run the tools.
     *
     * @param array<string, \Simbiat\QARunner\Interfaces\ToolAdapter> $adapters Adapters, that support the command
     * @param Paths                                                   $paths    Paths to process
     * @param null|float                                              $timeout  Timeout for each tool in seconds. Null means no timeout.
     * @param InputInterface                                          $input    Input
     * @param OutputInterface                                         $output   Output
     *
     * @return int Exit code
     */
    abstract protected function runTools(array $adapters, Paths $paths, ?float $timeout, InputInterface $input, OutputInterface $output): int;

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Files or directories to process')
            ->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Project directory with composer.json (default: current directory)')
            ->addOption('timeout', 't', InputOption::VALUE_REQUIRED, 'Timeout for each tool in seconds, 0 means no timeout', self::DEFAULT_TIMEOUT);
    }

    /**
     * @inheritDoc
     *
     * @throws \ReflectionException
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectDirectory($input);
        $timeout = $this->timeout($input);

        try {
            $paths = Paths::fromCli($input->getArgument('paths'), \getcwd());
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        try {
            $config = Config::fromProjectRoot($project);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Failed to parse `composer.json`: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        $adapters = $this->createAdapters($config, $project, $input, $output);
        if ($adapters === []) {
            $this->error($output, 'No adapters can run in `'.$this->getName().'` mode. Check the settings in `composer.json`.');

            return self::EXIT_ERROR;
        }

        return $this->runTools($adapters, $paths, $timeout, $input, $output);
    }

    /**
     * Output for errors and warnings: STDERR, if the output has it.
     *
     * @param OutputInterface $output Output
     *
     * @return OutputInterface
     */
    protected function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }

    /**
     * Write a warning to the error output.
     *
     * @param OutputInterface $output  Output
     * @param string          $message Message
     */
    protected function warn(OutputInterface $output, string $message): void
    {
        $this->errorOutput($output)->writeln('<comment>qa-runner: '.OutputFormatter::escape($message).'</comment>');
    }

    /**
     * Write an error to the error output.
     *
     * @param OutputInterface $output  Output
     * @param string          $message Message
     */
    protected function error(OutputInterface $output, string $message): void
    {
        $this->errorOutput($output)->writeln('<error>qa-runner: '.OutputFormatter::escape($message).'</error>');
    }

    /**
     * Project directory from `--project`, or the current directory.
     *
     * @param InputInterface $input Input
     *
     * @return string
     */
    private function projectDirectory(InputInterface $input): string
    {
        $raw = $input->getOption('project') ?? \getcwd();
        $project = \is_string($raw) ? \realpath($raw) : false;
        if ($project === false || !\is_dir($project)) {
            throw new InvalidOptionException('Not a readable project directory.');
        }

        return $project;
    }

    /**
     * Timeout from `--timeout`.
     *
     * @param InputInterface $input Input
     *
     * @return null|float Seconds, or null for no timeout
     */
    private function timeout(InputInterface $input): ?float
    {
        $raw = $input->getOption('timeout');
        if (!\is_numeric($raw) || (float) $raw < 0) {
            throw new InvalidOptionException('Option `--timeout` needs a number of seconds, 0 or more.');
        }

        return (float) $raw > 0 ? (float) $raw : null;
    }

    /**
     * Create the adapters, that are configured for the command and support it. Adapters, whose program does not exist or is not executable, are skipped with a warning.
     *
     * @param Config          $config  Config from composer.json
     * @param string          $project Project root
     * @param InputInterface  $input   Input
     * @param OutputInterface $output  Output
     *
     * @return array<string, ToolAdapter>
     *
     * @throws \ReflectionException
     */
    private function createAdapters(Config $config, string $project, InputInterface $input, OutputInterface $output): array
    {
        $full_scan = !$input->hasOption('no-full-scan') || !$input->getOption('no-full-scan');
        $bin_dir = $config->binDir();
        $required = $this->requiredInterface();

        $adapters = [];
        foreach ($config->commandsFor((string) $this->getName()) as $class => $command) {
            if (!\is_a($class, ToolAdapter::class, allow_string: true) || !new \ReflectionClass($class)->isInstantiable()) {
                $this->warn($output, "'$class' is not a known ToolAdapter, skipping.");

                continue;
            }

            if (!\is_a($class, $required, allow_string: true)) {
                $this->warn($output, "'$class' does not support '".$this->getName()."', skipping.");

                continue;
            }

            $reason = Cli::unrunnableReason($command, $project, $bin_dir);
            if ($reason !== null) {
                $this->warn($output, "'$class' skipped, because $reason. Command: `$command`");

                continue;
            }

            /* @var class-string<ToolAdapter> $class -- False positive from EA Inspections, despite the proper hinting in the method */
            $adapters[$class] = new $class($project, $command, $full_scan, $bin_dir);
        }

        return $adapters;
    }
}
