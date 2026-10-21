<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use ReflectionClass;
use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Adapter for Rector https://github.com/rectorphp/rector.
 */
final readonly class Rector extends AbstractAdapter implements DiffableToolAdapter, FixableToolAdapter
{
    /**
     * 0 - nothing to change, 2 - changes needed (dry-run). 1 is a failure of the tool.
     */
    protected const array OK_EXIT_CODES = [0, 2];

    /**
     * Display label used as Finding::tool.
     */
    public function name(): string
    {
        return 'Rector';
    }

    /**
     * Method to prepare Check command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param Paths $paths Paths to be scanned. Taken from CLI.
     *
     * @return Process
     */
    public function buildCheckProcess(Paths $paths): Process
    {
        $this->assertRector();

        // Enforce JSON format and dry-run
        $command = Cli::ensureFlag(Cli::setOption($this->command, '--output-format', 'json'), '--dry-run');

        return $this->shell($command, $paths);
    }

    /**
     * Method to prepare Diff command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param Paths $paths Paths to be scanned. Taken from CLI.
     *
     * @return Process
     */
    public function buildDiffProcess(Paths $paths): Process
    {
        $this->assertRector();

        // Enforce console format with diffs and force dry-run
        $command = Cli::setOption(Cli::strip($this->command, ['--no-diffs']), '--output-format', 'console');

        return $this->shell(Cli::ensureFlag($command, '--dry-run'), $paths);
    }

    /**
     * Method to prepare fix command. Adapter appends whatever flags *it* needs for the fix, then the paths.
     *
     * @param Paths $paths Paths to be processed. Taken from CLI.
     *
     * @return Process
     */
    public function buildFixProcess(Paths $paths): Process
    {
        $this->assertRector();

        // Enforce console format with diffs and remove dry-run
        $command = Cli::setOption(Cli::strip($this->command, ['--no-diffs', '--dry-run']), '--output-format', 'console');

        return $this->shell($command, $paths);
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
    protected function extractFindings(string $output, Paths $paths): array
    {
        $findings = [];
        foreach ($this->decodeJson($output)['file_diffs'] ?? [] as $file_data) {
            $file = $this->absolute($file_data['file'] ?? '');
            foreach ($file_data['changes'] ?? [] as $finding) {
                $rector = (string) ($finding['rector'] ?? 'unknown');

                try {
                    $description = new ReflectionClass($rector)->newInstanceWithoutConstructor()->getRuleDefinition()->getDescription();
                } catch (\Throwable) {
                    $description = '';
                }

                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: self::shortName($rector),
                    file: $file,
                    line: (int) ($finding['line'] ?? 0),
                    message: $description,
                    // Rector has only fixable issues by design
                    fixable: true,
                );
            }
        }

        return $findings;
    }

    /**
     * Make sure the command is for Rector.
     *
     * @throws \UnexpectedValueException
     */
    private function assertRector(): void
    {
        if (!Cli::mentions($this->command, 'rector')) {
            throw new \UnexpectedValueException('Not a Rector command');
        }
    }
}
