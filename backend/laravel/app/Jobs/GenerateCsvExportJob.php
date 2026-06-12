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
use Illuminate\Support\Facades\Storage;
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
        $project  = Project::findOrFail($this->projectId);
        $disk     = config('geolevel.export_disk', 'local');
        $filename = "survey_{$this->projectId}_" . now()->format('YmdHis') . '.csv';
        $storagePath = config('geolevel.export_path', 'exports') . "/{$this->projectId}/{$filename}";

        $rows = $project->computedElevations()
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'cumulative_distance', 'adjusted_elevation', 'correction']);

        // Build CSV content in-memory, then write via Storage facade.
        // Ini memungkinkan Storage::fake() di test untuk menangkap file.
        // Column order (no header): sequence_no, point_name, cumulative_distance,
        //                           adjusted_elevation, correction
        // — downstream AutoCAD Civil 3D / GIS import by column position.
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $row->sequence_no,
                $row->point_name,
                number_format((float) $row->cumulative_distance, 3, '.', ''),
                number_format((float) $row->adjusted_elevation, 4, '.', ''),
                number_format((float) $row->correction, 6, '.', ''),
            ]);
        }

        Storage::disk($disk)->put($storagePath, implode("\n", $lines));

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $this->userId,
            'activity_type' => 'export_generated',
            'description'   => "CSV export generated: {$filename}",
            'metadata'      => ['file' => $filename, 'type' => 'csv'],
        ]);

        ExportGenerated::dispatch($this->projectId, $this->userId, 'csv', $storagePath);
    }

    public function failed(Throwable $e): void
    {
        $disk        = config('geolevel.export_disk', 'local');
        $exportPath  = config('geolevel.export_path', 'exports') . "/{$this->projectId}";

        // Hapus semua file CSV partial untuk project ini
        $files = Storage::disk($disk)->files($exportPath);
        foreach ($files as $file) {
            if (str_starts_with(basename($file), "survey_{$this->projectId}_")
                && str_ends_with($file, '.csv')) {
                Storage::disk($disk)->delete($file);
            }
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
