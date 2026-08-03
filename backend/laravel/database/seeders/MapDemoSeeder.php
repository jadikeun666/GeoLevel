<?php

namespace Database\Seeders;

use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\Reading;
use App\Models\SurveyPoint;
use App\Models\User;
use Illuminate\Database\Seeder;

class MapDemoSeeder extends Seeder
{
    /**
     * Proyek khusus untuk pengembangan & pengujian fitur peta:
     * - 5 titik berurutan (BM-A -> TP-1 -> TP-2 -> TP-3 -> BM-B)
     * - 4 di antaranya punya koordinat survey_points (untuk polyline)
     * - 1 titik (TP-2) sengaja tanpa koordinat (untuk test "titik tanpa koordinat")
     * - project status 'accepted' supaya fitur export & network legs bisa diuji juga
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'mapdemo@geolevel.com'],
            ['name' => 'Map Demo User', 'password' => bcrypt('password')]
        );

        // Idempotent: hapus proyek demo lama milik user ini supaya re-seed
        // tidak menumpuk banyak project dengan nama sama (ini yang menyebabkan
        // kebingungan "koordinat tidak muncul" — Anda mungkin melihat project
        // lama dari run seeder sebelumnya, bukan yang baru).
        Project::where('user_id', $user->id)
            ->where('name', 'Peta Demo - Jalur Sipat Datar')
            ->get()
            ->each(fn ($old) => $old->delete());

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Peta Demo - Jalur Sipat Datar',
            'location' => 'Bandar Lampung',
            'survey_date' => now()->subDays(3),
            'benchmark_name' => 'BM-A',
            'benchmark_elevation' => 100.0000,
            'tolerance_class' => 'LA',
            'adjustment_method' => 'equal',
            'status' => 'calculated',
            'metadata' => [],
        ]);

        $points = [
            ['name' => 'BM-A', 'type' => 'BS', 'ba' => 1.5230, 'bt' => 1.2100, 'bb' => 0.8970, 'lat' => -5.397200, 'lng' => 105.266100],
            ['name' => 'TP-1', 'type' => 'FS', 'ba' => 1.4870, 'bt' => 1.1750, 'bb' => 0.8630, 'lat' => -5.397500, 'lng' => 105.266400],
            ['name' => 'TP-1', 'type' => 'BS', 'ba' => 1.6210, 'bt' => 1.3050, 'bb' => 0.9890, 'lat' => -5.397500, 'lng' => 105.266400],
            ['name' => 'TP-2', 'type' => 'FS', 'ba' => 1.3940, 'bt' => 1.0820, 'bb' => 0.7700, 'lat' => null, 'lng' => null], // sengaja tanpa koordinat
            ['name' => 'TP-2', 'type' => 'BS', 'ba' => 1.5560, 'bt' => 1.2430, 'bb' => 0.9300, 'lat' => null, 'lng' => null],
            ['name' => 'BM-B', 'type' => 'FS', 'ba' => 1.4120, 'bt' => 1.1000, 'bb' => 0.7880, 'lat' => -5.398100, 'lng' => 105.267000],
        ];

        $rawElevation = 100.0000;
        $seq = 1;
        $seenCoords = [];

        foreach ($points as $row) {
            $reading = Reading::withoutEvents(fn () => Reading::create([
                'project_id' => $project->id,
                'sequence_no' => $seq,
                'point_name' => $row['name'],
                'reading_type' => $row['type'],
                'ba' => $row['ba'],
                'bt' => $row['bt'],
                'bb' => $row['bb'],
                'distance_m' => null,
                'notes' => null,
            ]));

            ComputedElevation::create([
                'project_id' => $project->id,
                'reading_id' => $reading->id,
                'sequence_no' => $seq,
                'point_name' => $row['name'],
                'hi' => $row['type'] === 'BS' ? $rawElevation + $row['bt'] : null,
                'raw_elevation' => $rawElevation,
                'correction' => 0,
                'adjusted_elevation' => $rawElevation,
                'cumulative_distance' => $seq * 50,
            ]);

            if ($row['lat'] !== null && !in_array($row['name'], $seenCoords, true)) {
                SurveyPoint::create([
                    'project_id' => $project->id,
                    'point_name' => $row['name'],
                    'lat' => $row['lat'],
                    'lng' => $row['lng'],
                    'point_type' => $row['name'] === 'BM-A' || $row['name'] === 'BM-B' ? 'BM' : 'TP',
                    'source' => 'manual',
                ]);
                $seenCoords[] = $row['name'];
            }

            $seq++;
        }

        // Network legs dummy — loop kecil BM-A -> TP-1 -> BM-B -> BM-A,
        // untuk menguji overlay jaring di peta (garis warna sesuai status proyek).
        \App\Models\NetworkLeg::create([
            'project_id' => $project->id,
            'from_point' => 'BM-A',
            'to_point' => 'TP-1',
            'observed_delta_h' => 0.035000,
            'distance_m' => 45.200,
            'corrected_delta_h' => 0.035000,
            'residual' => 0.000000,
            'notes' => null,
        ]);
        \App\Models\NetworkLeg::create([
            'project_id' => $project->id,
            'from_point' => 'TP-1',
            'to_point' => 'BM-B',
            'observed_delta_h' => 0.223000,
            'distance_m' => 60.800,
            'corrected_delta_h' => 0.223000,
            'residual' => 0.000000,
            'notes' => null,
        ]);
        \App\Models\NetworkLeg::create([
            'project_id' => $project->id,
            'from_point' => 'BM-B',
            'to_point' => 'BM-A',
            'observed_delta_h' => -0.258000,
            'distance_m' => 52.100,
            'corrected_delta_h' => -0.258000,
            'residual' => 0.000000,
            'notes' => null,
        ]);

        $this->command?->info("MapDemoSeeder: project #{$project->id} created for user mapdemo@geolevel.com / password");
    }
}
