<?php

namespace App\Http\Controllers;

use App\Services\TrackService;
use App\Services\WaypointService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutdoorController extends Controller
{
    public function __construct(
        private readonly TrackService $tracks,
        private readonly WaypointService $waypoints,
    ) {
    }

    /**
     * GET /outdoor
     * Own tracks (summaries only), waypoints and projects (for the import selector).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Outdoor/Index', [
            'tracks'    => $this->tracks->listFor($user),
            'waypoints' => $this->waypoints->listFor($user),
            'projects'  => $user->projects()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
