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
use Throwable;

class GenerateCsvExportJob implements ShouldQueue
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
        $project = Project::findOrFail($this->projectId);

        $dir = ExportService::exportDir($this->projectId);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "survey_{$this->projectId}_" . now()->format('YmdHis') . '.csv';
        $path     = "{$dir}/{$filename}";

        $rows = $project->computedElevations()
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'cumulative_distance', 'adjusted_elevation', 'correction']);

        $handle = fopen($path, 'w');

        // No header row — AutoCAD Civil 3D / GIS import by column position
        // Column order: sequence_no, point_name, cumulative_distance, adjusted_elevation, correction
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->sequence_no,
                $row->point_name,
                number_format((float) $row->cumulative_distance, 3, '.', ''),
                number_format((float) $row->adjusted_elevation, 4, '.', ''),
                number_format((float) $row->correction, 6, '.', ''),
            ]);
        }

        fclose($handle);

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'export_generated',
            'description'   => "CSV export generated: {$filename}",
            'metadata'      => ['file' => $filename, 'type' => 'csv'],
        ]);

        ExportGenerated::dispatch($this->projectId, $this->userId, 'csv', $path);
    }

    public function failed(Throwable $e): void
    {
        $dir = ExportService::exportDir($this->projectId);
        foreach (glob("{$dir}/survey_{$this->projectId}_*.csv") as $file) {
            @unlink($file);
        }

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'job_failed',
            'description'   => 'GenerateCsvExportJob failed: ' . $e->getMessage(),
            'metadata'      => ['exception' => $e->getMessage()],
        ]);
    }
}