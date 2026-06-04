<?php

namespace App\Observers;

use App\Jobs\RecalculateSurveyJob;
use App\Models\Reading;

class ReadingObserver
{
    public function saved(Reading $reading): void
    {
        $this->dispatch($reading);
    }

    public function deleted(Reading $reading): void
    {
        $this->dispatch($reading);
    }

    private function dispatch(Reading $reading): void
    {
        RecalculateSurveyJob::dispatch(
            $reading->project_id,
            $reading->project->user_id,
        );
    }
}
