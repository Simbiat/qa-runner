<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Entities;

/**
 * Normalized severity of a finding
 */
enum Severity: string
{
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
    case NOTICE = 'NOTICE';

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
            'ERROR', 'FATAL', 'CRITICAL' => self::ERROR,
            'WARNING', 'WARN' => self::WARNING,
            'NOTICE', 'INFO', 'INFORMATION' => self::NOTICE,
            default => $default,
        };
    }
}
