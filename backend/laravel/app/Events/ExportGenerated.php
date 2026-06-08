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
        public readonly string $type,   // 'pdf' | 'excel' | 'csv'
        public readonly string $path,   // absolute filesystem path
    ) {}
}