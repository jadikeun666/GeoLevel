<?php

namespace App\Listeners;

use App\Events\ClosureChecked;
use App\Models\ActivityLog;

class LogClosureResult
{
    public function handle(ClosureChecked $event): void
    {
        ActivityLog::create([
            'project_id'    => $event->projectId,
            'user_id'       => $event->userId,
            'activity_type' => ActivityLog::TYPE_CLOSURE_CHECKED,
            'description'   => "Closure check selesai. Status: {$event->status}. "
                             . "fh={$event->closureError}, toleransi={$event->allowedTolerance}",
            'metadata'      => [
                'status'            => $event->status,
                'closure_error'     => $event->closureError,
                'allowed_tolerance' => $event->allowedTolerance,
            ],
        ]);
    }
}