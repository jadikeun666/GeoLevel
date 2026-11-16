<?php

namespace App\Listeners;

use App\Events\TrackFinished;
use App\Models\ActivityLog;

class LogTrackFinished
{
    public function handle(TrackFinished $event): void
    {
        // activity_logs.project_id is NOT NULL: tracks without a project are not logged
        // (docs/outdoor.md, keputusan terbuka #2).
        if ($event->projectId === null) {
            return;
        }

        ActivityLog::create([
            'project_id'    => $event->projectId,
            'user_id'       => $event->userId,
            'activity_type' => ActivityLog::TYPE_TRACK_FINISHED,
            'description'   => 'Rekaman track selesai.',
            'metadata'      => ['track_id' => $event->trackId],
        ]);
    }
}
