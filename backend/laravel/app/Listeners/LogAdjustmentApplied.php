<?php

namespace App\Listeners;

use App\Events\AdjustmentApplied;
use App\Models\ActivityLog;

class LogAdjustmentApplied
{
    public function handle(AdjustmentApplied $event): void
    {
        ActivityLog::create([
            'project_id'    => $event->projectId,
            'user_id'       => $event->userId,
            'activity_type' => ActivityLog::TYPE_ADJUSTMENT_APPLIED,
            'description'   => "Hitung perataan diterapkan. Metode: {$event->method}.",
            'metadata'      => [
                'method' => $event->method,
            ],
        ]);
    }
}