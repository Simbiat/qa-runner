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
 * Adapter for PHP CodeSniffer https://github.com/PHPCSStandards/PHP_CodeSniffer (version 4.x).
 */
final readonly class CodeSniffer extends AbstractAdapter implements DiffableToolAdapter, FixableToolAdapter
{
    /**
     * phpcs: 0 - nothing found, 1 - everything found can be fixed, 2 - something found cannot be fixed, 3 - combination of 1 and 2, everything else most likely a runtime error.
     */
    protected const array OK_EXIT_CODES = [0, 1, 2, 3];

    /**
     * phpcbf: 0 - nothing to fix, 1 - everything fixable was fixed. 2 means something is not fixable, everything else is either fixer error/conflict or runtime error.
     */
    protected const array FIX_OK_EXIT_CODES = [0, 1, 2];

    /**
     * Display label used as Finding::tool.
     */
    public function name(): string
    {
        return 'PHP CodeSniffer';
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
        $this->assertPhpcs();

        // Enforce JSON format
        return $this->shell(Cli::setOption($this->command, '--report', 'json'), $paths);
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
        $this->assertPhpcs();

        // Enforce Diff format
        return $this->shell(Cli::setOption($this->command, '--report', 'diff'), $paths);
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
        if (!Cli::mentions($this->command, 'phpcbf')) {
            throw new \UnexpectedValueException('Not a PHPCBF command');
        }

        return $this->shell($this->command, $paths);
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
        foreach ($this->decodeJson($output)['files'] ?? [] as $file => $file_data) {
            foreach ($file_data['messages'] ?? [] as $message) {
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: $message['source'] ?? 'unknown',
                    file: (string) $file,
                    line: (int) ($message['line'] ?? 0),
                    column: (int) ($message['column'] ?? 0),
                    message: $message['message'] ?? '',
                    type: Severity::fromString($message['type'] ?? ''),
                    fixable: (bool) ($message['fixable'] ?? false),
                );
            }
        }

        return $findings;
    }

    /**
     * Make sure the command is for PHPCS.
     *
     * @throws \UnexpectedValueException
     */
    private function assertPhpcs(): void
    {
        if (!Cli::mentions($this->command, 'phpcs')) {
            throw new \UnexpectedValueException('Not a PHPCS command');
        }
    }
}
