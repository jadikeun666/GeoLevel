# GeoLevel — Map Feature: Technical Specification

> Read this before implementing anything related to map, coordinates, GPS, or spatial visualization.
> This document is the single source of truth for all map-related development.

---

## Overview

GeoLevel Map Feature menambahkan visualisasi spasial untuk data survei waterpass.
Surveyor dapat melihat posisi BM, TP, dan titik-titik ukur lainnya di atas peta
nyata (mode jalan maupun citra satelit), lengkap dengan koordinat manual, import
GPX, jalur pengukuran, overlay jaring, dan overview peta di halaman daftar proyek.

**Prinsip utama:**
- Semua komponen peta menggunakan library dan tile yang **100% gratis selamanya**
- Koordinat bersifat **opsional** — proyek tetap berfungsi penuh tanpa koordinat
- Koordinat **terpisah** dari `readings` — disimpan di tabel `survey_points` sendiri
- Tidak ada dependency berbayar, tidak ada API key yang membutuhkan kartu kredit
- Komponen peta (berat karena WebGL) selalu **lazy-loaded**, tidak pernah masuk
  bundle utama aplikasi

**Status keseluruhan (per sesi terakhir): Fase 1, 2, 3, DAN Export PDF Peta
SEMUA SELESAI.** Gambar peta (basemap + marker + polyline + network legs)
sekarang masuk ke field book PDF via `StaticMapRenderService` (render backend
murni PHP GD, tanpa WebGL), dengan pilihan mode Peta/Satelit dari `Show.vue`.
Lihat section "Export PDF Peta" di bawah untuk detail lengkap.

---

## Stack Peta

| Komponen          | Teknologi                          | Lisensi      | Biaya      |
| ----------------- | ----------------------------------- | ------------ | ---------- |
| Map Library       | **MapLibre GL JS v5.24.0**         | BSD-3-Clause | Gratis     |
| Vue wrapper       | **maplibre-gl** (langsung, no wrapper) | —        | Gratis     |
| Tile Provider (jalan) | **CARTO Basemaps (Voyager)**   | ODbL + CARTO attribution | Gratis |
| Tile Provider (satelit) | **Esri World Imagery**       | Esri attribution | Gratis (standard usage) |
| Geocoder/Search   | **Nominatim** (OpenStreetMap)      | ODbL         | Gratis     |
| GPX Parser        | **gpxparser** (npm, v3.0.8)        | MIT          | Gratis     |
| Koordinat storage | PostgreSQL `NUMERIC(12,8)`         | —            | —          |

### ⚠️ PERUBAHAN PENTING: tile provider bukan lagi `tile.openstreetmap.org`

Spesifikasi awal dokumen ini menyebut `tile.openstreetmap.org` sebagai tile
provider utama. **Ini sudah diganti** setelah ditemukan bug produksi nyata:
MapLibre GL menggambar tile lewat WebGL (texture), dan WebGL **mewajibkan**
header `Access-Control-Allow-Origin` (CORS) dari server tile. Server resmi
`tile.openstreetmap.org` tidak konsisten mengirim header itu, menyebabkan
sebagian tile gagal fetch total (`ERR_FAILED`, error `AJAXError` di console)
dan peta tampil kosong/parsial secara acak — bukan soal gaya visual, tapi bug
loading yang nyata.

**Solusi yang dipakai sekarang:** dua tile source terpisah, dipilih lewat
toggle "Peta" / "Satelit" di semua komponen peta:

1. **CARTO Basemaps (Voyager)** — mode jalan/vektor-style, gratis selamanya,
   tanpa API key, kirim CORS header dengan benar.
2. **Esri World Imagery** — mode satelit (foto udara asli, bukan peta vektor),
   gratis untuk pemakaian standar tanpa API key, kirim CORS header dengan benar.

Kedua tile provider ini adalah pengganti langsung, bukan tambahan opsional —
jangan kembalikan ke `tile.openstreetmap.org` untuk MapLibre GL.

### Instalasi

```bash
# NPM packages
npm install maplibre-gl gpxparser

# Tidak butuh composer package tambahan untuk map
# GPX parsing dilakukan di frontend (Vue), bukan backend
```

### Style config terpusat: `resources/js/Components/Map/mapStyle.js`

Semua komponen peta (`SurveyMap.vue`, `LocationPicker.vue`,
`ProjectsOverviewMap.vue`) mengimpor style dari satu file bersama — **jangan**
duplikasi objek style di masing-masing komponen.

```js
// resources/js/Components/Map/mapStyle.js
export const INDONESIA_CENTER = [118.0, -2.5]

export const STREET_STYLE = {
  version: 8,
  sources: {
    carto: {
      type: 'raster',
      tiles: [
        'https://a.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        'https://b.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        'https://c.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        'https://d.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
      ],
      tileSize: 256,
      attribution: CARTO_ATTRIBUTION,
    },
  },
  layers: [{ id: 'carto', type: 'raster', source: 'carto' }],
}

export const SATELLITE_STYLE = {
  version: 8,
  sources: {
    esri: {
      type: 'raster',
      tiles: [
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      ],
      tileSize: 256,
      attribution: ESRI_ATTRIBUTION,
    },
  },
  layers: [{ id: 'esri', type: 'raster', source: 'esri' }],
}

export const MAP_STYLES = { street: STREET_STYLE, satellite: SATELLITE_STYLE }
```

### Toggle Peta/Satelit — pola implementasi standar (UPDATED — lihat bug fix di bawah)

Setiap komponen peta yang bikin instance `maplibregl.Map` wajib pakai pola ini:

```js
import { MAP_STYLES, INDONESIA_CENTER } from './mapStyle'

const mapStyleMode = ref('street') // 'street' | 'satellite'

function setMapStyle(mode) {
  if (mapStyleMode.value === mode || !map) return
  mapStyleMode.value = mode
  map.setStyle(MAP_STYLES[mode])
}

// saat init:
map = new maplibregl.Map({
  container: mapContainer.value,
  style: MAP_STYLES[mapStyleMode.value],
  // ...
})
```

Marker adalah elemen DOM terpisah (bukan bagian dari style), jadi otomatis
tetap terlihat saat `setStyle()` dipanggil — tidak perlu re-render marker saat
toggle mode.

**⚠️ PENTING — untuk komponen yang menambahkan GeoJSON source/layer custom
(polyline, network legs, dll — bukan sekadar marker DOM):** pola di atas
**tidak cukup**. `setStyle()` menghapus SEMUA source/layer custom (bukan
marker DOM), dan menunggu event `map.once('style.load', ...)` untuk
menambahkannya kembali **rawan race condition** — event itu kadang sudah
selesai fire sebelum listener terpasang. `SurveyMap.vue` memakai pola
polling yang lebih robust:

```js
function waitForStyleReady(callback, attemptsLeft = 40) {
  if (!map) return
  if (map.isStyleLoaded()) {
    callback()
    return
  }
  if (attemptsLeft <= 0) {
    console.error('Timeout menunggu style siap.')
    return
  }
  requestAnimationFrame(() => waitForStyleReady(callback, attemptsLeft - 1))
}

function setMapStyle(mode) {
  if (mapStyleMode.value === mode || !map) return
  mapStyleMode.value = mode
  map.setStyle(MAP_STYLES[mode])
  waitForStyleReady(() => {
    renderRoute()
    renderNetworkLegs()
  })
}
```

**Wajib dipakai** untuk fitur peta apa pun ke depan yang menambahkan GeoJSON
source/layer di atas `map.setStyle()` — jangan pakai `once('style.load', ...)`.
Detail lengkap kejadian bug ini: lihat `docs/claude.md` → "Known Fixes
Applied" → entri "`map.setStyle()` + `once('style.load', ...)` race condition".

### CSS MapLibre — tetap via CDN, bukan Vite

**Tidak berubah** dari keputusan sebelumnya (lihat `claude.md` → "Known Fixes
Applied"): CSS MapLibre dimuat via `<link>` tag ke CDN jsDelivr di
`resources/views/app.blade.php`, bukan `import` di SFC Vue atau `@import` di
`resources/css/app.css`. Ini menghindari konflik dengan pipeline PostCSS/Tailwind.

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@5.24.0/dist/maplibre-gl.css" />
```

Versi di URL CDN harus disinkronkan manual dengan versi `maplibre-gl` di
`package.json` setiap kali di-upgrade.

### Code-splitting — semua komponen peta wajib lazy-loaded

`maplibre-gl` adalah library besar (~1MB unminified). Semua komponen peta
**wajib** diimpor lewat `defineAsyncComponent`, bukan `import` statis biasa,
supaya kode itu tidak pernah masuk bundle utama halaman:

```js
import { defineAsyncComponent } from 'vue'
const SurveyMap = defineAsyncComponent(() => import('@/Components/Map/SurveyMap.vue'))
const LocationPicker = defineAsyncComponent(() => import('@/Components/Map/LocationPicker.vue'))
const GpxImportModal = defineAsyncComponent(() => import('@/Components/Map/GpxImportModal.vue'))
const ProjectsOverviewMap = defineAsyncComponent(() => import('@/Components/Map/ProjectsOverviewMap.vue'))
const MapLegend = defineAsyncComponent(() => import('@/Components/Map/MapLegend.vue'))
```

Hasil nyata dari penerapan pola ini: `Show-*.js` turun dari **1,299 kB → 260 kB**
setelah lazy-loading diterapkan. `vite.config.js` juga menaikkan
`chunkSizeWarningLimit` ke `1200` karena chunk `mapStyle-*.js` (berisi
`maplibre-gl` itu sendiri, ~1MB) memang wajar besar untuk library WebGL, dan
sudah dipastikan hanya di-download saat komponen peta benar-benar mount.

```js
// vite.config.js
build: {
    chunkSizeWarningLimit: 1200,
},
```

---

## Database Schema

### Tabel: `survey_points`

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

### Tabel: `network_legs` (relevan untuk overlay peta)

Lihat detail lengkap struktur tabel di `docs/database.md` dan section "Loop
Network Least Squares" di `docs/claude.md`. Kolom yang relevan untuk fitur
peta: `from_point`, `to_point` (nama titik, dicocokkan ke `survey_points.point_name`
untuk mendapatkan koordinat), `residual` (indikator kualitas fit numerik).

**Penting:** `NetworkLeg` **tidak punya kolom `status` per-leg**. Warna
overlay garis jaring di peta mengikuti **status proyek** secara keseluruhan
(`draft`/`calculated`/`accepted`/`rejected`), bukan status per-leg individual.

### Perubahan pada Tabel Lain

Tidak ada perubahan pada tabel yang sudah ada selain `network_legs` (sudah
ada sebelumnya, sekarang dipakai juga oleh fitur peta). `survey_points`
sepenuhnya additive.

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

`Project::surveyPoints(): HasMany` sudah ada dan dipakai baik untuk `Show.vue`
props maupun query `mapPoints` di `ProjectController::index()`.

---

## Arsitektur Komponen — Status Aktual

### Struktur File (kondisi sekarang, sudah lengkap — SEMUA komponen selesai)
resources/js/
├── Components/
│ ├── Map/
│ │ ├── mapStyle.js ← style config bersama (STREET/SATELLITE) ✅
│ │ ├── SurveyMap.vue ← peta utama: marker, toggle, polyline, ✅
│ │ │ popup, network overlay
│ │ ├── LocationPicker.vue ← modal input/edit koordinat + mini-map ✅
│ │ │ + marker referensi
│ │ ├── GpxImportModal.vue ← modal import file GPX ✅
│ │ ├── ProjectsOverviewMap.vue ← mini-map di halaman daftar proyek ✅
│ │ └── MapLegend.vue ← legenda ikon marker + garis ✅
│ └── ... (existing components)
└── Pages/
└── Projects/
├── Show.vue ← tab "Peta" terintegrasi penuh ✅
└── Index.vue ← mini-map overview terintegrasi ✅

app/Http/Controllers/
├── SurveyPointController.php ← CRUD + importBatch, semua lengkap ✅
└── ProjectController.php ← index() kirim mapPoints, show() kirim
surveyPoints ✅

app/Http/Requests/
└── StoreSurveyPointRequest.php ✅

app/Models/
├── SurveyPoint.php ✅
└── NetworkLeg.php ← sudah ada sebelumnya, dipakai overlay ✅

database/seeders/
└── MapDemoSeeder.php ← seeder dev/test khusus map (baru) ✅

database/migrations/
└── (migration survey_points sudah dijalankan) ✅

tests/e2e/
└── map.spec.ts ← 5 test e2e ✅

### Layer Tanggung Jawab (tidak berubah dari desain awal)
Vue (SurveyMap.vue / ProjectsOverviewMap.vue)
↓ props: points[], mapPoints[], elevations[], networkLegs[]
↓ merender MapLibre GL canvas (lazy-loaded)
↓ menggabungkan data dari backend ke layer visual
↓ (baru) join elevations by point_name untuk popup
↓ (baru) join networkLegs from_point/to_point ke survey_points untuk overlay

SurveyPointController
↓ menerima request CRUD koordinat + import batch
↓ validasi via StoreSurveyPointRequest
↓ tidak ada kalkulasi engineering di sini

ProjectController (existing)
↓ show() — kirim surveyPoints, elevations, networkLegs ke Show.vue
↓ index() — kirim mapPoints (agregat AVG lat/lng per proyek) ke Index.vue
---

## API Routes (aktual, terverifikasi via `route:list`)

```php
// routes/web.php
Route::get   ('/projects/{project}/survey-points',         [SurveyPointController::class, 'index'])->name('survey-points.index');
Route::post  ('/projects/{project}/survey-points',         [SurveyPointController::class, 'store'])->name('survey-points.store');
Route::put   ('/projects/{project}/survey-points/{point}', [SurveyPointController::class, 'update'])->name('survey-points.update');
Route::delete('/projects/{project}/survey-points/{point}', [SurveyPointController::class, 'destroy'])->name('survey-points.destroy');
Route::post  ('/projects/{project}/survey-points/import',  [SurveyPointController::class, 'importBatch'])->name('survey-points.import');
```

Pemanggilan dari Vue **selalu** lewat Ziggy `route()` helper, mengikuti pola
yang sudah dipakai di seluruh `Show.vue` — jangan pakai template string URL manual:

```js
router.post(route('survey-points.store', props.project.id), body, { ... })
router.put(route('survey-points.update', { project: props.project.id, point: id }), body, { ... })
router.delete(route('survey-points.destroy', { project: props.project.id, point: id }), { ... })
```

---

## Komponen Vue: SurveyMap.vue — SELESAI ✅ (semua sub-fitur Fase 2 lengkap)

### Props Contract (implementasi aktual)

```ts
interface Props {
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
  elevations: Array<{
    sequence_no: number
    point_name: string
    hi: number | null
    raw_elevation: number
    correction: number
    adjusted_elevation: number
    cumulative_distance: number
    // ...field lain dari ComputedElevation, lihat database.md
  }>
  networkLegs: Array<{
    from_point: string
    to_point: string
    observed_delta_h: number
    distance_m: number
    corrected_delta_h: number
    residual: number
  }>
  projectStatus: 'draft' | 'calculated' | 'accepted' | 'rejected'
  canEdit: boolean                // sekarang selalu true dari Show.vue
}

// Emits
// 'point-clicked' → { point_name: string }             (jalan, TIDAK dipakai lagi untuk popup — lihat catatan)
// 'request-add-point' → { lat, lng }                    (ditangani Show.vue::handleRequestAddPoint — klik peta kosong)
// 'edit-point-requested' → SurveyPoint (objek penuh)     (BARU — dari tombol "Edit Koordinat" di popup)
```

**Semua sub-fitur berikut sudah selesai dan terverifikasi visual:**

1. **Render marker** warna sesuai `point_type`, `fitBounds` otomatis, toggle
   Peta/Satelit — (sudah selesai sejak Fase 2 inti sesi sebelumnya)
2. **Polyline urutan pengukuran** — garis merah (`#DC2626`, width 3, dashed)
   menghubungkan titik sesuai `sequence_no` yang di-lookup dari `elevations`
   (bukan prop terpisah — fungsi `buildOrderedCoords()` membangun `Map`
   `point_name → sequence_no` dari `props.elevations`, lalu urutkan
   `props.points` berdasarkan itu). Titik tanpa entri di `elevations` (atau
   tanpa koordinat) otomatis ter-skip.
3. **Popup info marker** — `maplibregl.Popup` bawaan, dipasang via
   `.setPopup()` ke tiap marker. Isi: nama titik, tipe (label Indonesia),
   elevasi terkoreksi, koreksi, jarak kumulatif, koordinat, akurasi GPS,
   tombol "Edit Koordinat". Data elevasi diambil via `findElevation(pointName)`
   yang mengambil entri **terakhir** (sequence_no tertinggi) kalau ada
   beberapa entri untuk nama titik yang sama (umum terjadi — satu titik bisa
   punya baris BS dan FS terpisah di `computed_elevations`). Tombol Edit
   meng-emit `edit-point-requested` dengan objek `point` (SurveyPoint) penuh,
   ditangani `Show.vue` via `@edit-point-requested="openEditPoint"`.
4. **Network legs overlay** — garis GeoJSON `LineString` per leg, warna
   mengikuti `NETWORK_LEG_COLOR[props.projectStatus]` (draft=abu, calculated=biru,
   accepted=hijau, rejected=merah). Koordinat leg didapat lookup `from_point`/
   `to_point` terhadap `props.points` — leg yang salah satu titiknya belum
   punya koordinat otomatis di-skip (tidak error, hanya tidak digambar).

**Robust terhadap toggle Peta/Satelit** — lihat entri bug fix "`map.setStyle()`
+ `once('style.load', ...)` race condition" di `docs/claude.md`. Kedua layer
GeoJSON (`route-line`, `network-legs-line`) di-render ulang via
`waitForStyleReady()` setelah setiap toggle mode.

### Marker Visual Spec (keputusan desain: semua lingkaran, tipe dibedakan lewat warna)

| Tipe | Warna implementasi sekarang | Catatan |
| ---- | ----- | ------- |
| BM   | `#F59E0B` (kuning) | lingkaran, ukuran terbesar (30px) |
| TP   | `#3B82F6` (biru) | bentuk lingkaran |
| IS   | `#10B981` (hijau) | lingkaran, ukuran terkecil (20px) |
| CP   | `#8B5CF6` (ungu) | lingkaran (24px) |

Bentuk ikon custom (bintang/segitiga/berlian) **sengaja TIDAK dipakai**:
sempat diimplementasikan lalu dikembalikan ke lingkaran demi kesan profesional.
Jangan diusulkan ulang tanpa persetujuan. Aturan yang sama berlaku di
`MapLegend.vue` dan `StaticMapRenderService::drawMarker()` (PDF).

---

## Komponen Vue: LocationPicker.vue — SELESAI ✅ (+ marker referensi baru)

### Props & Emits (implementasi aktual)

```ts
interface Props {
  pointName: string
  initialLat: number | null
  initialLng: number | null
  pointType: 'BM' | 'TP' | 'IS' | 'CP'
  referencePoints: Array<{ point_name: string, lat: number, lng: number, ... }>  // BARU
}
// Emits: 'save' → { lat, lng, point_type, notes }
// Emits: 'cancel' → void
```

### Fitur yang sudah jalan
1. Mini-map interaktif — klik untuk set lokasi, marker draggable
2. Input manual lat/lng dengan `step="0.00000001"`
3. Search lokasi via Nominatim (`countrycodes=id`, `Accept-Language: id`)
4. Tombol "Gunakan Lokasi Saya" — Browser Geolocation API
5. Koordinat realtime saat hover di peta
6. Toggle Peta/Satelit (sama seperti `SurveyMap.vue`)
7. **BARU — Marker referensi:** semua titik di `props.referencePoints` yang
   **bukan** titik yang sedang diedit (`p.point_name !== props.pointName`)
   ditampilkan sebagai marker kecil (12px, abu-abu `#94A3B8`, opacity 0.75)
   dengan label nama titik di bawahnya, non-draggable, tidak bisa diklik.
   Tujuan: user tahu posisi relatif titik lain saat menempatkan koordinat
   titik baru. Dipanggil `Show.vue` dengan `:reference-points="surveyPoints"`.
   Fungsi: `renderReferenceMarkers()`, dipanggil ulang saat map `load` (marker
   DOM tidak perlu re-render saat toggle style, sama seperti marker utama di
   `SurveyMap.vue`). Cleanup di `onBeforeUnmount`.

Dipasang di `Show.vue` lewat `v-if="showLocationPicker"`, dipicu dari tombol
"+ Tambah Koordinat" (state baru), tombol "Edit" di daftar titik (state
existing dengan id), atau tombol "Edit Koordinat" di popup marker peta
(state existing, via `@edit-point-requested`).

---

## Komponen Vue: GpxImportModal.vue — SELESAI ✅ (tidak berubah sesi ini)

### Props & Emits (implementasi aktual)

```ts
interface Props {
  projectId: number | string
  knownPointNames: string[]   // dari uniquePointNames di Show.vue, untuk matching
}
// Emits: 'imported' → void   (setelah submit sukses)
// Emits: 'cancel' → void
```

### Alur
User upload .gpx file
↓
Parse di browser via gpxparser (readAsText + FileReader)
↓
Filter waypoint: wajib punya name + lat [-90,90] + lng [-180,180]
↓ (invalid → masuk skippedCount, ditampilkan sebagai info, bukan error blocking)
Cap di 500 waypoint (MAX_WAYPOINTS)
↓
Tampilkan tabel preview dengan checkbox (default semua tercentang)
↓
Cocokkan wp.name terhadap knownPointNames → kolom status "Terhubung ke bacaan" / "Tidak terhubung"
↓
guessPointType(name) — tebak BM/CP/IS dari prefix nama, default TP
↓
User klik "Impor N Titik" → router.post(route('survey-points.import', projectId), { points: [...] })
↓
Backend simpan batch (SurveyPointController::importBatch)
↓
Inertia reload otomatis → marker baru muncul di peta + daftar titik

**Terverifikasi manual:** upload file GPX random dari internet, waypoint
dengan nama tidak cocok ditandai "Tidak terhubung" (perilaku benar), submit
sukses, marker muncul di peta dan daftar "Titik dengan koordinat".

---

## Komponen Vue: ProjectsOverviewMap.vue — SELESAI ✅ (tidak berubah sesi ini)

Mini-map di halaman `Projects/Index.vue`, menampilkan 1 marker per proyek
berdasarkan rata-rata koordinat `survey_points` proyek tersebut.

### Props

```ts
interface Props {
  mapPoints: Array<{
    project_id: number
    project_name: string
    status: 'draft' | 'calculated' | 'accepted' | 'rejected'
    center_lat: number
    center_lng: number
    point_count: number
  }>
}
```

### Warna marker berdasarkan status (implementasi aktual)

| Status | Warna |
| ------ | ----- |
| `draft` | `#9CA3AF` abu-abu |
| `calculated` | `#3B82F6` biru |
| `accepted` | `#10B981` hijau |
| `rejected` | `#EF4444` merah |

**Catatan:** skema warna ini identik dengan `NETWORK_LEG_COLOR` di
`SurveyMap.vue` — kalau salah satunya diubah ke depan, pertimbangkan
mengubah keduanya supaya konsisten di seluruh aplikasi.

### Behaviour
- Klik marker → `router.visit(route('projects.show', project_id))`
- Proyek tanpa `survey_points` tidak muncul di peta (backend filter `whereHas`)
- `fitBounds` otomatis ke semua marker, atau center Indonesia jika kosong
- Div pembungkus punya `v-if="mapPoints.length"` — mini-map (dan chunk JS-nya)
  hanya render kalau ada minimal 1 proyek dengan koordinat

### `ProjectController::index()` — implementasi aktual

```php
public function index(Request $request): Response
{
    $projects = $request->user()
        ->projects()
        ->orderByDesc('survey_date')
        ->get([
            'id', 'name', 'location', 'survey_date',
            'benchmark_name', 'benchmark_elevation',
            'tolerance_class', 'adjustment_method',
            'closure_error', 'allowed_tolerance',
            'total_distance_km', 'status',
        ]);

    $mapPoints = $request->user()
        ->projects()
        ->whereHas('surveyPoints')
        ->with(['surveyPoints' => fn ($q) => $q->select('project_id', 'lat', 'lng')])
        ->get(['id', 'name', 'status'])
        ->map(fn ($p) => [
            'project_id'   => $p->id,
            'project_name' => $p->name,
            'status'       => $p->status,
            'center_lat'   => (float) $p->surveyPoints->avg('lat'),
            'center_lng'   => (float) $p->surveyPoints->avg('lng'),
            'point_count'  => $p->surveyPoints->count(),
        ])
        ->values();

    return Inertia::render('Projects/Index', compact('projects', 'mapPoints'));
}
```

Test PHPUnit: `tests/Feature/ProjectIndexMapPointsTest.php` (3 test).

---

## Komponen Vue: MapLegend.vue — SELESAI ✅ (BARU, sesi terakhir)

Komponen kecil mandiri, menampilkan legenda ikon di bawah `SurveyMap.vue`
pada tab Peta. Tidak lazy-load-dependent terhadap komponen peta lain, tapi
tetap di-lazy-load lewat `defineAsyncComponent` sesuai konvensi.

### Props

```ts
interface Props {
  projectStatus: 'draft' | 'calculated' | 'accepted' | 'rejected'
  showNetworkLegend: boolean   // true kalau networkLegs.length > 0
}
```

### Isi legenda
- 4 baris warna marker: BM (kuning), TP (biru), IS (hijau), CP (ungu)
- Garis merah putus-putus: "Jalur pengukuran" (selalu tampil)
- Garis solid warna sesuai status proyek: "Jalur jaring (draft/terhitung/
  diterima/ditolak)" — **hanya muncul kalau `showNetworkLegend` true**

Dipasang di `Show.vue`:
```html
<MapLegend
  :project-status="project.status"
  :show-network-legend="networkLegs.length > 0"
/>
```

---

## Tab "Peta" di Show.vue — implementasi aktual (updated)
Tab order aktual:
[Bacaan] [Elevasi] [Jaring] [Peta] [Grafik] [Aktivitas]
Konten tab Peta (implementasi aktual sekarang, sudah lengkap):
┌──────────────────────────────────────────────────────────┐
│ Peta Survei [📁 Import GPX] │
│ N dari M titik punya koordinat │
│ ──────────────────────────────────────────────────────── │
│ [SurveyMap.vue — toggle Peta/Satelit, marker, popup, │
│ polyline merah, network overlay warna status] │
│ ──────────────────────────────────────────────────────── │
│ [MapLegend.vue — legenda warna marker + jenis garis] │
│ ──────────────────────────────────────────────────────── │
│ Titik dengan koordinat │ Titik tanpa koordinat │
│ BM-01 (TP) -x.xx, y.yy [Edit][Hapus] │ BM-02 [+ Tambah Koordinat] │
│ TP-1 (TP) -x.xx, y.yy [Edit][Hapus] │ TP-6 [+ Tambah Koordinat] │
└──────────────────────────────────────────────────────────┘
### State & fungsi di `Show.vue` (untuk debugging, updated)

```js
const showLocationPicker = ref(false)
const editingPoint = ref(null)   // { id: number|null, point_name, lat, lng, point_type }
const showGpxImport = ref(false)

const pointsWithCoords = computed(() => props.surveyPoints)
const pointsWithoutCoords = computed(() => {
  const covered = new Set(props.surveyPoints.map(p => p.point_name))
  return uniquePointNames.value.filter(n => !covered.has(n))
})

function openAddPoint(name) { editingPoint.value = { id: null, point_name: name, lat: null, lng: null, point_type: 'TP' }; showLocationPicker.value = true }
function openEditPoint(point) { editingPoint.value = { id: point.id, point_name: point.point_name, lat: Number(point.lat), lng: Number(point.lng), point_type: point.point_type }; showLocationPicker.value = true }
function savePoint(payload) { /* router.post survey-points.store atau router.put survey-points.update tergantung editingPoint.id */ }
function deletePoint(point) { /* confirm() lalu router.delete survey-points.destroy */ }
```

`openEditPoint` sekarang dipanggil dari **dua tempat**: tombol "Edit" di
daftar titik (biasa), **dan** event `@edit-point-requested` dari
`SurveyMap.vue` (dari tombol "Edit Koordinat" di popup marker). Karena
`SurveyMap.vue` meng-emit objek `SurveyPoint` penuh (bukan cuma `point_name`),
signature `openEditPoint(point)` tetap kompatibel tanpa perlu diubah.

`<LocationPicker>` sekarang menerima prop tambahan:
```html
<LocationPicker
  v-if="showLocationPicker"
  :point-name="editingPoint.point_name"
  :initial-lat="editingPoint.lat"
  :initial-lng="editingPoint.lng"
  :point-type="editingPoint.point_type"
  :reference-points="surveyPoints"
  @save="savePoint"
  @cancel="closeLocationPicker"
/>
```

`<SurveyMap>` sekarang menerima handler tambahan:
```html
<SurveyMap
  :points="surveyPoints"
  :elevations="elevations"
  :network-legs="networkLegs"
  :project-status="project.status"
  :can-edit="true"
  @edit-point-requested="openEditPoint"
/>
<MapLegend
  :project-status="project.status"
  :show-network-legend="networkLegs.length > 0"
/>
```

---

## Precision Policy untuk Koordinat (tidak berubah)

| Data              | PostgreSQL Type  | Presisi       | Setara di Bumi     |
| ----------------- | ---------------- | ------------- | ------------------ |
| Latitude          | NUMERIC(12,8)    | 0.00000001°   | ≈ 1.1 mm           |
| Longitude         | NUMERIC(12,8)    | 0.00000001°   | ≈ 0.7 mm (ekuator) |
| Elevasi GPS ref   | NUMERIC(12,4)    | 0.0001 m      | 0.1 mm             |
| Akurasi GPS       | NUMERIC(6,3)     | 0.001 m       | 1 mm               |

**Koordinat bukan `FLOAT`** — sesuai presisi policy keseluruhan GeoLevel.
Gunakan `NUMERIC` di PostgreSQL dan cast `decimal:8` di Eloquent.

---

## Batasan dan Catatan Penting

### CARTO Basemaps Usage Policy
- Wajib tampilkan attribution `© OpenStreetMap contributors © CARTO` (sudah
  otomatis muncul dari kontrol attribution bawaan MapLibre)
- Gratis selamanya untuk pemakaian standar, tanpa API key

### Esri World Imagery Usage Policy
- Wajib tampilkan attribution Esri (sudah otomatis muncul)
- Gratis untuk pemakaian standar tanpa API key/signup
- Untuk skala sangat besar/komersial berat, Esri secara teknis
  merekomendasikan developer account — tidak relevan untuk skala aplikasi ini
- Resolusi citra tidak seseragam Google Earth — bagus di kota besar, bisa
  lebih buram di area pedesaan/terpencil

### Nominatim Usage Policy
- Maksimum **1 request per detik**
- Wajib cantumkan `User-Agent` atau `Referer` header (sudah dipenuhi lewat
  header `Accept-Language` + browser default `Referer`)
- Jangan gunakan untuk geocoding massal

### Browser Geolocation API
- Hanya berfungsi di HTTPS (terpenuhi di `geolevel.local`)
- Akurasi bergantung perangkat user

### Koordinat Independen dari Pipeline Kalkulasi
Koordinat GPS **tidak mempengaruhi** kalkulasi elevasi. Pipeline sipat datar
tetap berjalan dari `readings` saja. `survey_points` murni untuk visualisasi.

### OPcache / stale code saat development lokal — pelajaran penting

Setup lokal proyek ini pakai Nginx + PHP-FPM (bukan `php artisan serve`), jadi
perubahan file `.php` **tidak selalu langsung terlihat** tanpa restart PHP-FPM,
tergantung konfigurasi OPcache.

**Prosedur wajib setelah setiap patch backend yang tidak langsung terlihat efeknya:**
1. `grep` isi file di disk untuk pastikan patch benar-benar masuk — **jangan
   asumsikan command yang "sepertinya jalan" benar-benar jalan**.
2. Kalau file di disk sudah benar tapi HTTP response masih lama:
   `sudo systemctl restart php8.5-fpm`
3. Kalau masih belum berubah: `php artisan optimize:clear`
4. Verifikasi paling akurat: `curl`/`view-source:` HTML mentah, bukan cuma
   tampilan visual browser.

### PostgreSQL bisa mati setelah restart WSL/Windows

Dicek di awal sesi terakhir — semua e2e Playwright gagal serentak karena
`SQLSTATE[08006] Connection refused`. Ini murni operasional (bukan bug
kode), tapi polanya khas dan penting dikenali: kalau *semua* test gagal
sekaligus (bukan cuma satu-dua terkait perubahan terakhir), curigai
infrastruktur dulu:
```bash
sudo service postgresql status
sudo service postgresql start   # kalau mati
```
Detail lengkap: lihat `docs/claude.md` → "Known Fixes Applied".

### e2e test jalan di atas database dev, bukan database test terisolasi

`playwright.config.ts` → `baseURL: 'https://geolevel.local'` menunjuk ke
environment dev (`.env` → `DB_DATABASE=geolevel`), **bukan** `geolevel_test`
yang dipakai PHPUnit. Konsekuensi: state database berubah permanen setiap
kali developer testing manual lewat browser, dan bisa membuat e2e test
berikutnya gagal karena asumsi state tidak lagi terpenuhi (bukan karena bug).

**Wajib re-seed sebelum `npm run test:e2e`** kalau sempat ada testing manual
sebelumnya:
```bash
php artisan db:seed --class=CanonicalSurveySeeder
php artisan db:seed --class=MapDemoSeeder
```
Kedua seeder idempotent, aman dijalankan ulang kapan saja.

---

## Utang Teknis (Technical Debt)

**Status: semua 6 item utang teknis sebelumnya TUNTAS** (terakhir diperbarui
2026-11-06). Tidak ada utang teknis Map Feature yang tersisa.

| # | Item | Hasil |
| - | ---- | ----- |
| 1 | Bentuk ikon custom per tipe titik | **Dibatalkan, bukan dikerjakan.** Sempat diimplementasikan (bintang/segitiga/berlian via `clip-path` di peta dan polygon GD di PDF), lalu dikembalikan ke lingkaran atas keputusan desain: semua marker berbentuk lingkaran, tipe dibedakan hanya lewat warna (BM kuning, TP biru, IS hijau, CP ungu) supaya tampilan terkesan profesional. Ukuran BM tetap paling besar. |
| 2 | Handler `request-add-point` | **Selesai.** Klik peta kosong membuka `LocationPicker` untuk titik pertama di daftar "tanpa koordinat" dengan lat/lng terisi dari posisi klik. Kalau semua titik sudah punya koordinat, tampil `alert`. Klik pada marker/popup diabaikan (filter `closest('.maplibregl-marker, .maplibregl-popup')`). `LocationPicker` punya prop baru `isNew` untuk judul "Tambah"/"Edit". Terbukti lewat e2e. |
| 3 | Test otomatis `mapPoints` | **Selesai.** `tests/Feature/ProjectIndexMapPointsTest.php` (3 test): proyek tanpa titik dikecualikan, bentuk data + rata-rata lat/lng benar, hanya proyek milik user sendiri. |
| 4 | `survey.spec.ts:162` gagal | **Selesai, akar masalah ditemukan.** Bukan bug UI: `CanonicalSurveySeeder` tidak pernah menjalankan `LeastSquaresAdjustmentService`, sehingga `network_std_deviation` tetap NULL dan tombol "PDF Jaring"/"Excel Jaring" (syarat `status=accepted` DAN `network_std_deviation != null`) memang tersembunyi. Seeder kini memanggil `adjustProject()` dan juga **idempoten** (menghapus proyek demo lama sebelum membuat ulang), karena duplikat dengan `survey_date` sama membuat urutan `orderByDesc` tidak stabil. |
| 5 | Dead code network export | **Ternyata sudah bersih sejak sesi lama.** `NetworkExportService`, `GenerateNetworkPdfExportJob`, `GenerateNetworkExcelExportJob` tidak ada lagi dan tidak direferensikan di mana pun. Yang tersisa dan AKTIF dipakai: trait `app/Jobs/Concerns/BuildsNetworkExportData.php` (dipakai `NetworkLegController`). Catatan lama yang menyebut ketiganya masih ada adalah dokumentasi yang kedaluwarsa. |
| 6 | Deteksi tile "valid tapi kosong" | **Selesai dengan pendekatan konservatif.** Tile seragam tidak bisa dibedakan dengan pasti dari laut/gurun yang sah, jadi tile TIDAK diganti. `StaticMapRenderService` menghitung rasio tile seragam (sampling grid 8x8, toleransi warna 6 per kanal, ambang 95% sampel mirip) dan `hasLikelySparseCoverage()` bernilai true bila >= 70% tile yang berhasil di-fetch seragam. `ExportController` meneruskannya ke Blade (`mapSparseCoverage`), yang menampilkan catatan advisory "cakupan basemap kemungkinan terbatas, coba mode Satelit". Terverifikasi: muncul untuk Kalimantan (mode Peta), tidak muncul untuk Jakarta. |

### Hasil uji terakhir

- PHPUnit: **216 test, 572 assertion**, hijau.
- Playwright e2e: **27 test** (22 `survey.spec.ts` + 5 `map.spec.ts`), hijau.
- `map.spec.ts` kini 5 test: polyline, toggle Peta/Satelit, titik tanpa koordinat,
  klik peta kosong (tambah, simpan, alert), klik marker (popup, tanpa modal tambah).

### Yang BELUM terverifikasi

- Tampilan visual peta interaktif setelah keputusan "semua lingkaran" belum
  dilihat langsung di browser (e2e tidak menilai bentuk/warna).
- Test PHPUnit khusus untuk `StaticMapRenderService` (termasuk
  `hasLikelySparseCoverage()`) belum ada; hanya diverifikasi manual lewat
  PDF sungguhan.

---

## Export PDF Peta -- StaticMapRenderService (SELESAI, sesi terakhir)

### Pendekatan yang dipilih: Opsi B (redraw backend, bukan screenshot canvas)

MapLibre GL merender ke WebGL canvas, yang tidak bisa dirender langsung
oleh DomPDF. Dua opsi dipertimbangkan:
- Opsi A -- screenshot canvas browser (map.getCanvas().toDataURL())
  dikirim sebagai base64 ke backend sebelum request PDF.
- Opsi B (dipilih) -- backend redraw peta dari nol (basemap tile + marker
  + polyline + network legs) murni pakai PHP GD, tanpa menyentuh WebGL sama
  sekali.

Opsi B dipilih karena menghasilkan dokumen yang deterministik dari data
(sama input = sama output, tidak bergantung state browser/viewport user saat
export), lebih sesuai untuk dokumen teknis field book yang mungkin diarsipkan
atau dijadikan bukti pengukuran resmi -- trade-off-nya adalah kompleksitas
implementasi lebih besar (proyeksi manual, fetch tile terpisah dari frontend).

### app/Services/StaticMapRenderService.php

Kelas murni (bukan dipanggil via facade), diinjeksi via constructor injection
Laravel ke ExportController::pdf(). Precision exception yang sama seperti
LeastSquaresAdjustmentService -- operasi geometri pakai PHP float,
input/output lat/lng tetap presisi tinggi dari database.

Method publik utama:

- computeLayout(array $points): array -- hitung bounding box, zoom level
  otomatis (max zoom yang muat di kanvas 900x600 dikurangi padding), dan
  origin pixel dunia untuk kanvas.
- renderBaseMap(array $layout, string $mode): GdImage -- mode 'street'
  (CARTO Voyager) atau 'satellite' (Esri World Imagery). Fetch tile raster
  via Http facade, composite ke satu canvas GD. Fallback checkerboard
  abu-abu kontras untuk tile yang gagal fetch.
- drawMarker(...) -- lingkaran solid + outline putih 2px, warna sama
  seperti SurveyMap.vue (BM kuning, TP biru, IS hijau, CP ungu).
- drawRoute(...) -- polyline merah putus-putus urutan sequence_no, sama
  logic seperti buildOrderedCoords() di SurveyMap.vue.
- drawNetworkLegs(...) -- garis putus-putus warna sesuai status proyek,
  sama seperti NETWORK_LEG_COLOR di SurveyMap.vue.

Proyeksi Web Mercator (lonToWorldPixelX, latToWorldPixelY) memakai rumus
standar yang identik dengan yang dipakai semua slippy map tile provider
(termasuk CARTO dan Esri) -- terverifikasi presisi lewat test tinker manual.

### Wiring ke ExportController::pdf()

Method pdf() menerima StaticMapRenderService lewat constructor injection,
membaca query param map_mode (default 'street', 'satellite' kalau diminta),
memanggil private method renderMapImage() yang mengambil survey_points,
computed_elevations (untuk urutan polyline), dan network_legs, lalu
memanggil keempat method service di atas berurutan, dan terakhir
imagepng() + base64_encode() untuk disisipkan sebagai
data:image/png;base64,... ke Blade. Hasilnya null kalau proyek tidak
punya survey_points sama sekali.

### field_book.blade.php -- section "Peta Survei"

Disisipkan antara "Ringkasan Penutup" dan "Tabel Data Sipat Datar". Fallback
kotak putus-putus "Belum ada titik koordinat..." kalau mapImageBase64 null
(proyek tanpa survey_points) -- tidak pernah error, section tetap tampil rapi.

### Show.vue -- pilihan mode Peta/Satelit saat export

Dropdown pdfMapMode (ref('street')) muncul di sebelah tombol PDF, hanya
untuk status accepted. exportProject('pdf') menambahkan
?map_mode=<mode> ke URL -- format excel/csv tidak terpengaruh.

### Temuan penting: tile HTTP 200 tapi konten kosong (bukan bug)

Selama debugging, ditemukan bahwa CARTO/Esri bisa mengembalikan tile dengan
HTTP 200 dan PNG valid, tapi isinya polos/kosong untuk lokasi yang tidak
tercakup data OpenStreetMap -- ini perilaku normal tile provider (bukan
kegagalan fetch), sehingga tidak terdeteksi oleh fallback checkerboard
(yang hanya aktif untuk fetch yang benar-benar gagal: HTTP error,
exception, gagal decode).

Ini penting untuk konteks survei geodetik: banyak proyek nyata berlokasi
di area terpencil (perkebunan, tambang, irigasi pedesaan) yang minim
ter-mapping OSM. Diverifikasi dengan koordinat proyek demo asli
(lat -3.75, lng 116.16, Kalimantan Timur) -- dikonfirmasi juga kosong di
openstreetmap.org langsung, bukan bug kita. Mode Satelit (Esri World
Imagery) jauh lebih andal untuk kasus ini karena citra satelit mencakup
seluruh permukaan bumi, tidak bergantung kontribusi komunitas seperti
OSM/CARTO -- direkomendasikan sebagai default pilihan untuk lokasi survei
yang diperkirakan terpencil.

### Fallback checkerboard (fillFallbackPattern)

Warna awal (RGB 230,230,230 flat) diganti pola checkerboard dua warna
abu-abu (235,235,235 / 205,205,205, kotak 20px) supaya kegagalan fetch
tile terlihat jelas sebagai kondisi "tidak ada data", bukan disalahartikan
sebagai halaman kosong/bug rendering.

### Verifikasi

- Test manual end-to-end: proyek "Survey Demo Diterima" dengan koordinat
  asli (Kalimantan, minim data OSM) DAN koordinat sementara Jakarta (banyak
  data OSM) -- basemap, marker, polyline, network legs overlay semua
  presisi di kedua mode (Peta/Satelit) untuk kedua lokasi.
- 213 test PHPUnit (kini 216 setelah test mapPoints) tetap hijau tanpa perubahan -- ExportControllerTest dan
  ProjectWorkflowTest yang menyentuh jalur pdf() tidak perlu modifikasi
  sama sekali berkat constructor injection dan fallback null-safe.
- Belum ada test PHPUnit khusus untuk StaticMapRenderService -- hanya
  diverifikasi manual via tinker dan visual PDF. Kandidat penambahan test
  di masa depan (lihat tabel Utang Teknis).

## Referensi

- [MapLibre GL JS Docs](https://maplibre.org/maplibre-gl-js/docs/)
- [MapLibre GL JS — Map#isStyleLoaded()](https://maplibre.org/maplibre-gl-js/docs/API/classes/Map/#isstyleloaded)
- [CARTO Basemaps](https://github.com/CartoDB/basemap-styles)
- [Esri World Imagery Service](https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer)
- [Nominatim API](https://nominatim.org/release-docs/latest/api/Search/)
- [GPX Format Specification](https://www.topografix.com/gpx.asp)
- [gpxparser npm](https://www.npmjs.com/package/gpxparser)
- [Browser Geolocation API — MDN](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation_API)