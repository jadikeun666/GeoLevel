<?php

namespace App\Listeners;

use App\Events\ExportGenerated;
use App\Models\ActivityLog;

class LogExportGenerated
{
    public function handle(ExportGenerated $event): void
    {
        ActivityLog::create([
            'project_id'    => $event->projectId,
            'user_id'       => $event->userId,
            'activity_type' => ActivityLog::TYPE_EXPORT_GENERATED,
            'description'   => "Export {$event->format} berhasil dibuat.",
            'metadata'      => [
                'format'    => $event->format,
                'file_path' => $event->filePath,
            ],
        ]);
    }
}