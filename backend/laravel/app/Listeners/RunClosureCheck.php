<?php

namespace App\Listeners;

use App\Events\SurveyRecalculated;
use App\Services\ClosureCheckerService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RunClosureCheck implements ShouldQueue
{
    public function __construct(
        private readonly ClosureCheckerService $closureChecker,
    ) {}

    public function handle(SurveyRecalculated $event): void
    {
        $this->closureChecker->check($event->projectId, $event->userId);
    }
}