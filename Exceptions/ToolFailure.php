<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Exceptions;

/**
 * Thrown when a QA tool did not run properly (missing binary, crash, bad configuration, timeout), as opposed to the tool successfully reporting findings.
 */
final class ToolFailure extends \RuntimeException {}
