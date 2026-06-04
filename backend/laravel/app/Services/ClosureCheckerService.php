<?php

namespace App\Services;

use App\Events\ClosureChecked;
use App\Models\ActivityLog;
use App\Models\Project;

class ClosureCheckerService
{
    public function check(int $projectId, int $userId): void
    {
        $project = Project::findOrFail($projectId);

        // Hasil closure sudah dihitung & disimpan oleh LevelingCalculationService
        // Service ini hanya dispatch event + log untuk memicu downstream listeners

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
