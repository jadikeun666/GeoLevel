<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OutdoorRecordingConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_outdoor_page_exposes_exactly_the_recording_config_keys(): void
    {
        $user = User::factory()->create();
        $c    = fn (string $key) => config("geolevel.outdoor.{$key}");

        $this->actingAs($user)
            ->get(route('outdoor.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Outdoor/Index')
                ->has('recordingConfig', fn (Assert $cfg) => $cfg
                    ->where('record_min_distance_m', $c('record_min_distance_m'))
                    ->where('record_min_interval_s', $c('record_min_interval_s'))
                    ->where('record_max_accuracy_m', $c('record_max_accuracy_m'))
                    ->where('record_auto_pause_speed_mps', $c('record_auto_pause_speed_mps'))
                    ->where('record_auto_pause_after_s', $c('record_auto_pause_after_s'))
                    ->where('record_batch_size', $c('record_batch_size'))
                    ->where('record_batch_interval_s', $c('record_batch_interval_s'))
                    ->where('record_use_wake_lock', $c('record_use_wake_lock'))
                    ->where('track_max_points', $c('track_max_points'))
                    ->where('offline_queue_max_points', $c('offline_queue_max_points'))
                )
                ->where('defaultCoordinateFormat', config('geolevel.outdoor.default_coordinate_format'))
                ->where('movingSpeedMinMps', config('geolevel.outdoor.stats_moving_speed_min_mps'))
                ->etc()
            );
    }
}
