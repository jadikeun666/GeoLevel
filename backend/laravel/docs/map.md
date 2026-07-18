# GeoLevel — Map Feature: Technical Specification

> Read this before implementing anything related to map, coordinates, GPS, or spatial visualization.
> This document is the single source of truth for all map-related development.

---

## Overview

GeoLevel Map Feature menambahkan visualisasi spasial untuk data survei waterpass.
Surveyor dapat melihat posisi BM, TP, dan titik-titik ukur lainnya di atas peta
nyata, lengkap dengan jalur pengukuran, jaring network, dan informasi elevasi.

**Prinsip utama:**
- Semua komponen peta menggunakan library dan tile yang **100% gratis selamanya**
- Koordinat bersifat **opsional** — proyek tetap berfungsi penuh tanpa koordinat
- Koordinat **terpisah** dari `readings` — disimpan di tabel `survey_points` sendiri
- Tidak ada dependency berbayar, tidak ada API key yang membutuhkan kartu kredit

---

## Stack Peta

| Komponen          | Teknologi                          | Lisensi      | Biaya      |
| ----------------- | ---------------------------------- | ------------ | ---------- |
| Map Library       | **MapLibre GL JS v4**              | BSD-3-Clause | Gratis     |
| Vue wrapper       | **maplibre-gl** (langsung, no wrapper) | —        | Gratis     |
| Tile Provider     | **OpenStreetMap via tile.openstreetmap.org** | ODbL | Gratis   |
| Tile fallback     | **Stadia Maps Free Tier** (200k req/bulan) | — | Gratis  |
| Geocoder/Search   | **Nominatim** (OpenStreetMap)      | ODbL         | Gratis     |
| GPX Parser        | **gpxparser** (npm)                | MIT          | Gratis     |
| Koordinat storage | PostgreSQL `NUMERIC(12,8)`         | —            | —          |

### Instalasi

```bash
# NPM packages
npm install maplibre-gl gpxparser

# Tidak butuh composer package tambahan untuk map
# GPX parsing dilakukan di frontend (Vue), bukan backend
```

### Import di Vue component

```js
import maplibregl from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
```

### Tile URL yang digunakan

```js
// Primary: OpenStreetMap (gratis, no API key)
const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

// Attribution wajib dicantumkan (syarat ODbL)
const ATTRIBUTION = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'

// Style object untuk MapLibre
const MAP_STYLE = {
  version: 8,
  sources: {
    osm: {
      type: 'raster',
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    },
  },
  layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
}
```

---

## Database Schema

### Tabel Baru: `survey_points`

```sql
CREATE TABLE survey_points (
  id              BIGSERIAL PRIMARY KEY,
  project_id      BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  point_name      VARCHAR(50) NOT NULL,
  lat             NUMERIC(12,8) NOT NULL,   -- latitude, 8 desimal = presisi ~1mm
  lng             NUMERIC(12,8) NOT NULL,   -- longitude, 8 desimal = presisi ~1mm
  point_type      VARCHAR(10) NOT NULL DEFAULT 'TP',  -- BM | TP | IS | CP
  elevation_ref   NUMERIC(12,4) NULL,       -- elevasi GPS (opsional, bukan hasil ukur)
  gps_accuracy_m  NUMERIC(6,3) NULL,        -- akurasi GPS dalam meter (dari file GPX)
  source          VARCHAR(20) NOT NULL DEFAULT 'manual', -- manual | gpx | picker
  notes           TEXT NULL,
  created_at      TIMESTAMP DEFAULT NOW(),
  updated_at      TIMESTAMP DEFAULT NOW(),
  UNIQUE (project_id, point_name)           -- satu nama titik, satu koordinat per proyek
);

CREATE INDEX idx_survey_points_project ON survey_points (project_id);
CREATE INDEX idx_survey_points_point_name ON survey_points (project_id, point_name);
```

**Catatan penting:**
- `point_name` di `survey_points` dilink ke `readings.point_name` secara logis
  (bukan FK fisik) — ini disengaja karena tidak semua titik ukur memiliki koordinat GPS
- `UNIQUE (project_id, point_name)` memastikan satu koordinat per titik per proyek
- Kolom `lat`/`lng` menggunakan `NUMERIC(12,8)` — bukan `FLOAT` sesuai presisi policy
- `elevation_ref` adalah elevasi GPS mentah dari alat, **bukan** hasil kalkulasi sipat datar

### Perubahan pada Tabel Lain

Tidak ada perubahan pada tabel yang sudah ada. `survey_points` sepenuhnya additive.

### Eloquent Model

```php
// app/Models/SurveyPoint.php
class SurveyPoint extends Model
{
    protected $fillable = [
        'project_id', 'point_name', 'lat', 'lng',
        'point_type', 'elevation_ref', 'gps_accuracy_m', 'source', 'notes',
    ];

    protected $casts = [
        'lat'            => 'decimal:8',
        'lng'            => 'decimal:8',
        'elevation_ref'  => 'decimal:4',
        'gps_accuracy_m' => 'decimal:3',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
```

---

## Arsitektur Komponen

### Struktur File Baru

```
resources/js/
├── Components/
│   ├── Map/
│   │   ├── SurveyMap.vue          ← komponen utama MapLibre GL
│   │   ├── LocationPicker.vue     ← modal klik-peta untuk input koordinat
│   │   ├── GpxImportModal.vue     ← modal import file GPX
│   │   └── MapLegend.vue          ← legenda marker dan warna
│   └── ... (existing components)
└── Pages/
    └── Projects/
        └── Show.vue               ← tambah tab "Peta" (existing file)

app/Http/Controllers/
└── SurveyPointController.php      ← CRUD koordinat titik

app/Http/Requests/
└── StoreSurveyPointRequest.php    ← validasi input koordinat

app/Models/
└── SurveyPoint.php                ← model baru

database/migrations/
└── 2026_xx_xx_create_survey_points_table.php
```

### Layer Tanggung Jawab

```
Vue (SurveyMap.vue)
  ↓ props: points[], legs[], readings[]
  ↓ merender MapLibre GL canvas
  ↓ menggabungkan data dari backend ke layer visual

SurveyPointController
  ↓ menerima request CRUD koordinat
  ↓ validasi via StoreSurveyPointRequest
  ↓ tidak ada kalkulasi engineering di sini

ProjectController (existing)
  ↓ tambahkan surveyPoints ke props Show.vue
  ↓ eager load: $project->load(['readings', 'computedElevations', 'surveyPoints', ...])
```

---

## API Routes

```php
// routes/web.php — tambahkan dalam group auth middleware
Route::prefix('projects/{project}')->group(function () {

    // Survey Points (koordinat GPS)
    Route::get('survey-points', [SurveyPointController::class, 'index'])
         ->name('survey-points.index');
    Route::post('survey-points', [SurveyPointController::class, 'store'])
         ->name('survey-points.store');
    Route::put('survey-points/{point}', [SurveyPointController::class, 'update'])
         ->name('survey-points.update');
    Route::delete('survey-points/{point}', [SurveyPointController::class, 'destroy'])
         ->name('survey-points.destroy');

    // Import GPX — parsing di frontend, endpoint ini hanya simpan hasil parse
    Route::post('survey-points/import', [SurveyPointController::class, 'importBatch'])
         ->name('survey-points.import');

});
```

### Response Format SurveyPointController

```json
// GET /projects/{id}/survey-points
{
  "data": [
    {
      "id": 1,
      "point_name": "BM-A",
      "lat": "-8.12345678",
      "lng": "115.12345678",
      "point_type": "BM",
      "elevation_ref": null,
      "gps_accuracy_m": null,
      "source": "manual",
      "notes": null
    }
  ]
}
```

---

## Komponen Vue: SurveyMap.vue

### Props Contract

```ts
// Props yang diterima SurveyMap.vue
interface Props {
  // Titik dengan koordinat GPS (dari survey_points)
  points: Array<{
    id: number
    point_name: string
    lat: number
    lng: number
    point_type: 'BM' | 'TP' | 'IS' | 'CP'
    elevation_ref: number | null
    gps_accuracy_m: number | null
    source: 'manual' | 'gpx' | 'picker'
    notes: string | null
  }>

  // Elevasi hasil kalkulasi (dari computed_elevations, join by point_name)
  elevations: Array<{
    point_name: string
    adjusted_elevation: number | null
    raw_elevation: number
    correction: number
    cumulative_distance: number
  }>

  // Network legs untuk overlay jaring (dari network_legs)
  networkLegs: Array<{
    from_point: string
    to_point: string
    distance_m: number
    corrected_delta_h: number | null
  }>

  // Status proyek (untuk warna network legs)
  projectStatus: 'draft' | 'calculated' | 'accepted' | 'rejected'

  // Apakah user boleh edit koordinat
  canEdit: boolean
}

// Emits
// 'point-clicked' → { point_name: string }
// 'request-add-point' → { lat: number, lng: number } (klik peta kosong)
```

### Visual Specification

**Marker per tipe titik:**

| Tipe | Ikon | Warna | Ukuran |
| ---- | ---- | ----- | ------ |
| BM   | ⭐ bintang | Kuning `#F59E0B` | 36px |
| TP   | ● lingkaran | Biru `#3B82F6` | 28px |
| IS   | ▲ segitiga | Hijau `#10B981` | 24px |
| CP   | ◆ berlian | Ungu `#8B5CF6` | 28px |

**Polyline urutan pengukuran:**
- Warna: Abu-abu `#6B7280`
- Lebar: 2px
- Dash: tidak (garis penuh)
- Urutan: ikut `sequence_no` dari `readings`

**Network legs overlay:**
- Accepted: Hijau `#10B981`, lebar 3px
- Rejected: Merah `#EF4444`, lebar 3px
- Draft/Calculated: Abu-abu `#9CA3AF`, lebar 2px, dashed

**Popup saat klik marker:**
```
┌─────────────────────────────┐
│ ● BM-A                 [×] │
│ ─────────────────────────── │
│ Tipe        : Benchmark     │
│ Elevasi     : 100.0000 m    │
│ Koreksi     : +0.000133 m   │
│ Jarak kumulatif: 0.000 m    │
│ Koordinat   : -8.1234, 115.1234 │
│ Akurasi GPS : ±2.5 m        │
│ ─────────────────────────── │
│ [Edit Koordinat]            │
└─────────────────────────────┘
```

### Behaviour

```
Saat map dimuat:
1. Render semua marker dari props.points
2. Buat polyline dari urutan readings (hanya titik yang ada di points)
3. Overlay network legs jika props.networkLegs.length > 0
4. fitBounds ke semua marker dengan padding 60px
5. Jika tidak ada marker → tampilkan Indonesia center (lat: -2.5, lng: 118.0), zoom: 5

Saat klik marker:
1. Buka popup dengan data elevasi dari props.elevations (join by point_name)
2. Emit 'point-clicked'

Saat klik peta kosong (canEdit = true):
1. Emit 'request-add-point' dengan lat/lng posisi klik
2. Parent (Show.vue) buka LocationPicker dengan koordinat pre-filled

Saat props.points berubah (reactivity):
1. Hapus semua marker lama
2. Render ulang marker baru
3. Update polyline
```

---

## Komponen Vue: LocationPicker.vue

Modal untuk input/edit koordinat satu titik. Berisi mini-map MapLibre yang bisa diklik.

### Props & Emits

```ts
interface Props {
  pointName: string          // nama titik yang sedang diedit
  initialLat: number | null  // null = kosong (tambah baru)
  initialLng: number | null
  pointType: 'BM' | 'TP' | 'IS' | 'CP'
}

// Emits
// 'save' → { lat: number, lng: number, point_type: string, notes: string }
// 'cancel' → void
```

### Fitur LocationPicker

1. **Mini-map interaktif** — klik di mana saja untuk set lokasi, marker bisa di-drag
2. **Input manual** — field lat/lng yang bisa diketik langsung
3. **Search lokasi** — input teks → query Nominatim → zoom ke hasil
4. **Tombol "Gunakan Lokasi Saya"** — Browser Geolocation API (gratis, tanpa API key)
5. **Koordinat realtime** — tampilkan lat/lng saat mouse hover di peta

### Nominatim Search (Geocoder Gratis)

```js
// Contoh implementasi search di LocationPicker.vue
const searchLocation = async (query) => {
  const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=5&countrycodes=id`
  const res = await fetch(url, {
    headers: { 'Accept-Language': 'id' }
  })
  return await res.json()
  // Response: [{ display_name, lat, lon, ... }]
}

// Wajib: tambahkan User-Agent header atau referer agar tidak diblok Nominatim
// Nominatim usage policy: max 1 request/detik, tidak untuk batch geocoding massal
```

---

## Komponen Vue: GpxImportModal.vue

Modal untuk upload dan parse file GPX dari GPS handheld.

### Alur Import

```
User upload .gpx file
  ↓
Parse di browser menggunakan gpxparser (npm)
  ↓
Ekstrak waypoints: { name, lat, lon, ele, time }
  ↓
Tampilkan tabel preview dengan checkbox
  ↓
Cocokkan nama waypoint dengan point_name di readings (auto-suggest)
  ↓
User konfirmasi → POST /projects/{id}/survey-points/import
  ↓
Backend simpan batch ke survey_points
  ↓
Inertia reload → map update
```

### Format GPX yang Didukung

```xml
<!-- Waypoint format (dari GPS handheld seperti Garmin, Trimble) -->
<wpt lat="-8.12345678" lon="115.12345678">
  <name>BM-A</name>
  <ele>100.5</ele>          <!-- elevation GPS, opsional -->
  <hdop>2.5</hdop>          <!-- akurasi horizontal, opsional -->
  <time>2024-01-15T08:30:00Z</time>
</wpt>
```

### Validasi GPX

- File harus berekstensi `.gpx`
- Maksimal 500 waypoints per file
- Setiap waypoint harus memiliki `name`, `lat`, dan `lon`
- `lat` range: -90 s/d 90 | `lng` range: -180 s/d 180
- Jika `name` waypoint tidak cocok dengan readings → tetap bisa diimpor, ditandai "tidak terhubung"

---

## Integrasi dengan Show.vue (Tab Peta)

### Perubahan pada ProjectController::show()

```php
// Tambahkan surveyPoints ke props yang dikirim ke Show.vue
public function show(Project $project): Response
{
    $project->load([
        'readings',
        'computedElevations',
        'activityLogs',
        'networkLegs',
        'surveyPoints',       // ← tambahan baru
    ]);

    return Inertia::render('Projects/Show', [
        // ... existing props ...
        'surveyPoints' => $project->surveyPoints,
    ]);
}
```

### Tab "Peta" di Show.vue

```
Tab order (yang ada sekarang):
[Bacaan] [Elevasi] [Grafik] [Aktivitas] [Jaring]

Setelah ditambah:
[Bacaan] [Elevasi] [Grafik] [Peta] [Aktivitas] [Jaring]
```

Konten tab Peta:
```
┌──────────────────────────────────────────────────────────┐
│ [🗺 Peta Survei]          [+ Tambah Titik] [📁 Import GPX] │
│ ──────────────────────────────────────────────────────── │
│                                                          │
│   [MAP CANVAS — full width, height: 500px]              │
│   - Marker BM/TP/IS/CP                                   │
│   - Polyline urutan pengukuran                           │
│   - Network legs overlay (jika ada)                      │
│   - Popup saat klik                                      │
│                                                          │
│ ──────────────────────────────────────────────────────── │
│ [Legenda]  ⭐ BM  ● TP  ▲ IS  ◆ CP                      │
│            — Jalur ukur   ── Network leg                 │
│ ──────────────────────────────────────────────────────── │
│ Titik dengan koordinat: 4 dari 6 titik                   │
│                                                          │
│ Titik BM-A  lat: -8.1234  lng: 115.1234  [Edit] [Hapus] │
│ Titik TP-1  lat: -8.1256  lng: 115.1289  [Edit] [Hapus] │
│ Titik TP-2  lat: -8.1278  lng: 115.1301  [Edit] [Hapus] │
│ Titik BM-B  lat: -8.1290  lng: 115.1345  [Edit] [Hapus] │
│                                                          │
│ Titik tanpa koordinat:                                   │
│ Titik AWAL-1  [+ Tambah Koordinat]                       │
│ Titik AWAL-2  [+ Tambah Koordinat]                       │
└──────────────────────────────────────────────────────────┘
```

---

## Overview Map di Projects/Index.vue

Peta kecil di halaman daftar proyek yang menampilkan semua proyek user
berdasarkan rata-rata koordinat titik-titiknya.

### Behaviour

```
Saat halaman Index dimuat:
1. Ambil rata-rata lat/lng dari survey_points per proyek
2. Proyek tanpa survey_points → tidak muncul di peta
3. Marker per proyek: warna sesuai status (draft=abu, accepted=hijau, rejected=merah)
4. Klik marker → navigasi ke Projects/Show proyek tersebut
5. Peta ditampilkan di atas tabel daftar proyek, tinggi 280px
```

### Props untuk Index map

```ts
// Dikirim dari ProjectController::index()
interface ProjectMapPoint {
  project_id: number
  project_name: string
  status: string
  center_lat: number   // AVG(lat) dari survey_points proyek ini
  center_lng: number   // AVG(lng)
  point_count: number  // jumlah titik dengan koordinat
}
```

### Query untuk center koordinat

```php
// Di ProjectController::index()
$mapPoints = Project::where('user_id', auth()->id())
    ->whereHas('surveyPoints')
    ->with(['surveyPoints' => fn($q) => $q->select('project_id', 'lat', 'lng')])
    ->get()
    ->map(fn($p) => [
        'project_id'   => $p->id,
        'project_name' => $p->name,
        'status'       => $p->status,
        'center_lat'   => $p->surveyPoints->avg('lat'),
        'center_lng'   => $p->surveyPoints->avg('lng'),
        'point_count'  => $p->surveyPoints->count(),
    ]);
```

---

## SurveyPointController — Spesifikasi Lengkap

```php
class SurveyPointController extends Controller
{
    // GET /projects/{project}/survey-points
    // Mengembalikan semua titik koordinat proyek
    public function index(Project $project): JsonResponse

    // POST /projects/{project}/survey-points
    // Simpan satu titik koordinat baru
    // Body: { point_name, lat, lng, point_type, notes, source }
    public function store(Project $project, StoreSurveyPointRequest $request): RedirectResponse

    // PUT /projects/{project}/survey-points/{point}
    // Update koordinat titik
    public function update(Project $project, SurveyPoint $point, StoreSurveyPointRequest $request): RedirectResponse

    // DELETE /projects/{project}/survey-points/{point}
    // Hapus koordinat titik (tidak hapus readings)
    public function destroy(Project $project, SurveyPoint $point): RedirectResponse

    // POST /projects/{project}/survey-points/import
    // Import batch dari hasil parse GPX di frontend
    // Body: { points: [{ point_name, lat, lng, point_type, elevation_ref, gps_accuracy_m }] }
    public function importBatch(Project $project, Request $request): RedirectResponse
}
```

### StoreSurveyPointRequest Rules

```php
return [
    'point_name' => ['required', 'string', 'max:50'],
    'lat'        => ['required', 'numeric', 'between:-90,90'],
    'lng'        => ['required', 'numeric', 'between:-180,180'],
    'point_type' => ['required', 'in:BM,TP,IS,CP'],
    'notes'      => ['nullable', 'string', 'max:500'],
    'source'     => ['required', 'in:manual,gpx,picker'],
    'elevation_ref'   => ['nullable', 'numeric'],
    'gps_accuracy_m'  => ['nullable', 'numeric', 'min:0'],
];
```

---

## Testing Standards untuk Map Feature

### Feature Tests (PHPUnit)

```
tests/Feature/
└── SurveyPointTest.php

Test cases wajib:
- test_can_store_survey_point_with_valid_coordinates
- test_rejects_survey_point_with_lat_out_of_range
- test_rejects_survey_point_with_lng_out_of_range
- test_enforces_unique_point_name_per_project
- test_can_update_survey_point_coordinates
- test_can_delete_survey_point_without_affecting_readings
- test_can_import_batch_survey_points
- test_import_skips_duplicate_point_names (upsert behavior)
- test_cannot_access_other_users_survey_points
- test_project_show_includes_survey_points_in_props
- test_project_index_includes_map_points
```

### Unit Tests

Tidak diperlukan unit test khusus untuk map feature — logika kalkulasi tidak
ada di sini. Semua koordinat hanya disimpan dan dikembalikan apa adanya.

### E2E Tests (Playwright)

```
tests/e2e/map.spec.ts

Test cases:
- map tab renders without survey points → shows empty state
- can open location picker and save coordinates
- survey points appear as markers after save
- can edit existing survey point coordinates
- can delete survey point
- gpx import modal opens and parses file
- imported gpx points appear on map
```

---

## Precision Policy untuk Koordinat

| Data              | PostgreSQL Type  | Presisi       | Setara di Bumi     |
| ----------------- | ---------------- | ------------- | ------------------ |
| Latitude          | NUMERIC(12,8)    | 0.00000001°   | ≈ 1.1 mm           |
| Longitude         | NUMERIC(12,8)    | 0.00000001°   | ≈ 0.7 mm (ekuator) |
| Elevasi GPS ref   | NUMERIC(12,4)    | 0.0001 m      | 0.1 mm             |
| Akurasi GPS       | NUMERIC(6,3)     | 0.001 m       | 1 mm               |

**Catatan:** Presisi `NUMERIC(12,8)` jauh melampaui kemampuan GPS handheld
biasa (±1-3 meter untuk GPS consumer, ±0.01-0.1 meter untuk GPS geodetik).
Ini disengaja untuk future-proofing jika GPS geodetik digunakan.

**Koordinat bukan `FLOAT`** — sesuai presisi policy keseluruhan GeoLevel.
Gunakan `NUMERIC` di PostgreSQL dan cast `decimal:8` di Eloquent.

---

## Batasan dan Catatan Penting

### OpenStreetMap Tile Usage Policy

- **Wajib** tampilkan attribution: `© OpenStreetMap contributors`
- Jangan lakukan tile fetching massal atau crawling
- Untuk production dengan traffic tinggi → pertimbangkan self-host tiles dengan
  [OpenMapTiles](https://openmaptiles.org/) (gratis untuk self-host)
- Stadia Maps free tier: 200.000 tile requests/bulan — cukup untuk development
  dan penggunaan terbatas

### Nominatim Usage Policy

- Maksimum **1 request per detik**
- Wajib cantumkan `User-Agent` atau `Referer` header
- Jangan gunakan untuk geocoding massal (ribuan query sekaligus)
- Untuk volume tinggi → gunakan Photon (self-hosted geocoder, gratis)

### Browser Geolocation API

- Hanya berfungsi di HTTPS (sudah terpenuhi di `geolevel.local`)
- User harus izinkan akses lokasi di browser
- Akurasi bergantung perangkat user — bisa dari GPS, WiFi, atau IP

### Koordinat Independen dari Pipeline Kalkulasi

Koordinat GPS **tidak mempengaruhi** kalkulasi elevasi. Pipeline sipat datar
tetap berjalan dari `readings` saja. `survey_points` murni untuk visualisasi.
Ini adalah keputusan arsitektur yang disengaja — koordinat bisa diinput kapan
saja tanpa mengganggu data ukur.

---

## Urutan Implementasi (Fase)

### Fase 1 — Foundation
**Target: Koordinat bisa disimpan dan diedit**

- [ ] Migration `survey_points`
- [ ] Model `SurveyPoint` + relasi ke `Project`
- [ ] `SurveyPointController` (index, store, update, destroy)
- [ ] `StoreSurveyPointRequest`
- [ ] Update `ProjectController::show()` — include `surveyPoints` di props
- [ ] Update `Project` model — tambah `hasMany(SurveyPoint::class)`
- [ ] Route baru di `web.php`
- [ ] Feature tests `SurveyPointTest.php`

### Fase 2 — Map Visualization
**Target: Peta tampil dengan marker dan polyline**

- [ ] `npm install maplibre-gl`
- [ ] `SurveyMap.vue` — MapLibre canvas + marker + polyline + popup
- [ ] `MapLegend.vue` — legenda ikon
- [ ] Tab "Peta" di `Show.vue` — integrasi `SurveyMap.vue`
- [ ] `LocationPicker.vue` — modal picker dengan mini-map
- [ ] Tombol "Tambah Koordinat" di daftar titik tanpa koordinat
- [ ] Network legs overlay di `SurveyMap.vue`
- [ ] E2E tests `map.spec.ts` (Fase 1 + 2)

### Fase 3 — GPX Import + Overview Map
**Target: Import GPS dan peta index**

- [ ] `npm install gpxparser`
- [ ] `GpxImportModal.vue` — upload + parse + preview + konfirmasi
- [ ] `SurveyPointController::importBatch()` — endpoint batch insert
- [ ] Mini-map di `Projects/Index.vue`
- [ ] Update `ProjectController::index()` — include `mapPoints` di props
- [ ] E2E tests tambahan untuk GPX import

---

## Referensi

- [MapLibre GL JS Docs](https://maplibre.org/maplibre-gl-js/docs/)
- [OpenStreetMap Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)
- [Nominatim API](https://nominatim.org/release-docs/latest/api/Search/)
- [GPX Format Specification](https://www.topografix.com/gpx.asp)
- [gpxparser npm](https://www.npmjs.com/package/gpxparser)
- [Browser Geolocation API — MDN](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation_API)
