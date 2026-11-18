<?php

namespace App\Services;

use App\Models\OutdoorRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RouteService
{
    public function __construct(private readonly GeoMathService $geo)
    {
    }

    public function listFor(User $user, ?int $projectId = null): Collection
    {
        return OutdoorRoute::where('user_id', $user->id)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->latest('id')
            ->get();
    }

    public function create(User $user, array $data): OutdoorRoute
    {
        return OutdoorRoute::create($this->attributes($data) + ['user_id' => $user->id]);
    }

    public function update(OutdoorRoute $route, array $data): OutdoorRoute
    {
        $route->update($this->attributes($data));

        return $route;
    }

    public function delete(OutdoorRoute $route): void
    {
        $route->delete();
    }

    /** distance_m is always computed here; a client-sent value is ignored. */
    private function attributes(array $data): array
    {
        // JSONB [[lat, lng], ...] as in docs/outdoor.md; a plan, not a record of measurement.
        $geometry = array_map(fn (array $v) => [(float) $v[0], (float) $v[1]], array_values($data['geometry']));

        $meters = 0.0;
        for ($i = 1, $n = count($geometry); $i < $n; $i++) {
            $meters += $this->geo->haversine($geometry[$i - 1][0], $geometry[$i - 1][1], $geometry[$i][0], $geometry[$i][1]);
        }

        return [
            'project_id' => $data['project_id'] ?? null,
            'name'       => $data['name'],
            'geometry'   => $geometry,
            'distance_m' => number_format($meters, 3, '.', ''),
            'notes'      => $data['notes'] ?? null,
        ];
    }
}
