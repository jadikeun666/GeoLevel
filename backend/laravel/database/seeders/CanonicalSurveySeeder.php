<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\Reading;
use App\Services\LevelingCalculationService;
use App\Services\AdjustmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CanonicalSurveySeeder extends Seeder
{
    public function __construct(
        private LevelingCalculationService $calculator,
        private AdjustmentService          $adjuster,
    ) {}

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@geolevel.com'],
            [
                'name'     => 'Demo Surveyor',
                'password' => Hash::make('password'),
            ]
        );

        $this->seedCanonicalProject($user);
        $this->seedAcceptedProject($user);

        $this->command->info('✅  Seeder selesai.');
        $this->command->info('    Login: demo@geolevel.com / password');
        $this->command->info('    Proyek 1: Survey Kanonikal → REJECTED (fh melebihi toleransi LA)');
        $this->command->info('    Proyek 2: Survey Demo Diterima → ACCEPTED + adjusted');
    }

    // ── Proyek 1 — persis dari docs/formulas.md ──────────────────────
    // fh = 0.4010 m >> toleransi LA = 0.002452 m → REJECTED (disengaja)
    // ─────────────────────────────────────────────────────────────────
    private function seedCanonicalProject(User $user): void
    {
        $project = Project::create([
            'user_id'             => $user->id,
            'name'                => 'Survey Kanonikal BM-A ke BM-B',
            'location'            => 'Lokasi Demo — Jalan Raya Utama',
            'description'         => 'Dataset kanonikal dari docs/formulas.md. fh = 0.4010 m melebihi toleransi LA = 0.002452 m — status REJECTED disengaja.',
            'survey_date'         => '2024-06-15',
            'benchmark_name'      => 'BM-A',
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'draft',
            'metadata'            => [],
        ]);

        $readings = [
            ['sequence_no' => 1, 'point_name' => 'BM-A', 'reading_type' => 'BS', 'ba' => '1.5230', 'bt' => '1.2100', 'bb' => '0.8970'],
            ['sequence_no' => 2, 'point_name' => 'TP-1', 'reading_type' => 'FS', 'ba' => '1.4870', 'bt' => '1.1750', 'bb' => '0.8630'],
            ['sequence_no' => 3, 'point_name' => 'TP-1', 'reading_type' => 'BS', 'ba' => '1.6210', 'bt' => '1.3050', 'bb' => '0.9890'],
            ['sequence_no' => 4, 'point_name' => 'TP-2', 'reading_type' => 'FS', 'ba' => '1.3940', 'bt' => '1.0820', 'bb' => '0.7700'],
            ['sequence_no' => 5, 'point_name' => 'TP-2', 'reading_type' => 'BS', 'ba' => '1.5560', 'bt' => '1.2430', 'bb' => '0.9300'],
            ['sequence_no' => 6, 'point_name' => 'BM-B', 'reading_type' => 'FS', 'ba' => '1.4120', 'bt' => '1.1000', 'bb' => '0.7880'],
        ];

        foreach ($readings as $data) {
            Reading::create(array_merge($data, [
                'project_id' => $project->id,
                'distance_m' => null,
            ]));
        }

        // recalculate() sudah menghitung closure & update status ke project
        $this->calculator->recalculate($project);
    }

    // ── Proyek 2 — fh kecil supaya lolos toleransi LA ────────────────
    // ─────────────────────────────────────────────────────────────────
    private function seedAcceptedProject(User $user): void
    {
        $project = Project::create([
            'user_id'             => $user->id,
            'name'                => 'Survey Demo Diterima',
            'location'            => 'Lokasi Demo — Saluran Irigasi Blok B',
            'description'         => 'Contoh proyek dengan fh kecil sehingga lolos toleransi LA dan bisa di-export.',
            'survey_date'         => '2024-06-20',
            'benchmark_name'      => 'BM-01',
            'benchmark_elevation' => '50.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'draft',
            'metadata'            => [],
        ]);

        $readings = [
            ['sequence_no' => 1, 'point_name' => 'BM-01', 'reading_type' => 'BS', 'ba' => '1.8000', 'bt' => '1.5000', 'bb' => '1.2000'],
            ['sequence_no' => 2, 'point_name' => 'TP-1',  'reading_type' => 'FS', 'ba' => '1.7985', 'bt' => '1.4985', 'bb' => '1.1985'],
            ['sequence_no' => 3, 'point_name' => 'TP-1',  'reading_type' => 'BS', 'ba' => '1.7010', 'bt' => '1.4010', 'bb' => '1.1010'],
            ['sequence_no' => 4, 'point_name' => 'TP-2',  'reading_type' => 'FS', 'ba' => '1.7000', 'bt' => '1.4000', 'bb' => '1.1000'],
            ['sequence_no' => 5, 'point_name' => 'TP-2',  'reading_type' => 'BS', 'ba' => '1.6005', 'bt' => '1.3005', 'bb' => '1.0005'],
            ['sequence_no' => 6, 'point_name' => 'BM-02', 'reading_type' => 'FS', 'ba' => '1.6020', 'bt' => '1.3020', 'bb' => '1.0020'],
        ];

        foreach ($readings as $data) {
            Reading::create(array_merge($data, [
                'project_id' => $project->id,
                'distance_m' => null,
            ]));
        }

        $this->calculator->recalculate($project);

        // Refresh project untuk baca status terbaru dari DB
        $fresh = $project->fresh();

        if ($fresh->status === 'accepted') {
            $this->adjuster->applyToProject(
                project: $fresh,
                method:  'equal',
                userId:  $user->id,
            );
        }
    }
}