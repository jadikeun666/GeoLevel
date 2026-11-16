<?php

namespace Tests\Feature;

use App\Models\OutdoorRoute;
use App\Models\Track;
use App\Models\TrackPointBatch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingModelsTest extends TestCase
{
    use RefreshDatabase;

    private const BATCH_ID = '3f0c2a3e-8b1d-4c53-9d3e-2a6f5b7c9d10';

    private function batchRow(Track $track): array
    {
        return [
            'track_id'        => $track->id,
            'client_batch_id' => self::BATCH_ID,
            'first_seq'       => 1,
            'point_count'     => 30,
            'received_at'     => now(),
        ];
    }

    public function test_same_client_batch_id_cannot_be_stored_twice_for_a_track(): void
    {
        $track = Track::factory()->create();
        TrackPointBatch::create($this->batchRow($track));

        $this->expectException(QueryException::class);
        TrackPointBatch::create($this->batchRow($track));
    }

    public function test_same_client_batch_id_is_allowed_on_a_different_track(): void
    {
        TrackPointBatch::create($this->batchRow(Track::factory()->create()));
        TrackPointBatch::create($this->batchRow(Track::factory()->create()));

        $this->assertSame(2, TrackPointBatch::count());
    }

    public function test_batches_are_deleted_with_their_track(): void
    {
        $track = Track::factory()->create();
        TrackPointBatch::create($this->batchRow($track));

        $track->delete();

        $this->assertSame(0, TrackPointBatch::count());
    }

    public function test_outdoor_route_uses_routes_table_and_casts_geometry(): void
    {
        $route = OutdoorRoute::factory()->create(['distance_m' => '1234.5']);

        $fresh = OutdoorRoute::findOrFail($route->id);

        $this->assertSame('routes', $fresh->getTable());
        $this->assertSame([[-6.9, 107.6], [-6.91, 107.61]], $fresh->geometry);
        $this->assertSame('1234.500', $fresh->distance_m);
    }
}
