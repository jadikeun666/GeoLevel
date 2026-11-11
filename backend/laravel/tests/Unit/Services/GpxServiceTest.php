<?php

namespace Tests\Unit\Services;

use App\Exceptions\GpxParseException;
use App\Services\GpxService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GpxServiceTest extends TestCase
{
    private GpxService $gpx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gpx = new GpxService();
    }

    private static function doc(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1">'
            . $body
            . '</gpx>';
    }

    #[Test]
    public function parses_waypoints_and_track_points_keeping_numeric_strings(): void
    {
        $result = $this->gpx->parseString(self::doc(
            '<wpt lat="-6.2" lon="106.8"><ele>12.5</ele><name>Pos A</name><desc>Catatan</desc></wpt>'
            . '<trk><name>Jalur 1</name><trkseg>'
            . '<trkpt lat="-6.2" lon="106.8"><ele>10</ele><time>2026-01-01T00:00:00Z</time></trkpt>'
            . '<trkpt lat="-6.21" lon="106.81"/>'
            . '</trkseg></trk>'
        ));

        $this->assertSame(
            ['name' => 'Pos A', 'lat' => '-6.2', 'lng' => '106.8', 'ele' => '12.5', 'notes' => 'Catatan'],
            $result['waypoints'][0]
        );

        $track = $result['tracks'][0];
        $this->assertSame('Jalur 1', $track['name']);
        $this->assertCount(2, $track['points']);
        $this->assertSame('10', $track['points'][0]['ele']);
        $this->assertSame('2026-01-01 00:00:00.000+00:00', $track['points'][0]['recorded_at']);
        $this->assertNull($track['points'][1]['ele']);
        $this->assertNull($track['points'][1]['recorded_at']);
    }

    #[Test]
    public function merges_all_segments_of_a_track_in_document_order(): void
    {
        $result = $this->gpx->parseString(self::doc(
            '<trk><trkseg><trkpt lat="0" lon="1"/><trkpt lat="0" lon="2"/></trkseg>'
            . '<trkseg><trkpt lat="0" lon="3"/></trkseg></trk>'
        ));

        $this->assertCount(1, $result['tracks']);
        $this->assertSame(['1', '2', '3'], array_column($result['tracks'][0]['points'], 'lng'));
    }

    #[Test]
    public function multiple_tracks_become_separate_entries_and_missing_name_is_null(): void
    {
        $result = $this->gpx->parseString(self::doc(
            '<trk><name>A</name><trkseg><trkpt lat="0" lon="1"/></trkseg></trk>'
            . '<trk><trkseg><trkpt lat="0" lon="2"/></trkseg></trk>'
        ));

        $this->assertCount(2, $result['tracks']);
        $this->assertSame('A', $result['tracks'][0]['name']);
        $this->assertNull($result['tracks'][1]['name']);
    }

    #[Test]
    public function waypoint_gets_default_name_and_long_name_is_truncated(): void
    {
        $result = $this->gpx->parseString(self::doc(
            '<wpt lat="1" lon="2"/>'
            . '<wpt lat="1" lon="2"><name>' . str_repeat('x', 150) . '</name></wpt>'
        ));

        $this->assertSame('Waypoint 1', $result['waypoints'][0]['name']);
        $this->assertSame(100, mb_strlen($result['waypoints'][1]['name']));
        $this->assertNull($result['waypoints'][0]['notes']);
    }

    #[Test]
    public function time_is_converted_to_utc_and_keeps_milliseconds(): void
    {
        $result = $this->gpx->parseString(self::doc(
            '<trk><trkseg>'
            . '<trkpt lat="0" lon="1"><time>2026-01-01T07:00:00+07:00</time></trkpt>'
            . '<trkpt lat="0" lon="2"><time>2026-01-01T00:00:00.250Z</time></trkpt>'
            . '</trkseg></trk>'
        ));

        $points = $result['tracks'][0]['points'];
        $this->assertSame('2026-01-01 00:00:00.000+00:00', $points[0]['recorded_at']);
        $this->assertSame('2026-01-01 00:00:00.250+00:00', $points[1]['recorded_at']);
    }

    public static function invalidDocuments(): array
    {
        return [
            'non-gpx root'        => ['<?xml version="1.0"?><kml></kml>'],
            'malformed xml'       => ['<?xml version="1.0"?><gpx version="1.1"><wpt lat="1" lon="2"></gpx>'],
            'doctype'             => ['<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY a "b">]><gpx version="1.1"></gpx>'],
            'latitude too large'  => [self::doc('<wpt lat="91" lon="2"/>')],
            'latitude not number' => [self::doc('<wpt lat="abc" lon="2"/>')],
            'missing longitude'   => [self::doc('<trk><trkseg><trkpt lat="1"/></trkseg></trk>')],
            'empty string'        => [''],
            'bad time'            => [self::doc('<trk><trkseg><trkpt lat="1" lon="2"><time>bukan-waktu</time></trkpt></trkseg></trk>')],
            'bad elevation'       => [self::doc('<wpt lat="1" lon="2"><ele>tinggi</ele></wpt>')],
            'natural language time' => [self::doc('<trk><trkseg><trkpt lat="1" lon="2"><time>tomorrow</time></trkpt></trkseg></trk>')],
        ];
    }

    #[Test]
    #[DataProvider('invalidDocuments')]
    public function invalid_documents_are_rejected(string $xml): void
    {
        $this->expectException(GpxParseException::class);

        $this->gpx->parseString($xml);
    }

    #[Test]
    public function track_point_limit_is_enforced(): void
    {
        config(['geolevel.outdoor.track_max_points' => 2]);

        $this->expectException(GpxParseException::class);

        $this->gpx->parseString(self::doc(
            '<trk><trkseg><trkpt lat="0" lon="1"/><trkpt lat="0" lon="2"/><trkpt lat="0" lon="3"/></trkseg></trk>'
        ));
    }

    #[Test]
    public function waypoint_limit_is_enforced(): void
    {
        config(['geolevel.outdoor.gpx_max_waypoints' => 1]);

        $this->expectException(GpxParseException::class);

        $this->gpx->parseString(self::doc('<wpt lat="1" lon="2"/><wpt lat="3" lon="4"/>'));
    }

    #[Test]
    public function empty_gpx_returns_empty_lists(): void
    {
        $this->assertSame(['waypoints' => [], 'tracks' => []], $this->gpx->parseString(self::doc('')));
    }

    #[Test]
    public function parse_file_reads_a_gpx_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gpx');
        file_put_contents($path, self::doc('<wpt lat="1" lon="2"><name>Dari file</name></wpt>'));

        try {
            $result = $this->gpx->parseFile($path);
        } finally {
            unlink($path);
        }

        $this->assertSame('Dari file', $result['waypoints'][0]['name']);
    }

    #[Test]
    public function parse_file_rejects_file_larger_than_configured_limit(): void
    {
        config(['geolevel.outdoor.gpx_max_size_mb' => 0.0001]);

        $path = tempnam(sys_get_temp_dir(), 'gpx');
        file_put_contents($path, self::doc(str_repeat('<wpt lat="1" lon="2"/>', 20)));

        try {
            $this->expectException(GpxParseException::class);
            $this->gpx->parseFile($path);
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function parse_file_rejects_missing_file(): void
    {
        $this->expectException(GpxParseException::class);

        $this->gpx->parseFile('/tmp/tidak-ada-' . uniqid() . '.gpx');
    }
}
