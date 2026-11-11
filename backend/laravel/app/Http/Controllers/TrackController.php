<?php

namespace App\Http\Controllers;

use App\Exceptions\GpxParseException;
use App\Http\Requests\ImportGpxRequest;
use App\Models\Track;
use App\Services\TrackImportService;
use App\Services\TrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrackController extends Controller
{
    public function __construct(private readonly TrackService $tracks)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Track::class);

        $projectId = $request->integer('project_id') ?: null;

        return response()->json([
            'data' => $this->tracks->listFor($request->user(), $projectId),
        ]);
    }

    public function import(ImportGpxRequest $request, TrackImportService $importer): JsonResponse
    {
        $this->authorize('create', Track::class);

        $file     = $request->file('file');
        $original = $file->getClientOriginalName();
        $fallback = pathinfo($original, PATHINFO_FILENAME);

        try {
            $created = $importer->importFile(
                $request->user(),
                $file->getRealPath(),
                $fallback !== '' ? $fallback : 'Track GPX',
                $request->integer('project_id') ?: null,
                $original,
            );
        } catch (GpxParseException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return response()->json(['data' => $created], 201);
    }

    public function geometry(Track $track): JsonResponse
    {
        $this->authorize('view', $track);

        return response()->json(['data' => $this->tracks->geometry($track)]);
    }

    public function destroy(Track $track): JsonResponse
    {
        $this->authorize('delete', $track);

        $this->tracks->delete($track);

        return response()->json(['message' => 'Track berhasil dihapus.']);
    }
}
