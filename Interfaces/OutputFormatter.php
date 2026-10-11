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
     * @param array<Finding> $findings List of found issues. Already sorted by the caller, so formatters must keep the order.
     *
     * @return string Output without a trailing line break. Empty string if there is nothing to show.
     */
    public function format(array $findings): string;
}
