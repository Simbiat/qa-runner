<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\MagoJson;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Process;

/**
 * Adapter for Mago Twig Lint https://github.com/carthage-software/mago
 */
final readonly class MagoTwigLint extends AbstractAdapter implements FixableToolAdapter
{
    /**
     * 0 - nothing found, 1 - code issues found
     */
    protected const array OK_EXIT_CODES = [0, 1];

    /**
     * Purpose of the tool.
     */
    protected const array TYPES = ['twig'];

    /**
     * Method to prepare Check command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param Paths $paths Paths to be scanned. Taken from CLI.
     *
     * @return Process
     *
     * @throws LogicException
     * @throws \UnexpectedValueException
     */
    #[\Override]
    public function buildCheckProcess(Paths $paths): Process
    {
        $this->assertMago();

        // Enforce JSON format and dry-run, and remove `--fix`
        $command = Cli::strip(Cli::ensureFlag(Cli::setOption($this->command, '--reporting-format', 'json'), '--dry-run'), ['--fix']);

        return $this->shell($command, $paths);
    }

    /**
     * Method to prepare fix command. Adapter appends whatever flags *it* needs for the fix, then the paths.
     *
     * @param Paths $paths Paths to be processed. Taken from CLI.
     *
     * @return Process
     *
     * @throws LogicException
     * @throws \UnexpectedValueException
     */
    #[\Override]
    public function buildFixProcess(Paths $paths): Process
    {
        $this->assertMago();

        // Ensure we have a fix mode
        $command = Cli::strip(Cli::ensureFlag($this->command, '--fix'), ['--dry-run']);

        return $this->shell($command, $paths);
    }

    /**
     * Display label used as Finding::tool.
     */
    #[\Override]
    public function name(): string
    {
        return 'Mago Twig Lint';
    }

    /**
     * Convert the tool output to findings.
     *
     * @param string $output Standard output of the tool
     * @param Paths  $paths  Paths to be scanned
     *
     * @return array<Finding>
     *
     * @throws \JsonException
     */
    #[\Override]
    protected function extractFindings(string $output, Paths $paths): array
    {
        $data = $this->decodeJson($output);

        return MagoJson::extractFindings($data, $paths, $this->name());
    }

    /**
     * Make sure the command is for Mago Twig Lint.
     *
     * @throws \UnexpectedValueException
     */
    private function assertMago(): void
    {
        if (!Cli::mentions($this->command, 'mago', 'twig:lint')) {
            throw new \UnexpectedValueException('Not a Mago Twig Lint command');
        }
    }
}
