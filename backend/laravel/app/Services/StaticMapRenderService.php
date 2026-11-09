<?php

namespace App\Services;

/**
 * Merender peta statis (PNG) dari data survey_points, computed_elevations,
 * dan network_legs — tanpa WebGL, untuk disisipkan ke field book PDF.
 *
 * Precision note: proyeksi Web Mercator di sini memakai PHP float murni,
 * konsisten dengan pengecualian yang sama diterapkan pada
 * LeastSquaresAdjustmentService (operasi matriks) — input/output data
 * (lat/lng) tetap presisi tinggi dari database, float hanya dipakai
 * internal untuk kalkulasi posisi pixel gambar.
 */
class StaticMapRenderService
{
    /** Ukuran tile standar slippy map (CARTO & Esri keduanya 256px). */
    private const TILE_SIZE = 256;

    /** Dimensi kanvas output (px) — proporsi cocok untuk disisipkan di A4 portrait. */
    private const CANVAS_WIDTH = 900;
    private const CANVAS_HEIGHT = 600;

    /** Padding di sekeliling bounding box titik, dalam pixel kanvas. */
    private const PADDING_PX = 50;

    private const MIN_ZOOM = 2;
    private const MAX_ZOOM = 18;

    /**
     * Threshold deteksi "tile seragam" -- fraksi minimum sample pixel yang
     * harus mirip satu sama lain (dalam toleransi warna) supaya tile itu
     * dianggap uniform/polos. Dipakai HANYA untuk sinyal agregat tingkat
     * peta (lihat hasLikelySparseCoverage()), TIDAK untuk mengganti tile
     * individual -- ocean/desert asli juga uniform secara visual, jadi
     * tidak aman untuk deteksi per-tile.
     */
    private const UNIFORM_TILE_PIXEL_MATCH_RATIO = 0.95;

    /** Toleransi warna (per kanal RGB) saat membandingkan sample pixel. */
    private const UNIFORM_TILE_COLOR_TOLERANCE = 6;

    /**
     * Kalau porsi tile yang berhasil fetch TAPI terdeteksi uniform/polos
     * melewati ambang ini, renderBaseMap() menganggap area basemap
     * kemungkinan besar minim cakupan data (lihat hasLikelySparseCoverage()).
     */
    private const SPARSE_COVERAGE_RATIO_THRESHOLD = 0.7;

    /** State per-render, direset di awal setiap renderBaseMap(). */
    private int $tilesFetchedCount = 0;
    private int $tilesUniformCount = 0;

    /**
     * Konversi longitude ke koordinat pixel dunia (world pixel) pada zoom tertentu.
     */
    public function lonToWorldPixelX(float $lon, int $zoom): float
    {
        $scale = self::TILE_SIZE * (2 ** $zoom);

        return ($lon + 180.0) / 360.0 * $scale;
    }

    /**
     * Konversi latitude ke koordinat pixel dunia (world pixel) pada zoom tertentu,
     * menggunakan rumus proyeksi Web Mercator standar (dipakai oleh semua slippy
     * map tile provider termasuk CARTO dan Esri).
     */
    public function latToWorldPixelY(float $lat, int $zoom): float
    {
        $scale = self::TILE_SIZE * (2 ** $zoom);
        $latRad = deg2rad($lat);
        $mercN = log(tan((M_PI / 4) + ($latRad / 2)));

        return (0.5 - ($mercN / (2 * M_PI))) * $scale;
    }

    /**
     * Hitung bounding box (lat/lng min-max) dari kumpulan titik.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public function computeBoundingBox(array $points): array
    {
        if (empty($points)) {
            // Fallback: pusat Indonesia, radius kecil — hanya dipakai jika
            // caller lupa mengecek array kosong dulu (seharusnya sudah di-guard
            // oleh pemanggil sebelum sampai ke sini).
            return [
                'minLat' => -6.5, 'maxLat' => -5.5,
                'minLng' => 106.3, 'maxLng' => 107.3,
            ];
        }

        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        return [
            'minLat' => min($lats),
            'maxLat' => max($lats),
            'minLng' => min($lngs),
            'maxLng' => max($lngs),
        ];
    }

    /**
     * Tentukan zoom level terbesar (paling detail) sedemikian rupa sehingga
     * seluruh bounding box masih muat di dalam kanvas (dikurangi padding).
     *
     * Pendekatan: coba dari MAX_ZOOM turun ke MIN_ZOOM, hitung lebar/tinggi
     * bounding box dalam world pixel pada tiap zoom, berhenti di zoom pertama
     * yang muat.
     */
    public function chooseZoom(array $bbox): int
    {
        $availableWidth = self::CANVAS_WIDTH - (2 * self::PADDING_PX);
        $availableHeight = self::CANVAS_HEIGHT - (2 * self::PADDING_PX);

        for ($zoom = self::MAX_ZOOM; $zoom >= self::MIN_ZOOM; $zoom--) {
            $x1 = $this->lonToWorldPixelX($bbox['minLng'], $zoom);
            $x2 = $this->lonToWorldPixelX($bbox['maxLng'], $zoom);
            // Ingat: pixel Y dunia terbalik (makin ke utara/lat besar, Y makin kecil).
            $y1 = $this->latToWorldPixelY($bbox['maxLat'], $zoom);
            $y2 = $this->latToWorldPixelY($bbox['minLat'], $zoom);

            $bboxWidthPx = abs($x2 - $x1);
            $bboxHeightPx = abs($y2 - $y1);

            if ($bboxWidthPx <= $availableWidth && $bboxHeightPx <= $availableHeight) {
                return $zoom;
            }
        }

        return self::MIN_ZOOM;
    }

    /**
     * Hitung layout lengkap: zoom terpilih, titik pixel dunia kiri-atas kanvas
     * (dipakai sub-langkah berikutnya untuk menentukan tile mana yang perlu
     * di-fetch), dan dimensi kanvas.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array{
     *     zoom: int,
     *     originWorldPxX: float,
     *     originWorldPxY: float,
     *     canvasWidth: int,
     *     canvasHeight: int,
     * }
     */
    public function computeLayout(array $points): array
    {
        $bbox = $this->computeBoundingBox($points);
        $zoom = $this->chooseZoom($bbox);

        // Titik tengah bounding box, dalam world pixel pada zoom terpilih.
        $centerLat = ($bbox['minLat'] + $bbox['maxLat']) / 2;
        $centerLng = ($bbox['minLng'] + $bbox['maxLng']) / 2;
        $centerWorldPxX = $this->lonToWorldPixelX($centerLng, $zoom);
        $centerWorldPxY = $this->latToWorldPixelY($centerLat, $zoom);

        // Origin (pixel dunia di pojok kiri-atas kanvas) = pusat dikurangi setengah kanvas.
        $originWorldPxX = $centerWorldPxX - (self::CANVAS_WIDTH / 2);
        $originWorldPxY = $centerWorldPxY - (self::CANVAS_HEIGHT / 2);

        return [
            'zoom' => $zoom,
            'originWorldPxX' => $originWorldPxX,
            'originWorldPxY' => $originWorldPxY,
            'canvasWidth' => self::CANVAS_WIDTH,
            'canvasHeight' => self::CANVAS_HEIGHT,
        ];
    }

    /**
     * Konversi satu titik lat/lng menjadi koordinat pixel di dalam kanvas
     * (bukan pixel dunia), berdasarkan layout yang sudah dihitung.
     */
    public function pointToCanvasPixel(float $lat, float $lng, array $layout): array
    {
        $worldPxX = $this->lonToWorldPixelX($lng, $layout['zoom']);
        $worldPxY = $this->latToWorldPixelY($lat, $layout['zoom']);

        return [
            'x' => $worldPxX - $layout['originWorldPxX'],
            'y' => $worldPxY - $layout['originWorldPxY'],
        ];
    }

    /**
     * Bangun URL tile untuk satu tile x/y/zoom, sesuai mode ('street' -> CARTO
     * Voyager, 'satellite' -> Esri World Imagery). Server CARTO 'a' dipakai
     * tetap (bukan round-robin a/b/c/d) karena fetch di sini sekuensial dari
     * backend, bukan banyak request paralel dari browser seperti MapLibre GL.
     */
    public function buildTileUrl(string $mode, int $zoom, int $tileX, int $tileY): string
    {
        if ($mode === 'satellite') {
            return "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{$zoom}/{$tileY}/{$tileX}";
        }

        return "https://a.basemaps.cartocdn.com/rastertiles/voyager/{$zoom}/{$tileX}/{$tileY}.png";
    }

    /**
     * Fetch satu tile dan decode menjadi GD image resource. Mengembalikan
     * null (bukan exception) kalau gagal -- dipanggil sekuensial per tile
     * saat compose, kegagalan satu tile tidak boleh menggagalkan seluruh
     * proses render peta.
     */
    public function fetchTileImage(string $url): \GdImage|null
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'GeoLevel/1.0 (+https://geolevel.local)',
            ])->timeout(8)->get($url);

            if (! $response->successful()) {
                \Illuminate\Support\Facades\Log::warning('StaticMapRenderService: tile fetch tidak sukses', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body_preview' => substr($response->body(), 0, 200),
                ]);

                return null;
            }

            $image = @imagecreatefromstring($response->body());

            if ($image === false) {
                \Illuminate\Support\Facades\Log::warning('StaticMapRenderService: response sukses tapi gagal decode sebagai gambar', [
                    'url'          => $url,
                    'content_type' => $response->header('Content-Type'),
                    'body_length'  => strlen($response->body()),
                ]);
            }

            return $image !== false ? $image : null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaticMapRenderService: exception saat fetch tile', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            report($e);

            return null;
        }
    }

    /**
     * Render basemap (composite dari tile-tile yang relevan) menjadi satu
     * canvas GD sesuai layout yang sudah dihitung computeLayout(). Tile yang
     * gagal di-fetch diisi warna solid abu-abu muda sebagai fallback, bukan
     * menggagalkan seluruh render.
     */
    public function renderBaseMap(array $layout, string $mode): \GdImage
    {
        $this->tilesFetchedCount = 0;
        $this->tilesUniformCount = 0;

        $canvas = imagecreatetruecolor($layout['canvasWidth'], $layout['canvasHeight']);
        $this->fillFallbackPattern($canvas, $layout['canvasWidth'], $layout['canvasHeight']);

        $zoom = $layout['zoom'];
        $maxTileIndex = (2 ** $zoom) - 1;

        $firstTileX = (int) floor($layout['originWorldPxX'] / self::TILE_SIZE);
        $firstTileY = (int) floor($layout['originWorldPxY'] / self::TILE_SIZE);
        $lastTileX = (int) floor(($layout['originWorldPxX'] + $layout['canvasWidth']) / self::TILE_SIZE);
        $lastTileY = (int) floor(($layout['originWorldPxY'] + $layout['canvasHeight']) / self::TILE_SIZE);

        for ($tileX = $firstTileX; $tileX <= $lastTileX; $tileX++) {
            for ($tileY = $firstTileY; $tileY <= $lastTileY; $tileY++) {
                // Lewati tile di luar batas dunia (bisa terjadi di zoom rendah
                // atau area dekat kutub) -- tetap tampil sebagai fallback abu-abu.
                if ($tileX < 0 || $tileY < 0 || $tileX > $maxTileIndex || $tileY > $maxTileIndex) {
                    continue;
                }

                $url = $this->buildTileUrl($mode, $zoom, $tileX, $tileY);
                $tileImage = $this->fetchTileImage($url);

                if ($tileImage === null) {
                    continue;
                }

                $this->tilesFetchedCount++;
                if ($this->isTileUniform($tileImage)) {
                    $this->tilesUniformCount++;
                }

                $destX = (int) round(($tileX * self::TILE_SIZE) - $layout['originWorldPxX']);
                $destY = (int) round(($tileY * self::TILE_SIZE) - $layout['originWorldPxY']);

                imagecopy($canvas, $tileImage, $destX, $destY, 0, 0, self::TILE_SIZE, self::TILE_SIZE);
            }
        }

        return $canvas;
    }

    /**
     * Isi kanvas dengan pola checkerboard abu-abu kontras (bukan warna flat)
     * sebagai fallback default sebelum tile ditempel. Kalau ada tile yang
     * gagal fetch ATAU lokasi memang tidak tercakup basemap CARTO/Esri
     * (umum terjadi untuk lokasi survei terpencil -- perkebunan, tambang,
     * dsb yang jarang ter-mapping OpenStreetMap), pola ini tetap terlihat
     * jelas sebagai kondisi "tidak ada data", bukan seperti halaman kosong
     * atau bug rendering.
     */
    /**
     * Sample sebuah grid 8x8 titik pixel pada tile, cek berapa fraksi yang
     * warnanya mirip satu sama lain (dalam UNIFORM_TILE_COLOR_TOLERANCE).
     * Kembalikan true kalau fraksi itu >= UNIFORM_TILE_PIXEL_MATCH_RATIO.
     *
     * PENTING -- ini TIDAK berarti tile "rusak" atau "tidak valid". Laut,
     * gurun, atau area datar luas juga bisa sampling uniform secara sah.
     * Nilai balik method ini HANYA dipakai untuk menghitung rasio agregat
     * di seluruh tile pada satu render (lihat hasLikelySparseCoverage()),
     * bukan untuk memutuskan nasib tile individual ini.
     */
    private function isTileUniform(\GdImage $tile): bool
    {
        $gridSize = 8;
        $tileWidth = imagesx($tile);
        $tileHeight = imagesy($tile);

        $samples = [];
        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                $px = (int) (($col + 0.5) / $gridSize * $tileWidth);
                $py = (int) (($row + 0.5) / $gridSize * $tileHeight);
                $px = min($px, $tileWidth - 1);
                $py = min($py, $tileHeight - 1);

                $rgb = imagecolorat($tile, $px, $py);
                $samples[] = [
                    ($rgb >> 16) & 0xFF,
                    ($rgb >> 8) & 0xFF,
                    $rgb & 0xFF,
                ];
            }
        }

        $reference = $samples[0];
        $matchCount = 0;

        foreach ($samples as $sample) {
            $diff = abs($sample[0] - $reference[0])
                + abs($sample[1] - $reference[1])
                + abs($sample[2] - $reference[2]);

            if ($diff <= self::UNIFORM_TILE_COLOR_TOLERANCE * 3) {
                $matchCount++;
            }
        }

        return ($matchCount / count($samples)) >= self::UNIFORM_TILE_PIXEL_MATCH_RATIO;
    }

    /**
     * Setelah renderBaseMap() dipanggil, method ini mengembalikan true kalau
     * porsi tile yang berhasil fetch tapi terdeteksi uniform/polos melewati
     * SPARSE_COVERAGE_RATIO_THRESHOLD -- sinyal agregat bahwa area basemap
     * ini kemungkinan besar minim cakupan data (lokasi survei terpencil),
     * BUKAN kepastian mutlak. Dipakai untuk menampilkan catatan advisory di
     * PDF, bukan untuk mengubah tile yang sudah dirender.
     *
     * Mengembalikan false kalau belum ada tile yang berhasil di-fetch sama
     * sekali (misal semua fetch gagal -- itu kasus fallback checkerboard
     * yang sudah ditangani terpisah, bukan kasus "valid tapi kosong").
     */
    public function hasLikelySparseCoverage(): bool
    {
        if ($this->tilesFetchedCount === 0) {
            return false;
        }

        return ($this->tilesUniformCount / $this->tilesFetchedCount) >= self::SPARSE_COVERAGE_RATIO_THRESHOLD;
    }

    private function fillFallbackPattern(\GdImage $canvas, int $width, int $height): void
    {
        $colorLight = imagecolorallocate($canvas, 235, 235, 235);
        $colorDark = imagecolorallocate($canvas, 205, 205, 205);

        imagefilledrectangle($canvas, 0, 0, $width, $height, $colorLight);

        $checkerSize = 20;
        for ($y = 0; $y < $height; $y += $checkerSize) {
            for ($x = 0; $x < $width; $x += $checkerSize) {
                $isDark = ((int) ($x / $checkerSize) + (int) ($y / $checkerSize)) % 2 === 0;
                if ($isDark) {
                    imagefilledrectangle($canvas, $x, $y, $x + $checkerSize - 1, $y + $checkerSize - 1, $colorDark);
                }
            }
        }
    }

    /**
     * Warna marker per point_type, konsisten dengan skema warna di
     * SurveyMap.vue (lihat docs/map.md -- "Marker Visual Spec").
     */
    private const MARKER_COLORS = [
        'BM' => [245, 158, 11],  // #F59E0B kuning
        'TP' => [59, 130, 246],  // #3B82F6 biru
        'IS' => [16, 185, 129],  // #10B981 hijau
        'CP' => [139, 92, 246],  // #8B5CF6 ungu
    ];

    /** Warna default kalau point_type tidak dikenali (seharusnya tidak terjadi). */
    private const MARKER_COLOR_FALLBACK = [107, 114, 128]; // abu-abu

    /** Warna garis polyline urutan pengukuran, sama seperti SurveyMap.vue. */
    private const ROUTE_LINE_COLOR = [220, 38, 38]; // #DC2626 merah

    /**
     * Warna garis network leg per status proyek, konsisten dengan
     * NETWORK_LEG_COLOR di SurveyMap.vue dan skema warna
     * ProjectsOverviewMap.vue.
     */
    private const NETWORK_LEG_COLORS = [
        'draft'      => [156, 163, 175], // abu-abu
        'calculated' => [59, 130, 246],  // biru
        'accepted'   => [16, 185, 129],  // hijau
        'rejected'   => [239, 68, 68],   // merah
    ];

    private const MARKER_RADIUS_PX = 7;
    private const OUTLINE_WIDTH_PX = 2;

    /**
     * Gambar satu marker (lingkaran solid + outline putih) di posisi
     * lat/lng tertentu, di atas canvas yang sudah berisi basemap.
     */
    public function drawMarker(\GdImage $canvas, float $lat, float $lng, string $pointType, array $layout): void
    {
        $pos = $this->pointToCanvasPixel($lat, $lng, $layout);
        $x = (int) round($pos['x']);
        $y = (int) round($pos['y']);

        [$r, $g, $b] = self::MARKER_COLORS[$pointType] ?? self::MARKER_COLOR_FALLBACK;
        $fillColor = imagecolorallocate($canvas, $r, $g, $b);
        $outlineColor = imagecolorallocate($canvas, 255, 255, 255);

        // Semua tipe titik berbentuk lingkaran; tipe dibedakan lewat warna.
        // Outline putih digambar sedikit lebih besar di belakang lingkaran
        // warna, supaya marker tetap kontras di atas basemap satelit yang ramai.
        $outerRadius = self::MARKER_RADIUS_PX + self::OUTLINE_WIDTH_PX;
        imagefilledellipse($canvas, $x, $y, $outerRadius * 2, $outerRadius * 2, $outlineColor);
        imagefilledellipse($canvas, $x, $y, self::MARKER_RADIUS_PX * 2, self::MARKER_RADIUS_PX * 2, $fillColor);
    }

    /**
     * Gambar satu segmen garis putus-putus antara dua titik lat/lng, dengan
     * outline putih tipis di baliknya supaya tetap terbaca di kedua mode peta.
     * Dipakai baik untuk polyline urutan pengukuran maupun network legs.
     */
    private function drawDashedLine(\GdImage $canvas, array $fromLatLng, array $toLatLng, array $layout, array $rgb, int $thickness = 3): void
    {
        $from = $this->pointToCanvasPixel($fromLatLng['lat'], $fromLatLng['lng'], $layout);
        $to = $this->pointToCanvasPixel($toLatLng['lat'], $toLatLng['lng'], $layout);

        [$r, $g, $b] = $rgb;
        $lineColor = imagecolorallocate($canvas, $r, $g, $b);
        $outlineColor = imagecolorallocate($canvas, 255, 255, 255);

        imagesetthickness($canvas, $thickness + 2);
        $this->drawDashedSegment($canvas, $from, $to, $outlineColor);

        imagesetthickness($canvas, $thickness);
        $this->drawDashedSegment($canvas, $from, $to, $lineColor);

        imagesetthickness($canvas, 1);
    }

    /**
     * Gambar segmen putus-putus mentah antara dua titik pixel kanvas.
     * GD tidak punya fungsi garis putus-putus bawaan yang presisi untuk
     * sudut sembarang, jadi diimplementasikan manual: bagi jarak jadi
     * segmen pendek berselang-seling gambar/lewati.
     */
    private function drawDashedSegment(\GdImage $canvas, array $from, array $to, int $color): void
    {
        $dashLength = 8.0;
        $gapLength = 6.0;

        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];
        $distance = sqrt(($dx * $dx) + ($dy * $dy));

        if ($distance < 0.01) {
            return;
        }

        $unitX = $dx / $distance;
        $unitY = $dy / $distance;

        $traveled = 0.0;
        $drawing = true;

        while ($traveled < $distance) {
            $segmentLength = $drawing ? $dashLength : $gapLength;
            $segmentEnd = min($traveled + $segmentLength, $distance);

            if ($drawing) {
                $x1 = (int) round($from['x'] + ($unitX * $traveled));
                $y1 = (int) round($from['y'] + ($unitY * $traveled));
                $x2 = (int) round($from['x'] + ($unitX * $segmentEnd));
                $y2 = (int) round($from['y'] + ($unitY * $segmentEnd));

                imageline($canvas, $x1, $y1, $x2, $y2, $color);
            }

            $traveled = $segmentEnd;
            $drawing = ! $drawing;
        }
    }

    /**
     * Gambar polyline urutan pengukuran (garis merah) menghubungkan
     * survey_points sesuai sequence_no yang di-lookup dari
     * computed_elevations -- sama seperti buildOrderedCoords() di
     * SurveyMap.vue. Titik tanpa entri di elevations otomatis ter-skip.
     *
     * @param  array<int, array{point_name: string, lat: float, lng: float}>  $points
     * @param  array<int, array{point_name: string, sequence_no: int}>  $elevations
     */
    public function drawRoute(\GdImage $canvas, array $points, array $elevations, array $layout): void
    {
        $sequenceByName = [];
        foreach ($elevations as $elevation) {
            // Sama seperti findElevation() di SurveyMap.vue: kalau ada
            // beberapa entri untuk nama titik yang sama, pakai yang
            // sequence_no tertinggi (entri terakhir).
            $sequenceByName[$elevation['point_name']] = $elevation['sequence_no'];
        }

        $ordered = array_values(array_filter($points, fn ($p) => isset($sequenceByName[$p['point_name']])));

        usort($ordered, fn ($a, $b) => $sequenceByName[$a['point_name']] <=> $sequenceByName[$b['point_name']]);

        for ($i = 0; $i < count($ordered) - 1; $i++) {
            $this->drawDashedLine(
                $canvas,
                ['lat' => $ordered[$i]['lat'], 'lng' => $ordered[$i]['lng']],
                ['lat' => $ordered[$i + 1]['lat'], 'lng' => $ordered[$i + 1]['lng']],
                $layout,
                self::ROUTE_LINE_COLOR,
                3
            );
        }
    }

    /**
     * Gambar overlay network legs (garis antar titik jaring), warna
     * mengikuti status proyek keseluruhan -- NetworkLeg tidak punya kolom
     * status per-leg, sama seperti NETWORK_LEG_COLOR di SurveyMap.vue.
     * Leg yang salah satu titiknya belum punya koordinat otomatis di-skip.
     *
     * @param  array<int, array{point_name: string, lat: float, lng: float}>  $points
     * @param  array<int, array{from_point: string, to_point: string}>  $networkLegs
     */
    public function drawNetworkLegs(\GdImage $canvas, array $points, array $networkLegs, array $layout, string $projectStatus): void
    {
        $coordsByName = [];
        foreach ($points as $point) {
            $coordsByName[$point['point_name']] = ['lat' => $point['lat'], 'lng' => $point['lng']];
        }

        $rgb = self::NETWORK_LEG_COLORS[$projectStatus] ?? self::NETWORK_LEG_COLORS['draft'];

        foreach ($networkLegs as $leg) {
            $from = $coordsByName[$leg['from_point']] ?? null;
            $to = $coordsByName[$leg['to_point']] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $this->drawDashedLine($canvas, $from, $to, $layout, $rgb, 2);
        }
    }
}
