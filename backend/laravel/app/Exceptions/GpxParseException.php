<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a GPX file is invalid or exceeds configured limits.
 * The message is user-facing (Indonesian).
 */
class GpxParseException extends RuntimeException
{
}
