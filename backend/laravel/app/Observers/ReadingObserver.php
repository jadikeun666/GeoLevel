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
        // PERUBAHAN: Hapus argumen kedua $reading->project->user_id.
        // Job constructor sekarang hanya terima $projectId — tidak perlu userId.
        // Sebelumnya: RecalculateSurveyJob::dispatch($reading->project_id, $reading->project->user_id)
        // Ini penyebab error "1 passed and exactly 2 expected" di factory test.
        RecalculateSurveyJob::dispatch($reading->project_id);
    }
}