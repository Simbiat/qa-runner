<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Helpers\PsalmTaint;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Process;

/**
 * Adapter for Psalm https://github.com/vimeo/psalm/.
 */
final readonly class Psalm extends AbstractAdapter implements FixableToolAdapter
{
    /**
     * 0 - no issues, 2 - issues found. 1 is a failure of the tool (for example, bad configuration).
     */
    protected const array OK_EXIT_CODES = [0, 2];

    /**
     * Fixable errors' types, based on setIssuesToFix() method from Psalm's ProjectAnalyzer.php.
     */
    private const array FIXABLE_ERRORS = [
        'MissingReturnType',
        'MissingClosureReturnType',
        'InvalidReturnType',
        'InvalidNullableReturnType',
        'InvalidFalsableReturnType',
        'MissingOverrideAttribute',
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
        'ClassMustBeFinal',
        'RedundantCast',
        'RedundantCastGivenDocblockType',
        'MissingPureAnnotation',
        'MissingThrowsDocblock',
        'MissingImmutableAnnotation',
    ];

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
        if (!Cli::mentions($this->command, 'psalm')) {
            throw new \UnexpectedValueException('Not a Psalm command');
        }

        // Enforce JSON format. Remove --alter, since it would modify the files. --taint-analysis stays: taint issues are in the same JSON and are split by PsalmTaint
        $command = Cli::setOption(Cli::strip($this->command, ['--alter']), '--output-format', 'json');

        return $this->shell($command, $this->full_scan ? null : $paths);
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
        $this->assertPsalter();

        // Remove --dry-run flag and add issues list, if not provided
        $command = Cli::strip($this->command, ['--dry-run']);
        $command = Cli::has($command, ['--issues'])
            ? $command
            : $command.' --issues=all';

        return $this->shell($command, $paths);
    }

    /**
     * Display label used as Finding::tool.
     */
    #[\Override]
    public function name(): string
    {
        return 'Psalm';
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
    #[\Override]
    protected function extractFindings(string $output, Paths $paths): array
    {
        // Taint issues are split to separate issues (see PsalmTaint). Paths are checked for each of them, and identical ones are removed
        $issues = [];
        $seen = [];
        foreach ($this->decodeJson($output) as $issue) {
            foreach (PsalmTaint::unpack($issue) as $item) {
                if (!$paths->covers((string) ($item['file_path'] ?? ''))) {
                    continue;
                }

                $key = \md5(\serialize($item));
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $issues[] = $item;
            }
        }

        $findings = [];
        foreach ($issues as $issue) {
            if (\array_key_exists('is_security', $issue) && $issue['is_security']) {
                $issue['severity'] = 'SECURITY';
            }

            $findings[] = new Finding(
                tool: $this->name(),
                rule: $issue['type'] ?? 'unknown',
                file: (string) ($issue['file_path'] ?? ''),
                line: (int) ($issue['line_from'] ?? 0),
                column: (int) ($issue['column_from'] ?? 0),
                message: $issue['message'] ?? '',
                type: (string) ($issue['severity'] ?? ''),
                fixable: \in_array($issue['type'] ?? 'unknown', self::FIXABLE_ERRORS, strict: true),
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
        if (
            !Cli::mentions($this->command, 'psalter')
            && (
                !Cli::mentions($this->command, 'psalm')
                || !Cli::has($this->command, ['--alter'])
            )
        ) {
            throw new \UnexpectedValueException('Not a Psalter command');
        }
    }
}
