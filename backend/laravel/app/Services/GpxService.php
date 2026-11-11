<?php

namespace App\Services;

use App\Exceptions\GpxParseException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use XMLReader;

/**
 * Streaming GPX parser (GPX 1.0 / 1.1, namespace-agnostic).
 *
 * Returns:
 *   [
 *     'waypoints' => [ ['name','lat','lng','ele','notes'], ... ],
 *     'tracks'    => [ ['name' => ?string, 'points' => [ ['lat','lng','ele','recorded_at'], ... ]], ... ],
 *   ]
 *
 * Coordinates and elevations stay NUMERIC strings exactly as written in the
 * file (no float conversion). recorded_at is a UTC string 'Y-m-d H:i:s.vP' or
 * null (GPX <time> is optional); strings, not Carbon, keep memory low for
 * tracks of 100k points. All <trkseg> of one <trk> are merged in document order.
 * <rte> is ignored. Limits come from config('geolevel.outdoor').
 */
class GpxService
{
    private const DECIMAL  = '/^-?\d+(\.\d+)?$/';
    private const ISO_TIME = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/';

    private ?DateTimeZone $utc = null;

    public function parseFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new GpxParseException('File GPX tidak dapat dibaca.');
        }

        $maxMb = config('geolevel.outdoor.gpx_max_size_mb');
        if (filesize($path) > (float) $maxMb * 1024 * 1024) {
            throw new GpxParseException("Ukuran file GPX melebihi batas {$maxMb} MB.");
        }

        return $this->withLibxmlErrors(
            fn () => $this->parse(XMLReader::open($path, null, LIBXML_NONET))
        );
    }

    public function parseString(string $xml): array
    {
        if (trim($xml) === '') {
            throw new GpxParseException('File GPX kosong atau bukan XML yang valid.');
        }

        return $this->withLibxmlErrors(
            fn () => $this->parse(XMLReader::XML($xml, null, LIBXML_NONET))
        );
    }

    private function withLibxmlErrors(callable $callback): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            return $callback();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function parse(XMLReader|bool $reader): array
    {
        if (! $reader instanceof XMLReader) {
            throw new GpxParseException('File GPX bukan XML yang valid.');
        }

        $maxWaypoints = (int) config('geolevel.outdoor.gpx_max_waypoints');
        $waypoints    = [];
        $tracks       = [];
        $sawRoot      = false;

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new GpxParseException('File GPX tidak boleh memuat DOCTYPE.');
                }
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                if (! $sawRoot) {
                    if ($reader->localName !== 'gpx') {
                        throw new GpxParseException('Berkas ini bukan file GPX (elemen akar harus <gpx>).');
                    }
                    $sawRoot = true;
                    continue;
                }

                if ($reader->localName === 'wpt') {
                    if (count($waypoints) >= $maxWaypoints) {
                        throw new GpxParseException("Jumlah waypoint melebihi batas {$maxWaypoints}.");
                    }
                    $waypoints[] = $this->readWaypoint($reader, count($waypoints) + 1);
                } elseif ($reader->localName === 'trk') {
                    $tracks[] = $this->readTrack($reader, count($tracks) + 1);
                }
            }
        } finally {
            $reader->close();
        }

        foreach (libxml_get_errors() as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                throw new GpxParseException('File GPX bukan XML yang valid.');
            }
        }

        if (! $sawRoot) {
            throw new GpxParseException('File GPX bukan XML yang valid.');
        }

        return ['waypoints' => $waypoints, 'tracks' => $tracks];
    }

    private function readWaypoint(XMLReader $reader, int $index): array
    {
        $point  = $this->readPoint($reader, 'waypoint', $index);
        $fields = $point['fields'];

        $name = mb_substr($fields['name'] ?? '', 0, 100);
        if ($name === '') {
            $name = 'Waypoint ' . $index;
        }

        $notes = $fields['desc'] ?? '';
        if ($notes === '') {
            $notes = $fields['cmt'] ?? '';
        }
        $notes = mb_substr($notes, 0, 500);

        return [
            'name'  => $name,
            'lat'   => $point['lat'],
            'lng'   => $point['lng'],
            'ele'   => $point['ele'],
            'notes' => $notes === '' ? null : $notes,
        ];
    }

    private function readTrack(XMLReader $reader, int $index): array
    {
        $maxPoints = (int) config('geolevel.outdoor.track_max_points');
        $name      = null;
        $points    = [];
        $depth     = $reader->depth;

        if (! $reader->isEmptyElement) {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) {
                    break;
                }
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                if ($reader->localName === 'name' && $reader->depth === $depth + 1) {
                    $name = trim($reader->readString());
                } elseif ($reader->localName === 'trkpt') {
                    if (count($points) >= $maxPoints) {
                        throw new GpxParseException("Track #{$index} melebihi batas {$maxPoints} titik.");
                    }
                    $point = $this->readPoint($reader, 'trkpt', count($points) + 1);
                    unset($point['fields']);
                    $points[] = $point;
                }
            }
        }

        $name = $name === null ? '' : mb_substr($name, 0, 150);

        return ['name' => $name === '' ? null : $name, 'points' => $points];
    }

    /**
     * Reads a wpt/trkpt element (reader positioned on its start tag) and
     * leaves the reader on its end tag.
     */
    private function readPoint(XMLReader $reader, string $label, int $index): array
    {
        $lat    = $reader->getAttribute('lat');
        $lng    = $reader->getAttribute('lon');
        $fields = [];

        if (! $reader->isEmptyElement) {
            $depth = $reader->depth;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) {
                    break;
                }
                if ($reader->nodeType === XMLReader::ELEMENT
                    && $reader->depth === $depth + 1
                    && in_array($reader->localName, ['name', 'ele', 'time', 'desc', 'cmt'], true)) {
                    $fields[$reader->localName] = trim($reader->readString());
                }
            }
        }

        $this->assertCoordinate($lat, 90, 'lat', $label, $index);
        $this->assertCoordinate($lng, 180, 'lon', $label, $index);

        $ele = $fields['ele'] ?? '';
        if ($ele !== '' && ! preg_match(self::DECIMAL, $ele)) {
            throw new GpxParseException("Elevasi tidak valid pada {$label} #{$index}.");
        }

        $time    = null;
        $rawTime = $fields['time'] ?? '';
        if ($rawTime !== '') {
            if (! preg_match(self::ISO_TIME, $rawTime)) {
                throw new GpxParseException("Waktu tidak valid pada {$label} #{$index}.");
            }
            try {
                $utc  = $this->utc ??= new DateTimeZone('UTC');
                $time = (new DateTimeImmutable($rawTime, $utc))->setTimezone($utc)->format('Y-m-d H:i:s.vP');
            } catch (Throwable) {
                throw new GpxParseException("Waktu tidak valid pada {$label} #{$index}.");
            }
        }

        return [
            'lat'         => $lat,
            'lng'         => $lng,
            'ele'         => $ele === '' ? null : $ele,
            'recorded_at' => $time,
            'fields'      => $fields,
        ];
    }

    private function assertCoordinate(?string $value, int $limit, string $attr, string $label, int $index): void
    {
        // Float is used only to compare against the range limit; the stored value stays a string.
        if ($value === null || ! preg_match(self::DECIMAL, $value) || abs((float) $value) > $limit) {
            throw new GpxParseException("Koordinat {$attr} tidak valid pada {$label} #{$index}.");
        }
    }
}
