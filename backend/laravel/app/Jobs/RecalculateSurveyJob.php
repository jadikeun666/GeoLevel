<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ActivityLog;
use App\Services\LevelingCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateSurveyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $projectId,
    ) {}

    public function handle(): void
    {
        $project = Project::findOrFail($this->projectId);
        app(LevelingCalculationService::class)->recalculate($project);
    }

    public function failed(Throwable $e): void
    {
        Log::error("RecalculateSurveyJob failed for project {$this->projectId}: {$e->getMessage()}");

        $project = Project::find($this->projectId);

        if (! $project) {
            return;
        }

        $project->update(['status' => 'draft']);

        ActivityLog::create([
            'project_id'    => $this->projectId,
            'user_id'       => $project->user_id,
            'activity_type' => ActivityLog::TYPE_JOB_FAILED,
            'description'   => "Recalculation job failed: {$e->getMessage()}",
            'metadata'      => ['exception' => get_class($e)],
        ]);
    }
}