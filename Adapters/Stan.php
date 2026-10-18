<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\Severity;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Paths;
use Symfony\Component\Process\Process;

/**
 * Adapter for PHPStan https://github.com/phpstan/phpstan.
 */
final readonly class Stan extends AbstractAdapter
{
    /**
     * 0 - no errors, 1 - errors found. Anything else is a failure of the tool.
     */
    protected const array OK_EXIT_CODES = [0, 1];

    /**
     * Display label used as Finding::tool.
     */
    public function name(): string
    {
        return 'PHPStan';
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
        if (\preg_match('/phpstan\s+analy[sz]e/i', $this->command) !== 1) {
            throw new \UnexpectedValueException('Not a PHPStan\'s analyse command');
        }
        // String --quiet flag, because it will hide the output
        $command = Cli::strip($this->command, ['--quiet', '-q']);

        // Enforce JSON format
        return $this->shell(Cli::setOption($command, '--error-format', 'json'), $this->full_scan ? null : $paths);
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
        $data = $this->decodeJson($output);
        $findings = [];
        foreach ($data['files'] ?? [] as $file => $file_data) {
            if (!$paths->covers((string) $file)) {
                continue;
            }
            foreach ($file_data['messages'] ?? [] as $message) {
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: $message['identifier'] ?? 'unknown',
                    file: (string) $file,
                    line: (int) ($message['line'] ?? 0),
                    message: $message['message'] ?? '',
                );
            }
        }
        // Errors, that are not tied to a file (for example, a bad configuration or an ignore pattern, that did not match). They are attached to the project root.
        foreach ($data['errors'] ?? [] as $error) {
            $findings[] = new Finding(
                tool: $this->name(),
                rule: 'general',
                file: $this->project_root,
                message: (string) $error,
                type: Severity::ERROR,
            );
        }

        return $findings;
    }
}
