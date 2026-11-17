<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

/**
 * Creates OSC 8 terminal hyperlinks.
 */
final class Hyperlink
{
    /**
     * Wrap the text in an OSC 8 hyperlink. Returns the text as it is, if there is no template.
     *
     * @param string|null $template URL template with `%file%`, `%line%` and `%column%` placeholders. The caller must make sure it has no control characters.
     * @param string      $text     Text to show
     * @param string      $file     File path
     * @param int         $line     Line
     * @param int         $column   Column
     *
     * @return string
     */
    public static function wrap(?string $template, string $text, string $file, int $line, int $column): string
    {
        if ($template === null) {
            return $text;
        }

        // Encode the path, but keep the slashes readable
        $url = \strtr($template, [
            '%file%' => \str_replace('%2F', '/', \rawurlencode($file)),
            '%line%' => (string) $line,
            '%column%' => (string) $column,
        ]);

        return "\e]8;;".$url."\e\\".$text."\e]8;;\e\\";
    }
}
