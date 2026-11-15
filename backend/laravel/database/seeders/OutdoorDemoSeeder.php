<?php

namespace Database\Seeders;

use App\Models\Track;
use App\Models\User;
use App\Models\Waypoint;
use App\Services\TrackImportService;
use App\Services\WaypointService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the Outdoor feature (dev + e2e). Standalone: NOT called from
 * DatabaseSeeder. Idempotent: removes this user's previous outdoor data first.
 *
 * The track goes through TrackImportService (same path as a real GPX import),
 * so it also exercises parsing, stats and bulk insert.
 */
class OutdoorDemoSeeder extends Seeder
{
    public const EMAIL      = 'outdoordemo@geolevel.com';
    public const TRACK_NAME = 'Jalur Demo Lembang';

    public function __construct(
        private readonly TrackImportService $imports,
        private readonly WaypointService $waypoints,
    ) {
    }

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Outdoor Demo User', 'password' => Hash::make('password')]
        );

        // track_points are removed by the FK cascade.
        Track::where('user_id', $user->id)->delete();
        Waypoint::where('user_id', $user->id)->delete();

        $path = tempnam(sys_get_temp_dir(), 'outdoor-demo-');
        file_put_contents($path, $this->buildGpx());

        try {
            $this->imports->importFile($user, $path, self::TRACK_NAME, null, 'demo-lembang.gpx');
        } finally {
            @unlink($path);
        }

        $rows = [
            ['name' => 'Pos Demo 1', 'lat' => '-6.81500000', 'lng' => '107.61800000', 'category' => 'parking',      'elevation_ref' => '1200.0'],
            ['name' => 'Mata Air',   'lat' => '-6.81000000', 'lng' => '107.61850000', 'category' => 'water',        'elevation_ref' => '1215.5'],
            ['name' => 'Puncak Demo', 'lat' => '-6.80350000', 'lng' => '107.61900000', 'category' => 'summit',       'elevation_ref' => '1232.0'],
        ];
        foreach ($rows as $row) {
            $this->waypoints->create($user, $row);
        }

        $this->command?->info('OutdoorDemoSeeder: ' . self::EMAIL . ' / password (1 track, 3 waypoints)');
    }

    /** Deterministic 120-point GPX walk north from Lembang, 10 s per point. */
    private function buildGpx(): string
    {
        $points = '';
        $start  = strtotime('2026-11-01T01:00:00Z');
        for ($i = 0; $i < 120; $i++) {
            $lat  = -6.815 + $i * 0.0001;
            $lng  = 107.618 + 0.0008 * sin($i / 8);
            $ele  = 1200 + 15 * sin($i / 10) + $i * 0.2;
            $time = gmdate('Y-m-d\TH:i:s\Z', $start + $i * 10);
            $points .= sprintf(
                "      <trkpt lat=\"%.8f\" lon=\"%.8f\"><ele>%.1f</ele><time>%s</time></trkpt>\n",
                $lat, $lng, $ele, $time
            );
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<gpx version=\"1.1\" creator=\"OutdoorDemoSeeder\" xmlns=\"http://www.topografix.com/GPX/1/1\">\n"
            . "  <trk><name>" . self::TRACK_NAME . "</name><trkseg>\n"
            . $points
            . "  </trkseg></trk>\n</gpx>\n";
    }
}
