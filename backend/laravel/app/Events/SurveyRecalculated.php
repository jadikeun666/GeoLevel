<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SurveyRecalculated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $projectId,
        public readonly int $userId,
    ) {}
}