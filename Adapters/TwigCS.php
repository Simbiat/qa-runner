<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use DOMDocument;
use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\Severity;
use Simbiat\QARunner\Helpers\Cli;
use Simbiat\QARunner\Helpers\Paths;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Symfony\Component\Process\Process;
use TwigCsFixer\Rules\AbstractFixableRule;

/**
 * Adapter for Twig CS Fixer https://github.com/VincentLanglet/Twig-CS-Fixer.
 */
final readonly class TwigCS extends AbstractAdapter implements FixableToolAdapter
{
    /**
     * 0 - no violations, 1 - violations found. Anything else is a failure of the tool.
     */
    protected const array OK_EXIT_CODES = [0, 1];

    /**
     * Display label used as Finding::tool.
     */
    public function name(): string
    {
        return 'Twig CS Fixer';
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
        if (!Cli::mentions($this->command, 'twig-cs-fixer check') && !Cli::mentions($this->command, 'twig-cs-fixer lint')) {
            throw new \UnexpectedValueException('Not a Twig CS Fixer command');
        }

        // Enforce CheckStyle format
        return $this->shell(Cli::setOption($this->command, '--report', 'checkstyle'), $paths);
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
        if (!Cli::mentions($this->command, 'twig-cs-fixer') || !Cli::has($this->command, ['--fix'])) {
            throw new \UnexpectedValueException('Not a Twig CS Fixer fix command');
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
     * @throws \RuntimeException If the output is not valid XML
     */
    protected function extractFindings(string $output, Paths $paths): array
    {
        if (\trim($output) === '') {
            return [];
        }
        $dom = new DOMDocument();
        $previous = \libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($output);
        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new \RuntimeException('Output is not valid XML');
        }

        $findings = [];
        foreach ($dom->getElementsByTagName('file') as $file_node) {
            $file = $this->absolute($file_node->getAttribute('name'));
            foreach ($file_node->getElementsByTagName('error') as $error_node) {
                $source = $error_node->getAttribute('source') ?: 'unknown';

                try {
                    $fixable = \is_a($source, AbstractFixableRule::class, true);
                } catch (\Throwable) {
                    $fixable = false;
                }
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: self::shortName($source),
                    file: $file,
                    line: (int) $error_node->getAttribute('line'),
                    column: (int) $error_node->getAttribute('column'),
                    message: $error_node->getAttribute('message'),
                    type: Severity::fromString($error_node->getAttribute('severity')),
                    fixable: $fixable,
                );
            }
        }

        return $findings;
    }
}
