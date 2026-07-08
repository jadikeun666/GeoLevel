<?php

namespace App\Services;

use App\Exceptions\ExportNotAllowedException;
use App\Jobs\GenerateNetworkExcelExportJob;
use App\Jobs\GenerateNetworkPdfExportJob;
use App\Models\Project;

/**
 * Orchestrates PDF and Excel export for loop-network least squares results.
 *
 * Rules:
 * - Export only allowed when project.status = accepted (same as standard export).
 * - Project must have at least 1 network_leg.
 * - Jobs are dispatched to queue — never run synchronously.
 */
class NetworkExportService
{
    /**
     * @throws ExportNotAllowedException
     */
    public function exportPdf(Project $project, int $userId): void
    {
        $this->guard($project);

        GenerateNetworkPdfExportJob::dispatch($project->id, $userId);
    }

    /**
     * @throws ExportNotAllowedException
     */
    public function exportExcel(Project $project, int $userId): void
    {
        $this->guard($project);

        GenerateNetworkExcelExportJob::dispatch($project->id, $userId);
    }

    // ──────────────────────────────────────────────────────────────
    // Guard
    // ──────────────────────────────────────────────────────────────

    /**
     * @throws ExportNotAllowedException
     */
    private function guard(Project $project): void
    {
        if ($project->status !== 'accepted') {
            throw new ExportNotAllowedException(
                'Export tidak diijinkan. Status proyek harus accepted.'
            );
        }

        if ($project->networkLegs()->doesntExist()) {
            throw new ExportNotAllowedException(
                'Tidak ada data jalur jaring (network legs). Tambahkan jalur terlebih dahulu.'
            );
        }
    }
}