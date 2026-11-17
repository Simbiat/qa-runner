<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Entities;

/**
 * Normalized severity of a finding. Used only by formatters, that need standard values (IDE, Console). `Finding::$type` keeps the type as the tool returned it.
 */
enum Severity: string
{
    case ERROR = 'ERROR';
    case NOTICE = 'NOTICE';
    case WARNING = 'WARNING';

    /**
     * Name of the Symfony Console color for this severity.
     *
     * @return string
     */
    public function color(): string
    {
        return match ($this) {
            self::ERROR => 'red',
            self::NOTICE => 'cyan',
            self::WARNING => 'yellow',
        };
    }

    /**
     * Convert severity label of any tool to one of the known cases.
     *
     * @param string $raw     Label as returned by the tool
     * @param self   $default Value to use if the label is not recognized
     *
     * @return self
     */
    public static function fromString(string $raw, self $default = self::WARNING): self
    {
        return match (\strtoupper(\trim($raw))) {
            'ERROR', 'FATAL', 'CRITICAL', 'SECURITY' => self::ERROR,
            'WARNING', 'WARN' => self::WARNING,
            'NOTICE', 'INFO', 'INFORMATION', 'HELP', 'NOTE' => self::NOTICE,
            default => $default,
        };
    }
}
