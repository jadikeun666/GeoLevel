<?php

namespace App\Services;

use App\Models\CrossSection;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class VisualizationService
{
    // -----------------------------------------------------------------------
    // Long section — untuk LongSectionChart.vue
    // Returns: [{ x: cumulative_distance, y: adjusted_elevation, label: point_name }]
    // -----------------------------------------------------------------------

    public function longSection(Project $project): array
    {
        return DB::table('computed_elevations')
            ->where('project_id', $project->id)
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'cumulative_distance', 'adjusted_elevation'])
            ->map(fn ($row) => [
                'x'     => (float) $row->cumulative_distance,
                'y'     => (float) $row->adjusted_elevation,
                'label' => $row->point_name,
            ])
            ->values()
            ->all();
    }

    // -----------------------------------------------------------------------
    // Cross section — untuk CrossSectionChart.vue
    // Returns: { station: string, offsets: [{ side, distance, elevation }] }
    // -----------------------------------------------------------------------

    public function crossSection(Project $project, string $stationName): ?array
    {
        $record = CrossSection::where('project_id', $project->id)
            ->where('station_name', $stationName)
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'station' => $record->station_name,
            'offsets' => collect($record->offsets)
                ->map(fn ($o) => [
                    'side'      => $o['side'],
                    'distance'  => (float) $o['distance'],
                    'elevation' => (float) $o['elevation'],
                ])
                ->sortBy(fn ($o) => match ($o['side']) {
                    'L'     => -$o['distance'],
                    'C'     => 0,
                    'R'     => $o['distance'],
                    default => 0,
                })
                ->values()
                ->all(),
        ];
    }

    // -----------------------------------------------------------------------
    // Daftar semua station name cross-section untuk satu project
    // -----------------------------------------------------------------------

    public function crossSectionStations(Project $project): array
    {
        return CrossSection::where('project_id', $project->id)
            ->orderBy('station_distance')
            ->pluck('station_name')
            ->values()
            ->all();
    }
}