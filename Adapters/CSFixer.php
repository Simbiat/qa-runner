<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Symfony\Component\Process\Process;

/**
 * Adapter for PHP CS Fixer https://github.com/PHP-CS-Fixer/PHP-CS-Fixer.
 */
final readonly class CSFixer extends AbstractAdapter implements FixableToolAdapter
{
    /**
     * 0 - nothing to change, 8 - changes needed (dry-run). Other codes are errors: 4 - invalid files, 16 - configuration error, 32 - exception in a fixer.
     */
    protected const array OK_EXIT_CODES = [0, 8];

    /**
     * Method to prepare Check command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param Paths $paths Paths to be scanned. Taken from CLI.
     *
     * @return Process
     */
    public function buildCheckProcess(Paths $paths): Process
    {
        $this->assertDryRun();

        // Enforce Gitlab format, since it is JSON and provide description of the rules
        $command = Cli::setOption($this->command, '--format', 'gitlab');
        // Force --diff, since this also adds lines values.
        // They seem to be wrong in most cases, and there were multiple issues and PRs related to this with no proper resolution, but maybe at some point it will be properly fixed.
        $command = Cli::ensureFlag($command, '--diff');
        // Force --verbose flag to get rules' names
        $command = Cli::ensureFlag($command, '--verbose', ['-v', '-vv', '-vvv']);
        // Remove --stop-on-violation if present, since the goal is to get the whole list
        $command = Cli::strip($command, ['--stop-on-violation']);

        return $this->shell(self::withDryRun($command), $paths);
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
        if (!Cli::mentions($this->command, 'php-cs-fixer fix')) {
            throw new \UnexpectedValueException('Not a CS Fixer fix command');
        }

        // Remove --dry-run flag
        return $this->shell(Cli::strip($this->command, ['--dry-run']), $paths);
    }

    /**
     * Display label used as Finding::tool.
     */
    public function name(): string
    {
        return 'PHP CS Fixer';
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
        foreach ($this->decodeJson($output) as $finding) {
            $findings[] = new Finding(
                tool: $this->name(),
                // Strip the prefix
                rule: \str_ireplace('PHP-CS-Fixer.', '', $finding['check_name'] ?? 'unknown'),
                file: $this->absolute($finding['location']['path'] ?? ''),
                line: (int) ($finding['location']['lines']['begin'] ?? 0),
                message: $finding['description'] ?? '',
                fixable: true,
            );
        }

        return $findings;
    }

    /**
     * Make sure the command is a dry-run one: either `check`, or `fix` with `--dry-run`.
     *
     * @throws \UnexpectedValueException
     */
    private function assertDryRun(): void
    {
        if (
            !Cli::mentions($this->command, 'php-cs-fixer check')
            && (
                !Cli::mentions($this->command, 'php-cs-fixer fix')
                || !Cli::has($this->command, ['--dry-run'])
            )
        ) {
            throw new \UnexpectedValueException('Not a CS Fixer dry-run command');
        }
    }

    /**
     * Force dry-run for the `fix` command. The `check` command is dry-run by definition.
     *
     * @param string $command Command line
     *
     * @return string
     */
    private static function withDryRun(string $command): string
    {
        return Cli::mentions($command, 'php-cs-fixer fix')
            ? Cli::ensureFlag($command, '--dry-run')
            : $command;
    }
}
