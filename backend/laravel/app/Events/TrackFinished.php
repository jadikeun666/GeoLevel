<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once when a live-recorded track moves to "finished" (idempotent repeats
 * of finish() do not fire it). Carries user_id and project_id (nullable) per
 * architecture.md event contract.
 */
class TrackFinished
{
    use Dispatchable;

    public function __construct(
        public readonly int $trackId,
        public readonly int $userId,
        public readonly ?int $projectId = null,
    ) {}
}
