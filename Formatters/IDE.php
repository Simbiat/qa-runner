<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Formatters;

use JetBrains\PhpStorm\Pure;
use Simbiat\QARunner\Entities\Finding;
use Simbiat\QARunner\Entities\Severity;
use Simbiat\QARunner\Helpers\Hyperlink;
use Simbiat\QARunner\Interfaces\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Format findings in a way suitable to be fed to IDE or similar tools, for example to be used by JetBrains File Watcher. The type of finding is standardized to `Severity`, because IDE filters expect known values.
 */
final readonly class IDE implements OutputFormatter
{
    /**
     * @param bool        $colors        Color the `[tool, severity]` part by severity. Escape sequences break the filters of IDEs, so use it only outside of them.
     * @param string|null $link_template URL template for OSC 8 hyperlinks on `file:line:column`, with `%file%`, `%line%` and `%column%` placeholders. Null means no hyperlinks.
     */
    public function __construct(
        private bool $colors = false,
        private ?string $link_template = null,
    ) {}

    /**
     * Main function to prepare the output. Line and column are always 1 or more (IDEs, at least PhpStorm, do not show items with line 0), because `Finding` ensures it.
     *
     * @param array<Finding> $findings List of found issues
     *
     * @return string
     */
    #[Pure]
    #[\Override]
    public function format(array $findings): string
    {
        $lines = [];
        foreach ($findings as $finding) {
            $lines[] = \sprintf(
                '%s: %s %s (%s)',
                Hyperlink::wrap($this->link_template, \sprintf('%s:%d:%d', $finding->file, $finding->line, $finding->column), $finding->file, $finding->line, $finding->column),
                $this->color(\sprintf('[%s, %s%s]', $finding->tool, $finding->fixable ? 'Fixable ' : '', Severity::fromString($finding->type)->value), $finding->type),
                $finding->message,
                $finding->rule,
            );
        }

        return \implode("\n", $lines);
    }

    /**
     * Color the text, if colors are enabled.
     *
     * @param string $text Text to color
     * @param string $type Type of finding as returned by the tool. It is standardized to `Severity` to pick the color.
     *
     * @return string
     */
    private function color(string $text, string $type): string
    {
        return $this->colors
            ? new OutputFormatterStyle(Severity::fromString($type)->color())->apply($text)
            : $text;
    }
}
