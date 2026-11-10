<?php

namespace App\Services;

use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class WaypointService
{
    public function listFor(User $user, ?int $projectId = null): Collection
    {
        return Waypoint::query()
            ->where('user_id', $user->id)
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function create(User $user, array $data): Waypoint
    {
        $max = (int) config('geolevel.outdoor.waypoint_max_per_user');

        if (Waypoint::where('user_id', $user->id)->count() >= $max) {
            throw ValidationException::withMessages([
                'name' => "Batas jumlah waypoint ({$max}) sudah tercapai.",
            ]);
        }

        $data = Arr::except($data, ['user_id']);
        $data['category'] = $data['category'] ?? 'generic';
        $data['source']   = $data['source'] ?? 'manual';

        return Waypoint::create($data + ['user_id' => $user->id]);
    }

    public function update(Waypoint $waypoint, array $data): Waypoint
    {
        $waypoint->update(Arr::except($data, ['user_id', 'source']));

        return $waypoint->fresh();
    }

    public function delete(Waypoint $waypoint): void
    {
        $waypoint->delete();
    }
}
