<?php

namespace App\Jobs\Concerns;

use App\Models\Project;
use Illuminate\Support\Collection;

trait BuildsNetworkExportData
{
    private function buildAdjustedPoints(Project $project, Collection $legs): array
    {
        $pointMap = [];
        $bmName   = $project->benchmark_name;

        // Benchmark selalu fixed
        $pointMap[$bmName] = [
            'point_name'         => $bmName,
            'is_benchmark'       => true,
            'initial_elevation'  => (float) $project->benchmark_elevation,
            'correction'         => 0.0,
            'adjusted_elevation' => (float) $project->benchmark_elevation,
            'std_dev_mm'         => 0.0,
        ];

        // Kumpulkan semua point_name unik dari legs
        $pointNames = $legs->flatMap(fn ($l) => [$l->from_point, $l->to_point])
            ->unique()
            ->filter(fn ($p) => $p !== $bmName)
            ->values();

        // Baca dari computed_elevations — sumber kebenaran setelah LS adjustment
        $elevations = $project->computedElevations()
            ->whereIn('point_name', $pointNames->all())
            ->orderByDesc('sequence_no')
            ->get()
            ->groupBy('point_name');

        foreach ($pointNames as $ptName) {
            $ce = $elevations->get($ptName)?->first();

            $pointMap[$ptName] = [
                'point_name'         => $ptName,
                'is_benchmark'       => false,
                'initial_elevation'  => $ce ? (float) $ce->raw_elevation : 0.0,
                'correction'         => $ce ? (float) $ce->correction : 0.0,
                'adjusted_elevation' => $ce ? (float) $ce->adjusted_elevation : 0.0,
                'std_dev_mm'         => null, // per-point std dev tidak disimpan di DB
            ];
        }

        return array_values($pointMap);
    }

    private function buildStats(Project $project, Collection $legs): array
    {
        $n        = $legs->count();
        $points   = $legs->flatMap(fn ($l) => [$l->from_point, $l->to_point])
            ->unique()->count();
        $unknowns = max($points - 1, 1);

        // Baca dari kolom DB yang benar
        $variance   = (float) ($project->network_variance ?? 0);
        $sigma0Mm   = (float) ($project->network_std_deviation ?? 0) * 1000;
        $redundancy = $project->network_degrees_of_freedom
            ?? max($n - $unknowns, 0);

        // VTPV = variance_factor × redundancy
        $vtpv = $variance * $redundancy;

        $totalDistKm = $legs->sum(fn ($l) => (float) ((float) $l->distance_m / 1000));

        return [
            'n_legs'            => $n,
            'n_points'          => $points,
            'n_unknowns'        => $unknowns,
            'redundancy'        => $redundancy,
            'total_distance_km' => $totalDistKm,
            'vtpv'              => $vtpv,
            'variance_factor'   => $variance,
            'sigma0_mm'         => $sigma0Mm,
            'converged'         => true, // LS sudah selesai jika data ada
            'iterations'        => 1,
            'max_iterations'    => 50,
        ];
    }

    private function buildConnectivity(Project $project, Collection $legs): array
    {
        $connectivity = [];
        $bmName       = $project->benchmark_name;

        foreach ($legs as $leg) {
            foreach ([
                $leg->from_point => $leg->to_point,
                $leg->to_point   => $leg->from_point,
            ] as $pt => $neighbor) {
                if (!isset($connectivity[$pt])) {
                    $connectivity[$pt] = [
                        'degree'       => 0,
                        'neighbors'    => [],
                        'is_benchmark' => ($pt === $bmName),
                    ];
                }
                $connectivity[$pt]['degree']++;
                $connectivity[$pt]['neighbors'][] = $neighbor;
            }
        }

        foreach ($connectivity as &$info) {
            $info['neighbors'] = array_values(array_unique($info['neighbors']));
        }

        uksort($connectivity, function ($a, $b) use ($bmName, $connectivity) {
            if ($a === $bmName) return -1;
            if ($b === $bmName) return 1;
            return $connectivity[$b]['degree'] <=> $connectivity[$a]['degree'];
        });

        return $connectivity;
    }
}