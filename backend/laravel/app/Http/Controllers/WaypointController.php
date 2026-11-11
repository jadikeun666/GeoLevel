<?php

namespace App\Http\Controllers;

use App\Exceptions\GpxParseException;
use App\Http\Requests\ImportGpxRequest;
use App\Http\Requests\StoreWaypointRequest;
use App\Models\Waypoint;
use App\Services\GpxService;
use App\Services\WaypointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function import(ImportGpxRequest $request, GpxService $gpx): JsonResponse
    {
        $this->authorize('create', Waypoint::class);

        try {
            $parsed = $gpx->parseFile($request->file('file')->getRealPath());
        } catch (GpxParseException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        if ($parsed['waypoints'] === []) {
            throw ValidationException::withMessages(['file' => 'File GPX tidak memuat waypoint.']);
        }

        $imported = $this->service->importMany(
            $request->user(),
            $parsed['waypoints'],
            $request->integer('project_id') ?: null,
        );

        return response()->json(['imported' => $imported], 201);
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
