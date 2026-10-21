<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\Severity;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Paths;
use Symfony\Component\Process\Process;

/**
 * Adapter for PHP Mess Detector https://github.com/phpmd/phpmd.
 */
final readonly class MessDetector extends AbstractAdapter
{
    /**
     * 0 - nothing found, 2 - violations found. 1 is an exception inside the tool. 3 can be ambiguous, because it can be mean that one of the files was not processed properly.
     */
    protected const array OK_EXIT_CODES = [0, 2, 3];

    /**
     * Display label used as Finding::tool.
     */
    public function name(): string
    {
        return 'PHP Mess Detector';
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
        if (!Cli::mentions($this->command, 'phpmd analyze')) {
            throw new \UnexpectedValueException('Not a PHPMD command');
        }

        $command = Cli::strip($this->command, ['--progress']);
        $command = Cli::ensureFlag($command, '--no-progress');

        // Enforce JSON format
        return $this->shell(Cli::setOption($command, '--format', 'json'), $paths);
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
        $data = $this->decodeJson($output);
        $findings = [];
        foreach ($data['files'] ?? [] as $file_data) {
            foreach ($file_data['violations'] ?? [] as $message) {
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: $message['rule'] ?? 'unknown',
                    file: $file_data['file'] ?? '',
                    line: (int) ($message['beginLine'] ?? 0),
                    message: $message['description'] ?? '',
                    fixable: (bool) ($message['fixable'] ?? false),
                );
            }
        }

        // Files, that PHPMD could not process (for example, because of syntax errors)
        foreach ($data['errors'] ?? [] as $error) {
            if ($error['fileName'] === '') {
                if (\preg_match('/^(?<message>.+?),\s*line:\s*(?<line>\d+)(?:,\s*col:\s*(?<col>\d+))?,\s*file:\s*(?<file>.+?)\.?\\n.*/uir', $error['message'] ?? '', $matches) === 1) {
                    $findings[] = new Finding(
                        tool: $this->name(),
                        rule: 'processing-error',
                        file: $matches['file'] ?? '',
                        line: (int) $matches['line'],
                        column: (int) $matches['col'],
                        message: $matches['message'] ?? '',
                        type: Severity::ERROR,
                    );
                } else {
                    $findings[] = new Finding(
                        tool: $this->name(),
                        rule: 'processing-error',
                        file: '',
                        message: $error['message'] ?? '',
                        type: Severity::ERROR,
                    );
                }
            } else {
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: 'processing-error',
                    file: $error['fileName'] ?? '',
                    message: $error['message'] ?? '',
                    type: Severity::ERROR,
                );
            }
        }

        return $findings;
    }
}
