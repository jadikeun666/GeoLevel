<?php

namespace App\Services;

use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TrackService
{
    /** Track summaries only (never the raw points). */
    public function listFor(User $user, ?int $projectId = null): Collection
    {
        return Track::query()
            ->where('user_id', $user->id)
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('id')
            ->get();
    }

    public function delete(Track $track): void
    {
        $track->delete();
    }
}
