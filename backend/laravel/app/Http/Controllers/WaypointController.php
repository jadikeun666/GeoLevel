<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWaypointRequest;
use App\Models\Waypoint;
use App\Services\WaypointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaypointController extends Controller
{
    public function __construct(private readonly WaypointService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Waypoint::class);

        $projectId = $request->integer('project_id') ?: null;

        return response()->json([
            'data' => $this->service->listFor($request->user(), $projectId),
        ]);
    }

    public function store(StoreWaypointRequest $request): JsonResponse
    {
        $this->authorize('create', Waypoint::class);

        $waypoint = $this->service->create($request->user(), $request->validated());

        return response()->json(['data' => $waypoint], 201);
    }

    public function update(StoreWaypointRequest $request, Waypoint $waypoint): JsonResponse
    {
        $this->authorize('update', $waypoint);

        return response()->json([
            'data' => $this->service->update($waypoint, $request->validated()),
        ]);
    }

    public function destroy(Waypoint $waypoint): JsonResponse
    {
        $this->authorize('delete', $waypoint);

        $this->service->delete($waypoint);

        return response()->json(['message' => 'Waypoint berhasil dihapus.']);
    }
}
