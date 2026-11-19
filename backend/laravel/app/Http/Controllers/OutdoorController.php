<?php

namespace App\Http\Controllers;

use App\Services\RouteService;
use App\Services\TrackService;
use App\Services\WaypointService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class OutdoorController extends Controller
{
    /**
     * Client-side recording settings exposed to the page (docs/outdoor.md, 17.2).
     * Only these keys; the recorder component holds no hardcoded numbers.
     */
    private const RECORDING_CONFIG_KEYS = [
        'record_min_distance_m',
        'record_min_interval_s',
        'record_max_accuracy_m',
        'record_auto_pause_speed_mps',
        'record_auto_pause_after_s',
        'record_batch_size',
        'record_batch_interval_s',
        'record_use_wake_lock',
        'track_max_points',
        'offline_queue_max_points',
    ];

    public function __construct(
        private readonly TrackService $tracks,
        private readonly WaypointService $waypoints,
        private readonly RouteService $routes,
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
            'recordingConfig' => Arr::only(config('geolevel.outdoor'), self::RECORDING_CONFIG_KEYS),
            'routes'    => $this->routes->listFor($user),
            'routeMaxVertices' => (int) config('geolevel.outdoor.route_max_vertices'),
        ]);
    }
}
