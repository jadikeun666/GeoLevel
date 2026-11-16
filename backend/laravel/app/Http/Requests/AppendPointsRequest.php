<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One batch of live-recorded points (docs/outdoor.md, bagian 8).
 * Numbers are rounded to the column scale here; float is used only to read the
 * device's JSON numbers (same precision exception as GeoMathService).
 */
class AppendPointsRequest extends FormRequest
{
    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,3})?(Z|[+-]\d{2}:\d{2})$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Tolerate retries/merged queues: up to 10x the client batch size.
        $max = (int) config('geolevel.outdoor.record_batch_size') * 10;

        return [
            'client_batch_id'      => ['required', 'uuid'],
            'points'               => ['required', 'array', 'min:1', "max:{$max}"],
            'points.*.lat'         => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng'         => ['required', 'numeric', 'between:-180,180'],
            'points.*.ele'         => ['nullable', 'numeric', 'between:-99999999,99999999'],  // NUMERIC(12,4)
            'points.*.accuracy_m'  => ['nullable', 'numeric', 'min:0', 'max:999'],            // NUMERIC(6,3)
            'points.*.speed_mps'   => ['nullable', 'numeric', 'min:0', 'max:99999'],          // NUMERIC(8,3)
            'points.*.heading_deg' => ['nullable', 'numeric', 'min:0', 'max:360'],            // NUMERIC(6,3)
            'points.*.recorded_at' => ['required', 'string', 'regex:' . self::ISO_8601],
        ];
    }

    /**
     * Points normalized for TrackRecordingService.
     *
     * @return list<array<string, ?string>>
     */
    public function points(): array
    {
        return array_map(fn (array $p) => [
            'lat'         => number_format((float) $p['lat'], 8, '.', ''),
            'lng'         => number_format((float) $p['lng'], 8, '.', ''),
            'ele'         => $this->fixed($p['ele'] ?? null, 4),
            'accuracy_m'  => $this->fixed($p['accuracy_m'] ?? null, 3),
            'speed_mps'   => $this->fixed($p['speed_mps'] ?? null, 3),
            'heading_deg' => $this->fixed($p['heading_deg'] ?? null, 3),
            'recorded_at' => CarbonImmutable::parse($p['recorded_at'])->utc()->format('Y-m-d H:i:s.vP'),
        ], array_values($this->validated('points')));
    }

    private function fixed(mixed $value, int $decimals): ?string
    {
        return $value === null || $value === '' ? null : number_format((float) $value, $decimals, '.', '');
    }
}
