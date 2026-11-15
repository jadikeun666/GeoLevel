<?php

namespace Tests\Feature;

use App\Services\StaticMapRenderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StaticMapTileUrlTest extends TestCase
{
    private function service(): StaticMapRenderService
    {
        return app(StaticMapRenderService::class);
    }

    public function test_street_tile_url_has_key_when_configured(): void
    {
        config(['geolevel.carto_basemaps_key' => 'test-key']);

        $this->assertSame(
            'https://a.basemaps.cartocdn.com/rastertiles/voyager/15/100/200.png?key=test-key',
            $this->service()->buildTileUrl('street', 15, 100, 200)
        );
    }

    public function test_street_tile_url_has_no_query_without_key(): void
    {
        config(['geolevel.carto_basemaps_key' => null]);

        $this->assertSame(
            'https://a.basemaps.cartocdn.com/rastertiles/voyager/15/100/200.png',
            $this->service()->buildTileUrl('street', 15, 100, 200)
        );
    }

    public function test_key_is_url_encoded(): void
    {
        config(['geolevel.carto_basemaps_key' => 'a b&c']);

        $this->assertStringEndsWith('?key=a%20b%26c', $this->service()->buildTileUrl('street', 1, 2, 3));
    }

    public function test_satellite_tile_url_never_carries_the_carto_key(): void
    {
        config(['geolevel.carto_basemaps_key' => 'test-key']);

        $url = $this->service()->buildTileUrl('satellite', 15, 100, 200);

        $this->assertStringContainsString('arcgisonline.com', $url);
        $this->assertStringNotContainsString('test-key', $url);
    }

    public function test_failed_tile_fetch_does_not_log_the_key(): void
    {
        Http::fake(['*' => Http::response('nope', 404)]);
        Log::spy();

        $result = $this->service()->fetchTileImage(
            'https://a.basemaps.cartocdn.com/rastertiles/voyager/1/2/3.png?key=secret-key'
        );

        $this->assertNull($result);
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => isset($context['url'])
                && ! str_contains($context['url'], 'secret-key')
                && str_contains($context['url'], 'key=***')
        )->once();
    }
}
