<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Interfaces;

use Simbiat\QARunner\Entities\Finding;

/**
 * Interface for output formatters, that can be used with Check command
 */
interface OutputFormatter
{
    /**
     * Main function to prepare the output
     *
     * @param array<Finding> $findings List of found issues
     *
     * @return string
     */
    public function format(array $findings): string;
}
