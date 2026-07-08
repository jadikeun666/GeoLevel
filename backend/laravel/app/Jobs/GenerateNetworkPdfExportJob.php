<?php

namespace App\Jobs;

use App\Events\ExportGenerated;
use App\Models\NetworkLeg;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateNetworkPdfExportJob implements ShouldQueue
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
        $project = Project::with(['networkLegs'])->findOrFail($this->projectId);

        $legs = $project->networkLegs()
            ->orderBy('id')
            ->get();

        // Build adjusted points list from legs
        $adjustedPoints = $this->buildAdjustedPoints($project, $legs);

        // Build stats
        $stats = $this->buildStats($project, $legs);

        // Build connectivity map
        $connectivity = $this->buildConnectivity($project, $legs);

        $pdf = Pdf::loadView('exports.network_report', [
            'project'        => $project,
            'legs'           => $legs,
            'adjustedPoints' => collect($adjustedPoints),
            'stats'          => $stats,
            'connectivity'   => $connectivity,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $dir      = config('geolevel.export_path') . '/' . $this->projectId;
        $filename = 'network_report_' . now()->format('Ymd_His') . '.pdf';
        $path     = $dir . '/' . $filename;

        Storage::disk(config('geolevel.export_disk'))
            ->put($path, $pdf->output());

        ExportGenerated::dispatch(
            projectId: $this->projectId,
            userId:    $this->userId,
            format:    'pdf_network',
            filePath:  $path,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateNetworkPdfExportJob failed', [
            'project_id' => $this->projectId,
            'error'      => $e->getMessage(),
        ]);

        // Delete any partial file
        $dir = config('geolevel.export_path') . '/' . $this->projectId;
        $disk = Storage::disk(config('geolevel.export_disk'));
        foreach ($disk->files($dir) as $f) {
            if (str_contains($f, 'network_report_')) {
                $disk->delete($f);
            }
        }
    }

}