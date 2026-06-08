<?php

namespace App\Services;

use App\Jobs\GenerateCsvExportJob;
use App\Jobs\GenerateExcelExportJob;
use App\Jobs\GeneratePdfExportJob;
use App\Models\Project;

class ExportService
{
    // -----------------------------------------------------------------------
    // Guard — export hanya boleh saat status = accepted
    // -----------------------------------------------------------------------

    private function guardAccepted(Project $project): void
    {
        if ($project->status !== 'accepted') {
            throw new \RuntimeException(
                'Export not allowed. Survey status must be accepted.'
            );
        }
    }

    // -----------------------------------------------------------------------
    // Public — dispatch export jobs
    // -----------------------------------------------------------------------

    public function exportPdf(Project $project, int $userId): void
    {
        $this->guardAccepted($project);

        GeneratePdfExportJob::dispatch($project->id, $userId);
    }

    public function exportExcel(Project $project, int $userId): void
    {
        $this->guardAccepted($project);

        GenerateExcelExportJob::dispatch($project->id, $userId);
    }

    public function exportCsv(Project $project, int $userId): void
    {
        $this->guardAccepted($project);

        GenerateCsvExportJob::dispatch($project->id, $userId);
    }

    // -----------------------------------------------------------------------
    // Helper — path direktori export untuk satu project
    // -----------------------------------------------------------------------

    public static function exportDir(int $projectId): string
    {
        return storage_path("app/exports/{$projectId}");
    }
}