<?php

namespace App\Services;

use App\Exceptions\ExportNotAllowedException;
use App\Jobs\GenerateCsvExportJob;
use App\Jobs\GenerateExcelExportJob;
use App\Jobs\GeneratePdfExportJob;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class ExportService
{
    // -----------------------------------------------------------------------
    // Guard — export hanya boleh saat status = accepted
    // -----------------------------------------------------------------------

    private function guardAccepted(Project $project): void
    {
        if ($project->status !== 'accepted') {
            throw new ExportNotAllowedException(
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

    /**
     * CSV is lightweight — dispatch job but also return URL for immediate response.
     * Returns an array with 'url' key pointing to the export file.
     *
     * @return array{url: string}
     */
    public function exportCsv(Project $project, int $userId): array
    {
        $this->guardAccepted($project);

        GenerateCsvExportJob::dispatch($project->id, $userId);

        // Return a predictable URL for the client to poll/download
        $relativePath = "exports/{$project->id}";
        $url = Storage::url($relativePath);

        return ['url' => $url];
    }

    // -----------------------------------------------------------------------
    // Helper — path direktori export untuk satu project
    // -----------------------------------------------------------------------

    public static function exportDir(int $projectId): string
    {
        return storage_path("app/exports/{$projectId}");
    }
}