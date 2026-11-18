<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouteRequest;
use App\Models\OutdoorRoute;
use App\Services\RouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutdoorRouteController extends Controller
{
    public function __construct(private readonly RouteService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OutdoorRoute::class);

        return response()->json([
            'data' => $this->service->listFor($request->user(), $request->integer('project_id') ?: null),
        ]);
    }

    public function store(RouteRequest $request): JsonResponse
    {
        $this->authorize('create', OutdoorRoute::class);

        return response()->json(['data' => $this->service->create($request->user(), $request->validated())], 201);
    }

    public function update(RouteRequest $request, OutdoorRoute $outdoorRoute): JsonResponse
    {
        $this->authorize('update', $outdoorRoute);

        return response()->json(['data' => $this->service->update($outdoorRoute, $request->validated())]);
    }

    public function destroy(OutdoorRoute $outdoorRoute): JsonResponse
    {
        $this->authorize('delete', $outdoorRoute);

        $this->service->delete($outdoorRoute);

        return response()->json(['message' => 'Rute berhasil dihapus.']);
    }
}
