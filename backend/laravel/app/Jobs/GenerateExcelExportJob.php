<?php

namespace App\Jobs;

use App\Events\ExportGenerated;
use App\Exports\SurveyExport;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateExcelExportJob implements ShouldQueue
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

        $filename    = "survey_{$this->projectId}_" . now()->format('YmdHis') . '.xlsx';
        $storagePath = "exports/{$this->projectId}/{$filename}";

        // Write via the Laravel 'local' disk so behaviour is identical
        // whether it's the real filesystem (production) or
        // Storage::fake('local') (tests). Maatwebsite\Excel resolves the
        // given disk name through the Storage facade internally.
        Excel::store(new SurveyExport($project), $storagePath, 'local');

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'export_generated',
            'description'   => "Excel export generated: {$filename}",
            'metadata'      => ['file' => $filename, 'type' => 'excel'],
        ]);

        ExportGenerated::dispatch($this->projectId, $this->userId, 'excel', $storagePath);
    }

    public function failed(Throwable $e): void
    {
        // Delete any partial export files for this project (Business Rule:
        // export job failure → delete partial file, log to activity_logs).
        $disk = Storage::disk('local');

        foreach ($disk->files("exports/{$this->projectId}") as $file) {
            if (str_starts_with(basename($file), "survey_{$this->projectId}_")) {
                $disk->delete($file);
            }
        }

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'job_failed',
            'description'   => 'GenerateExcelExportJob failed: ' . $e->getMessage(),
            'metadata'      => ['exception' => $e->getMessage()],
        ]);
    }
}