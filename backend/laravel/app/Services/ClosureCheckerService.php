<?php

namespace App\Services;

use App\Events\ClosureChecked;
use App\Models\ActivityLog;
use App\Models\Project;

class ClosureCheckerService
{
    // -----------------------------------------------------------------------
    // Closure sudah dihitung & disimpan oleh LevelingCalculationService.
    // Service ini bertanggung jawab:
    //   1. Log activity closure_checked
    //   2. Dispatch ClosureChecked event untuk downstream listeners
    // -----------------------------------------------------------------------

    public function check(int $projectId, int $userId): void
    {
        $project = Project::findOrFail($projectId);

        ActivityLog::create([
            'project_id'    => $projectId,
            'user_id'       => $userId,
            'activity_type' => ActivityLog::TYPE_CLOSURE_CHECKED,
            'description'   => "Closure check selesai untuk project #{$projectId}.",
            'metadata'      => [
                'closure_error'     => $project->closure_error,
                'allowed_tolerance' => $project->allowed_tolerance,
                'status'            => $project->status,
            ],
        ]);

        ClosureChecked::dispatch(
            $projectId,
            $userId,
            $project->status,
            (float) $project->closure_error,
            (float) $project->allowed_tolerance,
        );
    }
}