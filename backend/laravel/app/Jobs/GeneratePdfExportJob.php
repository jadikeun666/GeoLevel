<?php

namespace App\Jobs;

use App\Events\ExportGenerated;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $rows    = $project->computedElevations()->orderBy('sequence_no')->get();
        $reading = $project->readings()->orderBy('sequence_no')->get()->keyBy('id');

        $dir = ExportService::exportDir($this->projectId);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf = Pdf::loadView('exports.field_book', [
            'project' => $project,
            'rows'    => $rows,
        ])->setPaper('a4', 'portrait');

        $filename = "field_book_{$this->projectId}_" . now()->format('YmdHis') . '.pdf';
        $path     = "{$dir}/{$filename}";

        $pdf->save($path);

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'export_generated',
            'description'   => "PDF field book generated: {$filename}",
            'metadata'      => ['file' => $filename, 'type' => 'pdf'],
        ]);

        ExportGenerated::dispatch($this->projectId, $this->userId, 'pdf', $path);
    }

    public function failed(Throwable $e): void
    {
        // Delete partial file if exists
        $dir = ExportService::exportDir($this->projectId);
        foreach (glob("{$dir}/field_book_{$this->projectId}_*.pdf") as $file) {
            @unlink($file);
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