<?php

namespace App\Http\Controllers;

use App\Exceptions\RecordingException;
use App\Http\Requests\AppendPointsRequest;
use App\Http\Requests\StartRecordingRequest;
use App\Models\Track;
use App\Services\TrackRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrackRecordingController extends Controller
{
    public function __construct(private readonly TrackRecordingService $recording)
    {
    }

    public function active(Request $request): JsonResponse
    {
        $track = $this->recording->activeFor($request->user());

        return response()->json(['data' => $track ? $this->payload($track) : null]);
    }

    public function store(StartRecordingRequest $request): JsonResponse
    {
        $name = $request->validated('name') ?: 'Rekaman ' . now()->format('Y-m-d H:i');

        try {
            $track = $this->recording->start($request->user(), $name, $request->validated('project_id'));
        } catch (RecordingException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => $this->payload($track)], 201);
    }

    public function points(AppendPointsRequest $request, Track $track): JsonResponse
    {
        Gate::authorize('update', $track);

        try {
            $result = $this->recording->appendBatch(
                $track,
                $request->validated('client_batch_id'),
                $request->points(),
            );
        } catch (RecordingException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => $result]);
    }

    public function pause(Track $track): JsonResponse
    {
        return $this->transition($track, fn () => $this->recording->pause($track));
    }

    public function resume(Track $track): JsonResponse
    {
        return $this->transition($track, fn () => $this->recording->resume($track));
    }

    public function finish(Track $track): JsonResponse
    {
        return $this->transition($track, fn () => $this->recording->finish($track));
    }

    private function transition(Track $track, callable $action): JsonResponse
    {
        Gate::authorize('update', $track);

        try {
            $updated = $action();
        } catch (RecordingException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => $this->payload($updated)]);
    }

    private function error(RecordingException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'reason'  => $e->reason,
            'track'   => $e->track ? $this->payload($e->track) : null,
        ], 409);
    }

    private function payload(Track $track): array
    {
        return [
            'id'          => $track->id,
            'name'        => $track->name,
            'status'      => $track->status,
            'project_id'  => $track->project_id,
            'point_count' => $track->point_count,
            'started_at'  => $track->started_at?->toIso8601String(),
        ];
    }
}
