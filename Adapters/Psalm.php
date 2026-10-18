<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\Severity;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Adapter for Psalm https://github.com/vimeo/psalm/.
 */
final readonly class Psalm extends AbstractAdapter implements DiffableToolAdapter, FixableToolAdapter
{
    /**
     * 0 - no issues, 2 - issues found. 1 is a failure of the tool (for example, bad configuration).
     */
    protected const array OK_EXIT_CODES = [0, 2];

    /**
     * Fixable errors' types, taken from https://psalm.dev/docs/manipulating_code/fixing/.
     */
    private const array FIXABLE_ERRORS = [
        'MissingReturnType',
        'MissingClosureReturnType',
        'InvalidReturnType',
        'InvalidNullableReturnType',
        'InvalidFalsableReturnType',
        'MissingParamType',
        'MissingPropertyType',
        'MismatchingDocblockParamType',
        'MismatchingDocblockReturnType',
        'LessSpecificReturnType',
        'PossiblyUndefinedVariable',
        'PossiblyUndefinedGlobalVariable',
        'UnusedMethod',
        'PossiblyUnusedMethod',
        'UnusedProperty',
        'PossiblyUnusedProperty',
        'UnusedVariable',
        'UnnecessaryVarAnnotation',
        'ParamNameMismatch',
    ];

    /**
     * Display label used as Finding::tool.
     */
    public function name(): string
    {
        return 'Psalm';
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
        if (!Cli::mentions($this->command, 'psalm')) {
            throw new \UnexpectedValueException('Not a Psalm command');
        }

        // Enforce JSON format. Remove --taint-analysis flag, since it results in different kind of output, and --alter since it would modify the file
        $command = Cli::setOption(Cli::strip($this->command, ['--taint-analysis', '--alter']), '--output-format', 'json');

        return $this->shell($command, $this->full_scan ? null : $paths);
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
        $this->assertPsalter();

        // Force dry-run and add issues list, if not provided
        $command = Cli::ensureFlag($this->command, '--dry-run');
        $command = Cli::has($command, ['--issues']) ? $command : $command.' --issues=all';

        return $this->shell($command, $paths);
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
        $this->assertPsalter();

        // Remove --dry-run flag and add issues list, if not provided
        $command = Cli::strip($this->command, ['--dry-run']);
        $command = Cli::has($command, ['--issues']) ? $command : $command.' --issues=all';

        return $this->shell($command, $paths);
    }

    /**
     * Convert the tool output to findings. Only the findings in the requested paths are kept.
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
        foreach ($this->decodeJson($output) as $finding) {
            $file = (string) ($finding['file_path'] ?? '');
            if (!$paths->covers($file)) {
                continue;
            }
            $findings[] = new Finding(
                tool: $this->name(),
                rule: $finding['type'] ?? 'unknown',
                file: $file,
                line: (int) ($finding['line_from'] ?? 0),
                column: (int) ($finding['column_from'] ?? 0),
                message: $finding['message'] ?? '',
                type: Severity::fromString($finding['severity'] ?? ''),
                fixable: \in_array($finding['type'] ?? 'unknown', self::FIXABLE_ERRORS, true),
            );
        }

        return $findings;
    }

    /**
     * Make sure the command is a Psalter one: either `psalter`, or `psalm --alter`.
     *
     * @throws \UnexpectedValueException
     */
    private function assertPsalter(): void
    {
        if (!Cli::mentions($this->command, 'psalter') && !(Cli::mentions($this->command, 'psalm') && Cli::has($this->command, ['--alter']))) {
            throw new \UnexpectedValueException('Not a Psalter command');
        }
    }
}
