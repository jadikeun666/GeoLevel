<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClosureChecked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $projectId,
        public readonly int    $userId,
        public readonly string $status,           // calculated | accepted | rejected
        public readonly float  $closureError,
        public readonly float  $allowedTolerance,
    ) {}
}