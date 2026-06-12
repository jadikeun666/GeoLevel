<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an export file (PDF / Excel / CSV) has been generated
 * and stored under storage/app/exports/{project_id}/.
 *
 * Carries project_id and user_id per architecture.md event contract,
 * plus export-specific metadata consumed by LogExportGenerated.
 */
class ExportGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $projectId,
        public readonly int $userId,
        public readonly string $format,
        public readonly string $filePath,
    ) {}
}