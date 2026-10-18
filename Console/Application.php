<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Entry point of qa-runner: the Symfony Console application with the `check`, `diff` and `fix` commands.
 */
final class Application extends SymfonyApplication
{
    /**
     * Class constructor.
     *
     * @throws \Symfony\Component\Console\Exception\LogicException
     */
    public function __construct()
    {
        parent::__construct('qa-runner');

        $this->addCommands([new CheckCommand(), new DiffCommand(), new FixCommand()]);
        // The bin file returns the exit code itself
        $this->setAutoExit(false);
    }

    /**
     * Symfony Console exits with code 1 on any error, but 1 means "findings exist" for qa-runner. So errors (wrong options, unknown command, unexpected exceptions) exit with 2 instead.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @return int
     */
    #[\Override]
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::doRun($input, $output);
        } catch (\Throwable $throwable) {
            $this->renderThrowable($throwable, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

            return AbstractQACommand::EXIT_ERROR;
        }
    }
}
