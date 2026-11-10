<?php

namespace App\Policies;

use App\Models\Track;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Support\Facades\Gate;

/**
 * Ownership policy for Outdoor items (waypoints, tracks).
 * Item belongs to user_id; if project_id is set, access also follows ProjectPolicy.
 */
class OutdoorItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Waypoint|Track $item): bool
    {
        return $this->canAccess($user, $item, 'view');
    }

    public function update(User $user, Waypoint|Track $item): bool
    {
        return $this->canAccess($user, $item, 'update');
    }

    public function delete(User $user, Waypoint|Track $item): bool
    {
        return $this->canAccess($user, $item, 'update');
    }

    private function canAccess(User $user, Waypoint|Track $item, string $projectAbility): bool
    {
        if ($item->user_id !== $user->id) {
            return false;
        }

        if ($item->project_id === null) {
            return true;
        }

        $project = $item->project;

        return $project !== null && Gate::forUser($user)->allows($projectAbility, $project);
    }
}
