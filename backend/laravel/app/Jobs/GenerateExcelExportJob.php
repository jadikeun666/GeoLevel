<?php

namespace App\Jobs;

use App\Events\ExportGenerated;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SurveyExport;
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

        $dir = ExportService::exportDir($this->projectId);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "survey_{$this->projectId}_" . now()->format('YmdHis') . '.xlsx';
        $path     = "{$dir}/{$filename}";

        Excel::store(new SurveyExport($project), $path, 'local_absolute');

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'export_generated',
            'description'   => "Excel export generated: {$filename}",
            'metadata'      => ['file' => $filename, 'type' => 'excel'],
        ]);

        ExportGenerated::dispatch($this->projectId, $this->userId, 'excel', $path);
    }

    public function failed(Throwable $e): void
    {
        $dir = ExportService::exportDir($this->projectId);
        foreach (glob("{$dir}/survey_{$this->projectId}_*.xlsx") as $file) {
            @unlink($file);
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