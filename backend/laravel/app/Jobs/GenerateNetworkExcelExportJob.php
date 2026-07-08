<?php

namespace App\Jobs;

use App\Events\ExportGenerated;
use App\Exports\Sheets\NetworkAdjustmentSheet;
use App\Exports\Sheets\NetworkLegsSheet;
use App\Exports\Sheets\NetworkSummarySheet;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class GenerateNetworkExcelExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use \App\Jobs\Concerns\BuildsNetworkExportData;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(
        private readonly int $projectId,
        private readonly int $userId
    ) {}

    public function handle(): void
    {
        /** @var Project $project */
        $project = Project::findOrFail($this->projectId);

        $legs = $project->networkLegs()
            ->orderBy('id')
            ->get();

        $adjustedPoints = $this->buildAdjustedPoints($project, $legs);
        $stats          = $this->buildStats($project, $legs);

        $dir      = config('geolevel.export_path') . '/' . $this->projectId;
        $filename = 'network_report_' . now()->format('Ymd_His') . '.xlsx';
        $path     = $dir . '/' . $filename;

        // Ensure directory exists
        Storage::disk(config('geolevel.export_disk'))->makeDirectory($dir);

        Excel::store(
            new class($legs, collect($adjustedPoints), $project, $stats) implements
                \Maatwebsite\Excel\Concerns\WithMultipleSheets
            {
                public function __construct(
                    private $legs,
                    private $adjustedPoints,
                    private $project,
                    private $stats
                ) {}

                public function sheets(): array
                {
                    return [
                        new NetworkLegsSheet($this->legs),
                        new NetworkAdjustmentSheet($this->adjustedPoints),
                        new NetworkSummarySheet($this->project, $this->stats),
                    ];
                }
            },
            $path,
            config('geolevel.export_disk'),
            ExcelFormat::XLSX
        );

        ExportGenerated::dispatch(
            projectId: $this->projectId,
            userId:    $this->userId,
            format:    'excel_network',
            filePath:  $path,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateNetworkExcelExportJob failed', [
            'project_id' => $this->projectId,
            'error'      => $e->getMessage(),
        ]);

        $dir  = config('geolevel.export_path') . '/' . $this->projectId;
        $disk = Storage::disk(config('geolevel.export_disk'));
        foreach ($disk->files($dir) as $f) {
            if (str_contains($f, 'network_report_') && str_ends_with($f, '.xlsx')) {
                $disk->delete($f);
            }
        }
    }

}