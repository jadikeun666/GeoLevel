<?php

namespace App\Services;

use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

    /**
     * Bulk-creates waypoints parsed from a GPX file (source = gpx).
     * All-or-nothing; respects the per-user limit. Returns the number created.
     */
    public function importMany(User $user, array $waypoints, ?int $projectId = null): int
    {
        $max = (int) config('geolevel.outdoor.waypoint_max_per_user');

        if (Waypoint::where('user_id', $user->id)->count() + count($waypoints) > $max) {
            throw ValidationException::withMessages([
                'file' => "Impor melebihi batas jumlah waypoint ({$max}) per pengguna.",
            ]);
        }

        $now  = now();
        $rows = [];
        foreach ($waypoints as $waypoint) {
            $rows[] = [
                'user_id'         => $user->id,
                'project_id'      => $projectId,
                'survey_point_id' => null,
                'name'            => $waypoint['name'],
                'lat'             => $waypoint['lat'],
                'lng'             => $waypoint['lng'],
                'elevation_ref'   => $waypoint['ele'],
                'gps_accuracy_m'  => null,
                'category'        => 'generic',
                'source'          => 'gpx',
                'notes'           => $waypoint['notes'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                Waypoint::insert($chunk);
            }
        });

        return count($rows);
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
