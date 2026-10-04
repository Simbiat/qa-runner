<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Adapters;

use DOMDocument;
use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\FixResult;
use Simbiat\QARunner\Interfaces\DiffableToolAdapter;
use Simbiat\QARunner\Interfaces\FixableToolAdapter;
use Simbiat\QARunner\Interfaces\ToolAdapter;
use Symfony\Component\Process\Process;
use TwigCsFixer\Rules\AbstractFixableRule;

/**
 * Adapter for Twig CS Fixer https://github.com/VincentLanglet/Twig-CS-Fixer
 */
final readonly class TwigCS implements ToolAdapter, DiffableToolAdapter, FixableToolAdapter
{
    /**
     * @param string $project_root Path to project root
     */
    public function __construct(private string $project_root) {}

    /** Display label used as Finding::tool, e.g. 'PHP CodeSniffer'. */
    public function name(): string
    {
        return 'Twig CS Fixer';
    }

    /**
     * Method to prepare Check command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for regular check by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildCheckProcess(string $paths, string $base_command): Process
    {
        if (\stripos($base_command, 'twig-cs-fixer check') === false && \stripos($base_command, 'twig-cs-fixer lint') === false) {
            throw new \UnexpectedValueException('Not a Twig CS Fixer command');
        }
        // Enforce CheckStyle format
        $base_command = \preg_replace('/--report=\S+/uir', '', $base_command).' --report=checkstyle';

        return Process::fromShellCommandline($base_command.$paths, $this->project_root);
    }

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return array<\Simbiat\QARunner\Entities\Finding>
     *
     * @throws \JsonException
     */
    public function parseCheck(Process $process, array $paths): array
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($process->getOutput())) {
            return [];
        }
        $findings = [];
        foreach ($dom->getElementsByTagName('file') as $file_node) {
            $file = $file_node->getAttribute('name');
            foreach ($file_node->getElementsByTagName('error') as $error_node) {
                try {
                    $fixable = \is_a($error_node->getAttribute('source'), AbstractFixableRule::class, true);
                } catch (\Throwable) {
                    $fixable = false;
                }
                $findings[] = new Finding(
                    tool: $this->name(),
                    rule: \substr($error_node->getAttribute('source') ?: 'unknown', \strrpos($error_node->getAttribute('source') ?: 'unknown', '\\') + 1),
                    file: (string) $file,
                    line: (int) ($error_node->getAttribute('line') ?? 0),
                    column: (int) ($error_node->getAttribute('column') ?? 0),
                    message: $error_node->getAttribute('message'),
                    type: $error_node->getAttribute('severity') ?: 'warning',
                    fixable: $fixable,
                );
            }
        }

        return $findings;
    }

    /**
     * Method to prepare Diff command. Adapter appends whatever flags *it* needs for parsing to work, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for Diff by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildDiffProcess(string $paths, string $base_command): Process
    {
        if (\stripos($base_command, 'phpcs') === false) {
            throw new \UnexpectedValueException('Not a PHPCS command');
        }
        // Enforce Diff format
        $base_command = \preg_replace('/--report=\S+/uir', '', $base_command).' --report=diff';

        return Process::fromShellCommandline($base_command.$paths, $this->project_root);
    }

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return string
     */
    public function parseDiff(Process $process, array $paths): string
    {
        return \trim($process->getOutput());
    }

    /**
     * Method to prepare fix command. Adapter appends whatever flags *it* needs for the fix, then the paths.
     *
     * @param string $paths        Paths to be scanned. Taken from CLI and escaped.
     * @param string $base_command Command for Diff by the tool. Taken from composer.json.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function buildFixProcess(string $paths, string $base_command): Process
    {
        if (\stripos($base_command, 'phpcbf') === false) {
            throw new \UnexpectedValueException('Not a PHPCBF command');
        }

        return Process::fromShellCommandline($base_command.$paths, $this->project_root);
    }

    /**
     * Parse the tool output.
     *
     * @param \Symfony\Component\Process\Process $process Symfony's Process instance
     * @param array<string>                      $paths   Paths to be scanned. Taken from CLI.
     *
     * @return \Simbiat\QARunner\Entities\FixResult
     */
    public function parseFix(Process $process, array $paths): FixResult
    {
        $code = $process->getExitCode();

        return new FixResult(
            tool: $this->name(),
            success: $code === 0,
            output: \trim($process->getOutput()."\n".$process->getErrorOutput()),
        );
    }
}
