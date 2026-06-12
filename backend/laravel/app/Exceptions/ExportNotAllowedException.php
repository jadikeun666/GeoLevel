<?php

namespace App\Exceptions;

use RuntimeException;

class ExportNotAllowedException extends RuntimeException
{
    public function __construct(string $message = 'Export not allowed. Survey status must be accepted.')
    {
        parent::__construct($message);
    }
}