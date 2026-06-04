<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExportGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $projectId,
        public readonly int    $userId,
        public readonly string $format,    // pdf | excel | csv
        public readonly string $filePath,  // storage/app/exports/{project_id}/...
    ) {}
}