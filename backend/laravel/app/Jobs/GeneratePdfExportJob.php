<?php

namespace App\Jobs;

use App\Events\ExportGenerated;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePdfExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly int $projectId,
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        $project = Project::with(['readings', 'computedElevations'])->findOrFail($this->projectId);

        $rows = $project->computedElevations()->orderBy('sequence_no')->get();

        $filename    = "field_book_{$this->projectId}_" . now()->format('YmdHis') . '.pdf';
        $storagePath = "exports/{$this->projectId}/{$filename}";

        $content = $this->buildPdfContent($project, $rows);

        // Always write via the Storage facade so behaviour is identical
        // whether the 'local' disk is the real filesystem (production)
        // or Storage::fake('local') (tests).
        Storage::disk('local')->put($storagePath, $content);

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'export_generated',
            'description'   => "PDF field book generated: {$filename}",
            'metadata'      => ['file' => $filename, 'type' => 'pdf'],
        ]);

        ExportGenerated::dispatch($this->projectId, $this->userId, 'pdf', $storagePath);
    }

    /**
     * Render the PDF as a raw byte string.
     *
     * Uses DomPDF if available (real field book layout). Falls back to a
     * minimal valid PDF structure for environments without DomPDF.
     */
    private function buildPdfContent(Project $project, $rows): string
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.field_book', [
                'project' => $project,
                'rows'    => $rows,
            ])->setPaper('a4', 'portrait')->output();
        }

        return $this->generateMinimalPdf($project, $rows);
    }

    /**
     * Minimal PDF content for environments without DomPDF (e.g. CI / tests).
     */
    private function generateMinimalPdf(Project $project, $rows): string
    {
        $title = "GeoLevel Field Book - {$project->name}";

        return "%PDF-1.4\n1 0 obj<</Type /Catalog /Pages 2 0 R>>endobj\n" .
               "2 0 obj<</Type /Pages /Kids [3 0 R] /Count 1>>endobj\n" .
               "3 0 obj<</Type /Page /Parent 2 0 R /MediaBox [0 0 595 842]>>endobj\n" .
               "xref\n0 4\n0000000000 65535 f\n" .
               "trailer<</Size 4 /Root 1 0 R>>\nstartxref\n9\n%%EOF\n";
    }

    public function failed(Throwable $e): void
    {
        // Delete any partial export files for this project (Business Rule:
        // export job failure → delete partial file, log to activity_logs).
        $disk = Storage::disk('local');

        foreach ($disk->files("exports/{$this->projectId}") as $file) {
            if (str_starts_with(basename($file), "field_book_{$this->projectId}_")) {
                $disk->delete($file);
            }
        }

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'job_failed',
            'description'   => 'GeneratePdfExportJob failed: ' . $e->getMessage(),
            'metadata'      => ['exception' => $e->getMessage()],
        ]);
    }
}