# GeoLevel — Outdoor Navigation Feature (ala AlpineQuest): Panduan & Parameter Pasti

> Baca dokumen ini sebelum mengerjakan apa pun yang terkait **track GPS, waypoint, rute,
> konversi koordinat, pengukuran di peta, mode offline, atau integrasi AI**.
> Dokumen ini adalah satu-satunya sumber kebenaran untuk fitur Outdoor.
> Letakkan di `docs/outdoor.md` dan daftarkan di tabel "Documentation" pada `docs/claude.md`.

Status dokumen: **DRAFT v1 — menunggu konfirmasi 6 keputusan terbuka (lihat bagian 14).**
Semua angka di bawah menjadi **TETAP** setelah keputusan terbuka dikonfirmasi.
Setelah TETAP, perubahan angka hanya lewat config, bukan hardcode.

---

## 1. Apa yang dimaksud "mirip AlpineQuest"

AlpineQuest adalah aplikasi navigasi outdoor berbasis GPS (hiking, berburu, geocaching, off-road)
yang menekankan: peta topografi yang tetap bisa dipakai tanpa sinyal, perekaman track,
waypoint/rute, navigasi aktif (jarak, bearing, ETA), dan koordinat multi-format.

GeoLevel **tidak** menjadi pengganti AlpineQuest. Yang diambil hanya kemampuan yang
bernilai untuk pekerjaan survei lapangan:

| Kemampuan AlpineQuest        | Padanan di GeoLevel                                   | Fase |
| ---------------------------- | ------------------------------------------------------ | ---- |
| Waypoint                     | `waypoints` (kategori survei + umum)                   | 1    |
| Track (rekam & impor GPX)    | `tracks` + `track_points`                              | 1–2  |
| Rute terencana               | `routes`                                               | 2    |
| Koordinat multi-format       | Konverter WGS84 ↔ UTM ↔ MGRS                           | 3    |
| Ukur jarak/luas di peta      | `MeasureTool.vue`                                      | 3    |
| Navigasi aktif (bearing/ETA) | `NavigationHud.vue`                                    | 3    |
| Profil elevasi track         | `ElevationProfileChart.vue`                            | 3    |
| Peta offline                 | PWA + cache tile (terbatas, lihat bagian 9)            | 4    |
| —                            | Asisten AI (ringkas track, tanya-jawab data proyek)    | 5    |

**Di luar cakupan (tidak dikerjakan):** aplikasi native Android/iOS, berbagi lokasi
real-time antar-pengguna, navigasi suara, unduh peta massal.

---

## 2. Aturan Emas (tidak boleh dilanggar)

1. **Fitur Outdoor tidak boleh mempengaruhi pipeline sipat datar.** Elevasi GPS bukan
   hasil leveling. Data Outdoor tidak pernah masuk `LevelingCalculationService`,
   `ClosureCheckerService`, atau `AdjustmentService`.
2. **Raw track point immutable.** `track_points` hanya di-insert, tidak di-update.
   Penyederhanaan (simplify) dihitung terpisah, tidak menimpa data mentah.
3. **Koordinat opsional.** Proyek tetap berfungsi penuh tanpa fitur Outdoor.
4. **Tidak ada `FLOAT`/`DOUBLE PRECISION` di PostgreSQL.** Kolom desimal wajib `NUMERIC`
   (lihat bagian 4).
5. **Semua konstanta ada di `config/geolevel.php`** (key `outdoor`, bagian 3). Jangan hardcode.
6. **Komponen peta/WebGL wajib lazy-load** via `defineAsyncComponent`.
7. **Layer GeoJSON wajib memakai `waitForStyleReady()`**, bukan `once('style.load')`
   (lihat `docs/claude.md` → Known Fixes).
8. **Tile hanya dari provider gratis** (CARTO Voyager dengan key gratis `VITE_CARTO_KEY`, Esri World Imagery tanpa key).
   Jangan kembali ke `tile.openstreetmap.org` untuk MapLibre GL.
9. **Controller tipis, logika di Service, validasi di Form Request, event-driven**
   — sama seperti arsitektur yang ada.
10. **Kode dalam bahasa Inggris, label UI dalam bahasa Indonesia.**
11. **AI tidak pernah menghitung, mengubah, atau menimpa angka teknis** (bagian 11).

---

## 3. Konfigurasi — `config/geolevel.php` → key `outdoor`

Tambahkan blok ini. Nilai di bawah adalah **parameter pasti** fitur.

```php
'outdoor' => [

    // ── Datum & koordinat ─────────────────────────────────────────────
    'datum'              => 'WGS84',        // EPSG:4326; SRGI 2013 setara WGS84 pada level meter
    'utm_zone_min'       => 46,             // wilayah Indonesia: zona 46–54
    'utm_zone_max'       => 54,
    'utm_central_meridian_easting' => 500000,    // meter, per zona
    'utm_false_northing_south'     => 10000000,  // meter, belahan selatan
    'coordinate_formats' => ['dd', 'dms', 'utm', 'mgrs'],
    'default_coordinate_format' => 'dd',

    // ── Perekaman track (client) ──────────────────────────────────────
    'record_min_distance_m'   => 5,      // abaikan titik < 5 m dari titik sebelumnya
    'record_min_interval_s'   => 2,      // minimal 2 detik antar titik
    'record_max_accuracy_m'   => 30,     // tolak titik dengan akurasi horizontal > 30 m
    'record_auto_pause_speed_mps' => 0.3,
    'record_auto_pause_after_s'   => 30,
    'record_batch_size'       => 30,     // kirim ke server tiap 30 titik...
    'record_batch_interval_s' => 15,     // ...atau tiap 15 detik, mana lebih dulu
    'record_use_wake_lock'    => true,

    // ── Batas data ────────────────────────────────────────────────────
    'track_max_points'        => 100000,
    'gpx_max_size_mb'         => 10,
    'gpx_max_waypoints'       => 500,    // sama dengan MAX_WAYPOINTS GpxImportModal
    'route_max_vertices'      => 500,
    'waypoint_max_per_user'   => 5000,

    // ── Statistik & penyederhanaan ────────────────────────────────────
    'stats_elevation_noise_threshold_m' => 3.0,  // naik/turun < 3 m tidak dihitung gain
    'stats_moving_speed_min_mps'        => 0.3,
    'simplify_tolerance_display_m'      => 2,    // Ramer–Douglas–Peucker untuk tampilan peta
    'simplify_tolerance_ai_m'           => 10,   // untuk input AI (bagian 11)

    // ── Peta & offline ────────────────────────────────────────────────
    'tile_cache_max_mb'       => 200,
    'tile_cache_ttl_days'     => 30,
    'offline_queue_max_points'=> 20000,

    // ── Ekspor ────────────────────────────────────────────────────────
    'export_disk' => 'local',
    'export_path' => 'exports/outdoor',     // storage/app/exports/outdoor/{user_id}/
],

'ai' => [   // lihat bagian 11
    'enabled'              => env('AI_ENABLED', false),
    'provider'             => 'anthropic',
    'model'                => env('AI_MODEL'),          // jangan hardcode nama model
    'api_key'              => env('ANTHROPIC_API_KEY'), // hanya backend
    'timeout_s'            => 30,
    'max_output_tokens'    => 1024,
    'max_tries'            => 2,
    'rate_limit_per_user_per_hour' => 20,
    'max_geometry_points'  => 200,
    'prompt_version'       => 'v1',
],
```

---

## 4. Precision Policy (perluasan dari `engineering-rules.md`)

| Data                          | PostgreSQL              | PHP                       | Catatan                    |
| ----------------------------- | ----------------------- | ------------------------- | -------------------------- |
| Latitude / Longitude          | `NUMERIC(12,8)`         | string, cast `decimal:8`  | ≈ 1 mm, sama dengan `survey_points` |
| Elevasi GPS                   | `NUMERIC(12,4)`         | string, `decimal:4`       | bukan elevasi leveling     |
| Akurasi horizontal            | `NUMERIC(6,3)`          | `decimal:3`               | meter                      |
| Kecepatan                     | `NUMERIC(8,3)`          | `decimal:3`               | m/s                        |
| Heading                       | `NUMERIC(6,3)`          | `decimal:3`               | derajat 0–360              |
| Jarak track / rute            | `NUMERIC(12,3)`         | `decimal:3`               | meter                      |
| Naik/turun kumulatif          | `NUMERIC(10,3)`         | `decimal:3`               | meter                      |
| Waktu rekam titik             | `TIMESTAMPTZ(3)`        | Carbon                    | milidetik, simpan UTC      |
| Geometri rute terencana       | `JSONB`                 | array                     | `[[lat,lng],…]`            |

**Pengecualian presisi (didokumentasikan, sama preseden `LeastSquaresAdjustmentService`
dan `StaticMapRenderService`):** perhitungan geometri berulang (haversine, bearing, proyeksi
UTM, RDP) boleh memakai `float` PHP/JS di dalam fungsi. Input dan output yang disimpan
tetap `NUMERIC`. Pengecualian ini **hanya** untuk `GeoMathService` dan sejenisnya,
**tidak pernah** untuk elevasi leveling.

---

## 5. Skema Database

Semua migration additive. Tidak ada perubahan pada tabel yang sudah ada.
Setiap kolom baru wajib masuk `$fillable` **dan** `$casts` (pelajaran silent mass-assignment).

### `waypoints`
```sql
id               BIGSERIAL PRIMARY KEY
user_id          BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE
project_id       BIGINT NULL REFERENCES projects(id) ON DELETE SET NULL
survey_point_id  BIGINT NULL REFERENCES survey_points(id) ON DELETE SET NULL
name             VARCHAR(100) NOT NULL
lat              NUMERIC(12,8) NOT NULL
lng              NUMERIC(12,8) NOT NULL
elevation_ref    NUMERIC(12,4) NULL
gps_accuracy_m   NUMERIC(6,3) NULL
category         VARCHAR(20) NOT NULL DEFAULT 'generic'
                 -- generic | survey_marker | camp | water | danger | summit | parking
source           VARCHAR(20) NOT NULL DEFAULT 'manual'   -- manual | gpx | recorded | picker
notes            TEXT NULL
created_at, updated_at
INDEX (user_id), INDEX (project_id)
```
> `waypoints` sengaja **terpisah** dari `survey_points`: `survey_points` tetap khusus titik
> ukur sipat datar (BM/TP/IS/CP) dan unik per `(project_id, point_name)`.
> `survey_point_id` hanya tautan opsional.

### `tracks`
```sql
id                BIGSERIAL PRIMARY KEY
user_id           BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE
project_id        BIGINT NULL REFERENCES projects(id) ON DELETE SET NULL
name              VARCHAR(150) NOT NULL
source            VARCHAR(20) NOT NULL        -- recorded | gpx
status            VARCHAR(20) NOT NULL        -- recording | paused | finished
started_at        TIMESTAMPTZ(3) NULL
ended_at          TIMESTAMPTZ(3) NULL
point_count       INT NOT NULL DEFAULT 0
distance_m        NUMERIC(12,3) NULL
duration_s        INT NULL
moving_time_s     INT NULL
elevation_gain_m  NUMERIC(10,3) NULL
elevation_loss_m  NUMERIC(10,3) NULL
min_elevation     NUMERIC(12,4) NULL
max_elevation     NUMERIC(12,4) NULL
metadata          JSONB NOT NULL DEFAULT '{}'
created_at, updated_at
```

### `track_points` (volume tinggi, immutable)
```sql
id           BIGSERIAL PRIMARY KEY
track_id     BIGINT NOT NULL REFERENCES tracks(id) ON DELETE CASCADE
seq          INT NOT NULL
lat          NUMERIC(12,8) NOT NULL
lng          NUMERIC(12,8) NOT NULL
ele          NUMERIC(12,4) NULL
accuracy_m   NUMERIC(6,3) NULL
speed_mps    NUMERIC(8,3) NULL
heading_deg  NUMERIC(6,3) NULL
recorded_at  TIMESTAMPTZ(3) NOT NULL
UNIQUE (track_id, seq)
```
Wajib: `INDEX (track_id, seq)`. Agregasi (jarak kumulatif, gain) memakai window
function `LAG() OVER` / `SUM() OVER` di DB, bukan loop PHP.
`seq` dibuat oleh server saat batch diterima (idempoten: batch dengan `client_batch_id`
yang sama diabaikan).

### `routes`
```sql
id          BIGSERIAL PRIMARY KEY
user_id     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE
project_id  BIGINT NULL REFERENCES projects(id) ON DELETE SET NULL
name        VARCHAR(150) NOT NULL
geometry    JSONB NOT NULL            -- [[lat,lng], …] maks. route_max_vertices
distance_m  NUMERIC(12,3) NULL
notes       TEXT NULL
created_at, updated_at
```

### `ai_insights` (fase 5)
```sql
id             BIGSERIAL PRIMARY KEY
user_id        BIGINT NOT NULL REFERENCES users(id)
project_id     BIGINT NULL REFERENCES projects(id) ON DELETE SET NULL
subject_type   VARCHAR(30) NOT NULL    -- track | project | route
subject_id     BIGINT NOT NULL
kind           VARCHAR(40) NOT NULL    -- track_summary | project_qa | anomaly_hint
prompt_version VARCHAR(20) NOT NULL
model          VARCHAR(100) NOT NULL
input_hash     CHAR(64) NOT NULL       -- sha256 dari input yang dikirim
output         JSONB NOT NULL
tokens_in      INT NULL
tokens_out     INT NULL
created_at
```

**Kepemilikan & otorisasi:** item Outdoor milik `user_id`. Jika `project_id` terisi,
akses mengikuti `ProjectPolicy`. Tambah `OutdoorItemPolicy` (cek `user_id`).

---

## 6. Arsitektur Backend

```
Request → Controller → Service → Model / DB
               ↓
          Form Request
               ↓
          Event → Listener → Job (queued)
```

| Komponen                  | Tanggung jawab                                                               |
| ------------------------- | ---------------------------------------------------------------------------- |
| `GeoMathService`          | haversine, bearing, titik tujuan, RDP, konversi DD/DMS/UTM/MGRS              |
| `TrackRecordingService`   | buat track, terima batch titik (idempoten), pause/resume/finish              |
| `TrackStatsService`       | jarak, durasi, moving time, gain/loss (dengan noise threshold)               |
| `GpxService`              | ekspor GPX (server, `XMLWriter`); validasi batch impor                        |
| `WaypointService`         | CRUD + tautan opsional ke `survey_points`                                     |
| `OutdoorExportService`    | ekspor GPX/CSV; ikut aturan job queue                                         |
| `AiInsightService`        | satu-satunya pintu ke AI (bagian 11)                                          |

**Job:** `FinalizeTrackJob` (hitung statistik setelah finish; tries 3, timeout 60 s;
`failed()` → status track tetap `finished`, stat NULL, tulis `activity_logs`).
**Event:** `TrackFinished`, `WaypointSaved`, `GpxImported`, `AiInsightGenerated`
— semua membawa `user_id` (dan `project_id` bila ada).
**Activity log (`activity_logs.activity_type`):** `track_finished`, `waypoint_saved`,
`gpx_imported`, `ai_insight_generated`, `job_failed`.
Catatan: `activity_logs.project_id` saat ini NOT NULL → untuk item tanpa proyek,
log **tidak ditulis** ke `activity_logs` (keputusan terbuka #2).

### Route API
```text
GET    /outdoor/waypoints            POST   /outdoor/waypoints
PUT    /outdoor/waypoints/{id}       DELETE /outdoor/waypoints/{id}
POST   /outdoor/waypoints/import

GET    /outdoor/tracks               POST   /outdoor/tracks              (mulai rekam)
GET    /outdoor/tracks/{id}          DELETE /outdoor/tracks/{id}
POST   /outdoor/tracks/{id}/points   (batch titik)
POST   /outdoor/tracks/{id}/pause    POST   /outdoor/tracks/{id}/resume
POST   /outdoor/tracks/{id}/finish
POST   /outdoor/tracks/import        (GPX → track)
GET    /outdoor/tracks/{id}/profile  (data profil elevasi, ter-agregasi di DB)
GET    /outdoor/tracks/{id}/export/gpx
GET    /outdoor/tracks/{id}/export/csv

GET|POST|PUT|DELETE /outdoor/routes
POST   /outdoor/ai/track-summary     POST /outdoor/ai/ask      (fase 5)
```
Pemanggilan dari Vue **selalu** lewat Ziggy `route()`. Unduhan file lewat
`window.location.href`, bukan axios.

---

## 7. Arsitektur Frontend

```
resources/js/Components/Outdoor/
├── OutdoorMap.vue            ← peta utama (memakai mapStyle.js bersama)
├── TrackRecorder.vue         ← tombol rekam/pause/stop + statistik langsung
├── WaypointForm.vue
├── CoordinateConverter.vue
├── MeasureTool.vue
├── NavigationHud.vue         ← jarak, bearing, ETA ke waypoint tujuan
└── ElevationProfileChart.vue ← Chart.js (vue-chartjs, sudah terpasang)
resources/js/Pages/Outdoor/
├── Index.vue                 ← daftar track, waypoint, rute
└── Show.vue                  ← detail track + peta + profil
```

Aturan komponen:
- Impor **hanya** via `defineAsyncComponent`.
- Style peta dari `Components/Map/mapStyle.js` (`MAP_STYLES`, `INDONESIA_CENTER`); jangan duplikasi.
- Toggle Peta/Satelit memakai pola `setMapStyle()` + `waitForStyleReady()`.
- Warna marker waypoint: pakai skema yang sudah ada (BM kuning `#F59E0B`, TP biru `#3B82F6`,
  IS hijau `#10B981`, CP ungu `#8B5CF6`); kategori baru memakai warna berbeda dan masuk
  ke `MapLegend.vue`. Garis track: warna **berbeda** dari polyline survei merah `#DC2626`
  → gunakan `#0EA5E9` (biru langit), width 3, solid.
- CSS MapLibre tetap dari CDN di `app.blade.php`.
- Setiap kontrak props/emits baru ditulis di dokumen ini sebelum implementasi
  (props tidak boleh diubah diam-diam). Kontrak `TrackRecorder.vue` dan perekaman live: bagian 17.

---

## 8. Perekaman Track — Perilaku Pasti

1. Sumber posisi: `navigator.geolocation.watchPosition` dengan `enableHighAccuracy: true`.
2. Titik diterima hanya jika: akurasi ≤ `record_max_accuracy_m`, jarak ≥ `record_min_distance_m`
   **dan** selang ≥ `record_min_interval_s`.
3. Titik disimpan lokal dulu (IndexedDB), lalu dikirim per `record_batch_size` / `record_batch_interval_s`.
   Tiap batch membawa `client_batch_id` (UUID) agar retry tidak menggandakan data.
4. Auto-pause saat kecepatan < `record_auto_pause_speed_mps` selama `record_auto_pause_after_s`.
5. Memakai Screen Wake Lock API selama merekam bila didukung.
6. Statistik akhir dihitung server (`FinalizeTrackJob`), bukan klien. Angka di klien hanya perkiraan sementara.
7. Batas keras: `track_max_points`; setelah tercapai, perekaman dihentikan dengan pesan jelas.

**Batasan jujur (diterima, bukan bug):** aplikasi web **tidak bisa merekam andal saat layar
terkunci/tab di latar belakang** — berbeda dengan AlpineQuest native. Kebijakan:
rekam hanya saat tab aktif di depan + Wake Lock; tampilkan peringatan saat tab disembunyikan;
data yang sudah masuk aman di IndexedDB. Jika kebutuhan latar belakang muncul, opsi
ke depan adalah membungkus PWA dengan Capacitor (di luar cakupan dokumen ini).
Geolocation hanya jalan di HTTPS (sudah terpenuhi di `geolevel.local`).

---

## 9. Peta Offline — Batasan Pasti

- Tahap awal = **PWA + Service Worker**: cache app shell dan tile yang **pernah dilihat**
  (`tile_cache_max_mb`, `tile_cache_ttl_days`), plus antrean titik offline di IndexedDB
  (`offline_queue_max_points`) yang disinkronkan saat online.
- **Dilarang** mengunduh tile massal (prefetch area) dari CARTO/Esri: kebijakan penggunaan
  gratis mereka umumnya tidak mengizinkan bulk download. Verifikasi syarat tiap provider
  sebelum menyentuh fase 4.
- Peta offline sungguhan (area yang diunduh sebelumnya) hanya via **tile sendiri**
  (mis. file PMTiles dari data OSM, di-host sendiri) — keputusan terbuka #3.
- Mode offline tidak boleh mengubah perilaku kalkulasi leveling.

---

## 10. Koordinat, Pengukuran & Navigasi

- Datum tunggal: **WGS84**. UTM: zona **46–54**, meridian tengah tiap zona = 500.000 m easting,
  belahan selatan memakai false northing 10.000.000 m (sesuai buku *Survei Terestris Geospasial*,
  Bab VI). Wilayah Indonesia melintasi ekuator, jadi **belahan (N/S) wajib disimpan/ditampilkan**
  bersama zona.
- Format output: `dd` (default), `dms`, `utm`, `mgrs`. Satu fungsi konversi di `GeoMathService`;
  frontend memakai pustaka konversi yang sama hasilnya (diverifikasi test lintas-bahasa).
- Jarak: haversine pada bola (cukup untuk navigasi); **bukan** untuk keperluan kadaster/legal.
- Bearing: azimut 0–360° dari utara geografis (utara grid dan magnetis **tidak** dikoreksi pada v1).
- ETA = jarak sisa ÷ kecepatan bergerak rata-rata 60 detik terakhir; tampilkan “—” bila kecepatan < `stats_moving_speed_min_mps`.
- Profil elevasi: memakai `ele` GPS pada `track_points`; bila kosong, sumber DEM eksternal
  (keputusan terbuka #4). Elevasi GPS **ditandai jelas** sebagai “elevasi GPS, bukan hasil leveling”.
- Ukur luas: koordinat dikonversi ke UTM lalu rumus trapesium/shoelace (selaras `Bab 7/9` buku referensi).

---

## 11. Fase AI (setelah fase 1–4 selesai dan stabil)

### Prinsip
1. **AI hanya membaca, menjelaskan, dan menyarankan.** AI **tidak pernah**: menulis ke
   `readings`, `computed_elevations`, `survey_points`, `network_legs`; menghitung elevasi/koreksi;
   menentukan status `accepted/rejected`. Keputusan teknis tetap oleh validator deterministik.
2. **Satu pintu:** semua panggilan lewat `AiInsightService`. Kunci API hanya di `.env` backend,
   tidak pernah di frontend.
3. **Opt-in:** fitur mati secara default (`AI_ENABLED=false`). Ada saklar per proyek
   (`projects.metadata->ai_enabled`, default `false`) dan persetujuan eksplisit pengguna.
4. **Minimisasi data:** kirim statistik teragregasi + geometri yang disederhanakan
   (`simplify_tolerance_ai_m`, maks `max_geometry_points` titik). Jangan kirim catatan pribadi,
   email, atau seluruh `track_points` mentah.
5. **Dapat dilacak:** setiap hasil disimpan di `ai_insights` (model, `prompt_version`,
   `input_hash`, token) dan berlabel “Dihasilkan AI — periksa kembali”.
6. **Berjalan di queue**, bukan sinkron di request: timeout `timeout_s`, `max_tries`,
   dan `failed()` yang menulis log; batas laju `rate_limit_per_user_per_hour`.
7. Nama model dari `AI_MODEL` (env), bukan hardcode, supaya bisa diganti tanpa deploy kode.

### Kasus penggunaan, berurutan prioritas
| # | Kasus                                   | Input                              | Output                         |
| - | --------------------------------------- | ---------------------------------- | ------------------------------ |
| 1 | Ringkasan track (narasi + sorotan)      | statistik track + geometri sederhana | teks Indonesia, JSON terstruktur |
| 2 | Tanya-jawab data proyek (read-only)     | hasil query terbatas lewat service | jawaban + sumber data          |
| 3 | Petunjuk anomali (misal bacaan janggal) | agregat bacaan, hasil validator    | daftar saran **untuk dicek manusia** |
| 4 | Usulan rute/jalur                       | waypoint + preferensi              | rute draft yang bisa diedit    |

Kasus 3 sebatas *petunjuk*: validasi BT (|BT_lapangan − BT_hitung| ≤ 0.002 m)
dan toleransi SNI tetap diputuskan kode, bukan AI.

### Pengujian AI
- Semua panggilan AI di-mock di test (`Http::fake`); tidak ada test yang memanggil API sungguhan.
- Test wajib: saklar mati → 403/diabaikan; batas laju; payload tidak memuat `notes`/data pribadi;
  hasil tersimpan di `ai_insights`; kegagalan → `failed()` tercatat.

---

## 12. Standar Pengujian (mengikuti `engineering-rules.md`)

- Setiap fungsi geometri → unit test di `tests/Unit/`: haversine, bearing, RDP, DD↔DMS,
  DD↔UTM↔MGRS (uji *round-trip* ≤ 0.001 m untuk UTM, dan bandingkan dengan titik acuan
  yang nilainya diverifikasi dari sumber otoritatif — jangan mengarang angka uji).
- `TrackStatsService`: uji noise threshold, auto-pause, gain/loss dengan data sintetis yang
  hasilnya bisa dihitung tangan.
- Batch titik: uji **idempotensi** (`client_batch_id` sama dua kali → tidak duplikat).
- Fitur: `RefreshDatabase`, uji otorisasi (user lain → 403), batas `track_max_points`,
  validasi impor GPX (ukuran, koordinat di luar rentang).
- Presisi: `assertEqualsWithDelta($expected, $actual, 0.000001)` untuk nilai `NUMERIC(·,6)`
  ke bawah; jarak ke 3 desimal.
- E2E Playwright: ingat MapLibre canvas hanya bisa diuji sebagai proxy
  (canvas visible + tanpa console error). Re-seed sebelum `npm run test:e2e`.
- Seeder `OutdoorDemoSeeder`: **idempoten**, standalone (tidak dipanggil dari `DatabaseSeeder`),
  hapus data lama dulu sebelum membuat baru.

---

## 13. Fase & Definisi Selesai

Cara kerja tetap: **satu sub-langkah per giliran**, baca file sebelum menulis kode,
**grep verifikasi setelah setiap patch**, build dengan `npm run build`, commit per fitur,
perbarui `docs/claude.md` dan dokumen ini di akhir sesi.

| Fase | Isi                                                        | Selesai bila                                                         |
| ---- | ---------------------------------------------------------- | -------------------------------------------------------------------- |
| 1    | Migration + model + CRUD waypoint, impor GPX track, tampil di `OutdoorMap` | test hijau; track dari GPX nyata tampil; PHPUnit + e2e |
| 2    | Rekam track live + batch idempoten + `FinalizeTrackJob` + rute | rekam 10+ menit di HP nyata tanpa duplikat titik; statistik benar    |
| 3    | Konverter koordinat, ukur jarak/luas, HUD navigasi, profil elevasi | unit test round-trip lulus; verifikasi manual vs alat lain        |
| 4    | PWA, cache tile terbatas, antrean offline                  | rekam offline lalu sinkron saat online tanpa kehilangan titik        |
| 5    | AI (kasus 1 → 2 → 3 → 4)                                   | semua test mock lulus; saklar & batas laju terbukti                  |

Jangan mulai fase N+1 sebelum fase N lengkap dengan dokumennya.

---

## 14. Keputusan Terbuka (perlu dikonfirmasi sebelum parameter dikunci)

| # | Pertanyaan                                                           | Usulan default                                                        |
| - | -------------------------------------------------------------------- | --------------------------------------------------------------------- |
| 1 | Bentuk produk: PWA web saja, atau nanti dibungkus native?            | **PWA web**; native ditunda                                           |
| 2 | Item Outdoor boleh tanpa proyek (milik user)?                        | **Ya** (`project_id` nullable); log aktivitas hanya jika ada proyek   |
| 3 | Peta offline sungguhan via PMTiles milik sendiri?                    | **Tunda** ke setelah fase 4 dasar                                     |
| 4 | Sumber elevasi untuk profil jika `ele` GPS kosong?                   | **Elevasi GPS apa adanya**; DEM eksternal diputuskan belakangan, setelah cek lisensi |
| 5 | Penyedia AI                                                          | **Claude (Anthropic API)** lewat backend; model via `AI_MODEL`        |
| 6 | Berbagi track/waypoint antar-pengguna?                               | **Tidak** di v1 (hanya ekspor GPX)                                    |

Mohon tandai pilihan akhir di tabel ini; setelah itu status dokumen diubah dari DRAFT ke TETAP
dan blok config bagian 3 resmi dipakai.

---

## 15. Utang Teknis Awal (diketahui sejak desain)

- Utara grid/magnetis belum dikoreksi pada bearing (v1 hanya utara geografis).
- Jarak pakai model bola, bukan elipsoid (tidak untuk keperluan legal/kadaster).
- Perekaman latar belakang tidak didukung (batasan platform web).
- Elevasi GPS rentan galat besar vs elevasi leveling — hanya informatif.

## 16. Status Implementasi Fase 1 (hasil — sumber kebenaran untuk kode yang sudah ada)

Bagian 1–15 adalah desain. Bagian ini mencatat apa yang benar-benar dibangun
dan **penyimpangan dari desain**; bila bertentangan, bagian ini yang berlaku.

### 16.1 Penyimpangan dari desain
- `track_points.recorded_at` **nullable** (migrasi `2026_11_11_100000_make_track_points_recorded_at_nullable`),
  supaya GPX tanpa `<time>` bisa diimpor. Statistik waktu (`duration_s`, `moving_time_s`) bernilai `null`
  bila ada satu saja titik tanpa waktu; statistik elevasi `null` bila tidak ada `<ele>`.
- `tracks` punya `INDEX(user_id)` dan `INDEX(project_id)` (tidak tertulis di bagian 5).
- Kolom `metadata` memakai `default('{}')`, bukan `'{}'::jsonb`: test berjalan di SQLite.
- Key config baru di `config/geolevel.php`: `earth_radius_m` (6371008.8) dan `waypoint_categories`
  (`generic, survey_marker, camp, water, danger, summit, parking`).
- Item Outdoor boleh tanpa proyek (`project_id` nullable). Bila terisi, akses juga lewat `ProjectPolicy`.

### 16.2 Komponen backend
- Migrasi `waypoints`, `tracks`, `track_points`; model + factory; `TrackPoint` immutable
  (`updating` melempar `LogicException`, tanpa timestamps).
- `OutdoorItemPolicy` didaftarkan manual di `AppServiceProvider` (`Gate::policy`) untuk `Waypoint` dan `Track`.
- Service: `WaypointService`, `GeoMathService` (`haversine`, `simplify`), `TrackStatsService`,
  `GpxService`, `TrackImportService`, `TrackService`. Exception: `GpxParseException`.
- Request: `StoreWaypointRequest`, `ImportGpxRequest`.
- Controller: `WaypointController` (JSON), `TrackController`, `OutdoorController`.
- Nama route: `outdoor.index`; `outdoor.waypoints.{index,store,update,destroy,import}`;
  `outdoor.tracks.{index,import,destroy,geometry,show}`.
- Data Outdoor tidak pernah menyentuh `LevelingCalculationService`, `ClosureCheckerService`, `AdjustmentService`.

### 16.3 Perilaku GPX (impor)
- Satu `Track` per `<trk>`; semua `<trkseg>` dalam satu `<trk>` digabung berurutan. `<rte>` diabaikan.
- Track dengan < 2 titik dilewati; bila tidak ada track tersisa, impor ditolak (`GpxParseException`).
- Lat/lng/ele disimpan sebagai string NUMERIC (regex `/^-?\d+(\.\d+)?$/`); float hanya untuk cek rentang.
- `recorded_at` hanya diterima bila cocok regex ISO ketat; disimpan sebagai string UTC `Y-m-d H:i:s.vP`
  (tanpa objek Carbon per titik, demi memori).
- Nama dipotong 100 (waypoint) / 150 (track), catatan 500. `<wpt>` tanpa nama menjadi "Waypoint N".
- Batas ukuran/jumlah titik/jumlah waypoint dari `config('geolevel.outdoor.*')`; batas `<wpt>` berlaku juga saat impor track.
- Insert titik per 1000 baris dalam satu transaksi, `seq` mulai 1; `metadata.source_file` = nama file asli.
- Statistik satu kali lewat (`TrackStatsService::fromPoints`): jarak haversine; waktu bergerak = segmen
  dengan kecepatan >= `stats_moving_speed_min_mps` (0.3 m/s); naik/turun dengan ambang `stats_elevation_noise_threshold_m`
  (3.0 m, inklusif).

### 16.4 Endpoint geometri (tampilan)
`GET outdoor.tracks.geometry` mengembalikan JSON: `coordinates` (`[lng, lat]`), `bbox`, `point_count`,
`simplified_count`, `tolerance_m`. Penyederhanaan RDP (iteratif, jarak titik-ke-segmen, proyeksi equirectangular
pada lintang rata-rata) dengan `simplify_tolerance_display_m` = 2 m. **Hanya untuk tampilan**; data di
`track_points` tidak diubah dan statistik tidak dihitung dari geometri sederhana.

### 16.5 Kontrak komponen frontend
Konstanta bersama: `resources/js/Components/Outdoor/outdoorCategories.js`
(`TRACK_COLOR` `#0EA5E9`, `CATEGORY_META`, `CATEGORY_OPTIONS`, `categoryColor`, `categoryLabel`).
Garis track lebar 3 (terpilih 5); polyline survei tetap `#DC2626`; marker waypoint berupa lingkaran berwarna kategori.

**`OutdoorMap.vue`** (lazy-loaded via `defineAsyncComponent`, memakai `waitForStyleReady()`):
```js
defineProps({
  tracks:          { type: Array,   default: () => [] },
  waypoints:       { type: Array,   default: () => [] },
  selectedTrackId: { type: Number,  default: null },
  fitToData:       { type: Boolean, default: true },
  height:          { type: String,  default: '500px' },
})
defineEmits(['select-track', 'select-waypoint'])
```

**`WaypointForm.vue`** (mode buat bila `waypoint` null, mode ubah bila terisi; koordinat dan elevasi dikirim
sebagai string; galat 422 ditampilkan per kolom):
```js
defineProps({
  waypoint: { type: Object, default: null },
  projects: { type: Array,  default: () => [] },
})
defineEmits(['saved', 'cancel'])
```

### 16.6 Batas lingkungan
- PHP-FPM (`/etc/php/8.5/fpm/php.ini`): `upload_max_filesize` 12M, `post_max_size` 14M, `memory_limit` 256M.
- Terukur: parse 85k titik (8,7 MB) puncak 106,5 MB; 100k titik 88,5 MB; impor 85k titik ~11 s; geometri 0,44 s.
- Nginx `client_max_body_size` **belum diverifikasi** terhadap batas 10 MB (lihat 16.8).

### 16.7 Pengujian
- Kelas test baru: `OutdoorModelsTest`, `WaypointServiceTest`, `WaypointControllerTest`, `WaypointImportTest`,
  `GeoMathServiceTest`, `TrackStatsServiceTest`, `GpxServiceTest`, `TrackImportTest`, `GeoMathSimplifyTest`,
  `TrackGeometryTest`, `OutdoorPagesTest`. Suite penuh: **315 test, 859 assertion** (sebelum Outdoor: 216).
- Seeder: `php artisan db:seed --class=OutdoorDemoSeeder` (user `outdoordemo@geolevel.com` / `password`;
  idempoten; mandiri, tidak dipanggil dari `DatabaseSeeder`; track lewat `TrackImportService`).
- E2E: `tests/e2e/outdoor.spec.ts` (3 tes: canvas + tanpa error, toggle Peta/Satelit, form waypoint).
  Jalankan seeder dulu. Error console "Failed to load resource" (tile) difilter. Garis track di WebGL **tidak** dinilai e2e.

### 16.8 Utang teknis & belum terverifikasi
- Impor 85k titik ~11 s sinkron di request web; kandidat job antrean di Fase 2.
- RDP worst-case O(n²) pada jalur sangat berliku.
- Test berjalan di SQLite; perilaku `timestampTz(…, 3)` dan `jsonb` di PostgreSQL tidak diuji otomatis.
- Nginx `client_max_body_size` belum dicek.
- Kriteria "track dari file GPX nyata tampil di peta" belum diverifikasi manusia di browser (hanya data seeder sintetis).

## 17. Fase 2: Perekaman Live, Kontrak (2.7, ditulis sebelum implementasi)

Bila bertentangan dengan bagian 7 dan 8, bagian ini yang berlaku. Tidak ada dependensi npm baru:
IndexedDB dipakai langsung, UUID dari `crypto.randomUUID()` (HTTPS sudah terpenuhi).

### 17.1 Kontrak server (sudah terbangun, 2.3 sampai 2.5)
| Route | Body | Sukses | Galat |
| ----- | ---- | ------ | ----- |
| `GET outdoor.tracks.active` | – | 200 `{data: Track\|null}` | – |
| `POST outdoor.tracks` | `{name?, project_id?}` | 201 `{data: Track}` | 409 `active_exists` (+ `track`) |
| `POST outdoor.tracks.points` | `{client_batch_id, points[]}` | 200 `{data: hasil}` | 409 `limit_reached` / `not_active`; 422 validasi |
| `POST outdoor.tracks.pause` / `resume` / `finish` | – | 200 `{data: Track}` | 409 `{message, reason, track}` |

- `Track` = `{id, name, status, project_id, point_count, started_at (ISO 8601)}`.
- Galat 409 berbentuk `{message, reason, track|null}`.
- Titik: `{lat, lng, ele?, accuracy_m?, speed_mps?, heading_deg?, recorded_at}`; `recorded_at` ISO 8601 ber-zona
  waktu (`Date.toISOString()` memenuhi); maksimum 300 titik per batch (10 x `record_batch_size`).
- Klien hanya membaca `data.limit_reached` dari balasan batch; semua 2xx berarti batch tersimpan atau sudah pernah tersimpan.

### 17.2 Props halaman
`OutdoorController::index` menambah prop `recordingConfig` (hanya kunci ini, diambil dari `config('geolevel.outdoor')`):
`record_min_distance_m`, `record_min_interval_s`, `record_max_accuracy_m`, `record_auto_pause_speed_mps`,
`record_auto_pause_after_s`, `record_batch_size`, `record_batch_interval_s`, `record_use_wake_lock`,
`track_max_points`, `offline_queue_max_points`. Komponen tidak boleh punya angka hardcode.

### 17.3 Modul pendukung (`resources/js/Components/Outdoor/`)
- `recorderFilter.js`, fungsi murni tanpa DOM (bisa diuji tanpa browser):
  `haversineM(a, b)`, `evaluateFix(fix, lastAccepted, cfg)` mengembalikan `{accept, reason}`
  (`reason`: `accuracy` | `distance` | `interval` | `ok`), `nextAutoPause(state, speedMps, nowMs, cfg)`.
  Pengecualian presisi sama seperti `GeoMathService`: float JS hanya di dalam fungsi ini.
- `recorderQueue.js`, pembungkus IndexedDB. DB `geolevel-outdoor` versi 1, dua store:
  `buffer` (autoIncrement, index `track_id`; titik diterima yang belum dibatch) dan
  `pending_batches` (keyPath `client_batch_id`; `{client_batch_id, track_id, created_at, points}`).
  API: `addPoint`, `makeBatch(trackId, size)` (memindahkan titik buffer ke `pending_batches` dalam satu transaksi,
  UUID dibuat saat itu), `listPending(trackId)`, `deletePending(id)`, `countQueued(trackId)`.

### 17.4 Kontrak `TrackRecorder.vue` (lazy-loaded via `defineAsyncComponent`)
```js
defineProps({
  config:   { type: Object, required: true },            // = recordingConfig (17.2)
  projects: { type: Array,  default: () => [] },         // [{id, name}] untuk pilihan proyek saat mulai
})
defineEmits([
  'position',   // { lat, lng, accuracy_m }  (number, hanya untuk tampilan), tiap fix mentah
  'trace',      // Array<[lng, lat]> titik diterima sesi ini; maksimal 1 kali per detik
  'state',      // 'idle'|'starting'|'recording'|'paused'|'autopaused'|'finishing'
  'finished',   // Track payload (17.1) setelah finish sukses
])
```
Statistik langsung yang ditampilkan (perkiraan klien, bukan hasil akhir): jumlah titik diterima, jarak haversine
kumulatif, waktu berjalan, jumlah batch menunggu, status koneksi. Label UI bahasa Indonesia.

### 17.5 Perilaku
1. **Mulai:** `POST outdoor.tracks`. Jika 409 `active_exists`, tampilkan tawaran pemulihan (poin 8) memakai `track` dari galat.
2. **Filter** (`evaluateFix`, bagian 8 poin 2): tolak jika `accuracy > record_max_accuracy_m`; titik pertama sesi
   lolos bila akurasi baik; selanjutnya wajib jarak ke titik diterima terakhir >= `record_min_distance_m`
   **dan** selang >= `record_min_interval_s`. `recorded_at` = `new Date(position.timestamp).toISOString()`.
   `altitude`, `speed`, `heading` yang `null`/`NaN` dikirim sebagai `null` (bukan 0).
3. **Auto-pause (hanya klien):** kecepatan = `coords.speed`, jika `null` dihitung dari dua fix mentah berurutan.
   Di bawah `record_auto_pause_speed_mps` selama `record_auto_pause_after_s` berarti `autopaused`: titik tidak
   disimpan, server tetap `recording`. Kembali merekam bila kecepatan >= ambang. Tidak memanggil endpoint `pause`.
4. **Jeda manual:** tombol Jeda memanggil `pause`/`resume`; saat `paused` titik tidak disimpan.
5. **Simpan lokal dulu:** titik diterima langsung ke store `buffer`. Batch dibentuk bila `buffer` mencapai
   `record_batch_size` titik **atau** `record_batch_interval_s` detik berlalu (dan buffer tidak kosong).
6. **Kirim:** satu batch berjalan sekaligus, berurutan menurut `created_at`. Hapus dari `pending_batches` hanya
   setelah 2xx. Galat jaringan atau 5xx: coba lagi dengan UUID yang sama (jeda 2, 4, 8, hingga 30 detik).
   409 `limit_reached`: hapus batch, hentikan perekaman, tampilkan pesan. 409 `not_active`: buang batch, tampilkan pesan.
   422: buang batch, tampilkan galat (bug klien, jangan diulang). 401/419: batch tetap disimpan, minta muat ulang halaman.
7. **Batas:** berhenti menerima titik bila `point_count + antrean >= track_max_points`, atau antrean lokal
   >= `offline_queue_max_points`, dengan pesan jelas.
8. **Pemulihan saat dimuat:** `GET outdoor.tracks.active`. Jika ada, tampilkan banner
   "Ada rekaman aktif" dengan **Lanjutkan** (jika `paused` panggil `resume`, lalu mulai `watchPosition` dan kirim ulang
   `pending_batches` track itu) dan **Selesaikan** (kirim semua batch menunggu, lalu `finish`).
   Batch yatim untuk track yang sudah selesai akan dijawab 409 `not_active` dan dibuang.
9. **Selesai:** kuras semua batch dulu; `finish` hanya dipanggil bila kuras sukses; bila gagal, tampilkan galat
   dan biarkan rekaman tetap aktif.
10. **Wake Lock:** diminta bila `config.record_use_wake_lock` dan `navigator.wakeLock` tersedia; diminta ulang saat
    tab kembali terlihat (dilepas otomatis saat tersembunyi).
11. **Tab tersembunyi:** tampilkan peringatan (batasan bagian 8), jangan berhenti merekam diam-diam.
12. `watchPosition` dihentikan dan wake lock dilepas di `onBeforeUnmount` serta saat selesai.

### 17.6 Perluasan kontrak `OutdoorMap.vue` (menambah 16.5, tidak mengubah prop lama)
```js
// prop baru
liveTrace:    { type: Array,  default: () => [] },   // Array<[lng, lat]>
livePosition: { type: Object, default: null },       // { lat, lng } | null
```
Layer baru `outdoor-live-track` (garis putus-putus `TRACK_COLOR`, lebar 4) dan `outdoor-live-position`
(lingkaran), terpisah dari layer track tersimpan; ditambahkan lewat `waitForStyleReady()`.
Peta tidak melakukan `fitBounds` otomatis saat `liveTrace` berubah.

### 17.7 Penempatan UI
`TrackRecorder` menjadi section baru di sidebar `Outdoor/Index.vue`, di atas "Impor GPX". Setelah `finished`,
halaman memanggil `router.reload({ only: ['tracks'] })` dan mengosongkan `liveTrace`.

### 17.8 Pengujian
- `recorderFilter.js` murni: belum ada vitest di proyek, tidak dipasang tanpa persetujuan. Logika filter diuji lewat
  e2e Playwright (`context.setGeolocation` bertahap + `grantPermissions(['geolocation'])`), dengan nilai yang
  jarak/selangnya bisa dihitung tangan.
- E2E rekam: mulai, 3 sampai 5 posisi tiruan melewati filter, selesai, track muncul di daftar dengan `point_count` benar.
  Tambahan: jaringan diputus (`context.setOffline`) lalu dipulihkan, jumlah titik di server tidak ganda.
- Kriteria selesai Fase 2 tetap: rekam 10+ menit di HP nyata tanpa titik ganda (butuh HTTPS yang dipercaya HP).

### 17.9 Perencana Rute (2.8b, ditulis sebelum implementasi)

**Server (sudah terbangun, 2.6):** `outdoor.routes.{index,store,update,destroy}`, semuanya JSON.
- Bentuk rute: `{id, user_id, project_id, name, geometry, distance_m, notes, created_at, updated_at}`;
  `geometry` = `[[lat, lng], ...]` (float, urutan **lat dulu**, sesuai bagian 5); `distance_m` = string 3 desimal.
- `store` membalas 201 `{data: Route}`; `update` 200 `{data: Route}`; `destroy` 200 `{message}`; validasi 422 `{errors}`.
- Body store **dan** update: `{name (wajib, maks 150), geometry (wajib, 2 sampai route_max_vertices titik), notes?, project_id?}`.
  `distance_m` dari klien diabaikan; server selalu menghitung haversine.

**Props halaman (menambah 17.2):** `OutdoorController::index` menambah `routes` (daftar rute milik user, terbaru dulu,
lewat `RouteService::listFor`) dan `routeMaxVertices` (Number, dari `config('geolevel.outdoor.route_max_vertices')`).
Prop `recordingConfig` tidak diubah. Muat ulang daftar: `router.reload({ only: ['routes'] })`.

**Konversi koordinat:** DB dan API memakai `[lat, lng]`; MapLibre dan seluruh prop peta memakai `[lng, lat]`.
Konversi hanya di `Index.vue` (ke peta) dan `RoutePlanner.vue` (ke API saat simpan, dan saat memuat rute untuk diubah).

**Perluasan kontrak `OutdoorMap.vue` (menambah 16.5 dan 17.6, prop lama tidak berubah):**
```js
routes:     { type: Array,   default: () => [] },   // [{ id, name, coordinates: [[lng, lat], ...] }] rute tersimpan yang ditampilkan
routeDraft: { type: Array,   default: () => [] },   // Array<[lng, lat]> vertex yang sedang digambar
drawRoute:  { type: Boolean, default: false },      // mode gambar
// emit baru: 'add-route-vertex' -> { lat, lng }
```
- Layer baru: `outdoor-routes-line` (oranye `#F97316`, lebar 3, solid), `outdoor-route-draft-line` (oranye, lebar 4,
  putus-putus), `outdoor-route-draft-vertices` (lingkaran kecil oranye berpinggir putih). Semua lewat `renderAll()`
  dan `waitForStyleReady()`; parent mengirim array **baru** tiap perubahan (watch dangkal).
- Saat `drawRoute` true: klik peta hanya meng-emit `add-route-vertex`, tidak memilih track/waypoint; kursor `crosshair`.
- Tidak ada `fitBounds` otomatis untuk `routes` maupun `routeDraft`. Warna rute masuk ke legenda peta.

**`RoutePlanner.vue`** (lazy-loaded via `defineAsyncComponent`; section "Rute" di sidebar `Index.vue`, di bawah "Waypoint"):
```js
defineProps({
  routes:   { type: Array,   default: () => [] },     // bentuk server (geometry [lat, lng])
  projects: { type: Array,   default: () => [] },     // [{ id, name }]
  maxVertices: { type: Number, required: true },      // = routeMaxVertices
  vertices: { type: Array,   default: () => [] },     // draft Array<[lng, lat]> (dimiliki parent)
  drawing:  { type: Boolean, default: false },
})
defineEmits([
  'update:vertices',   // Array<[lng, lat]> baru: Urungkan, Kosongkan, atau memuat rute untuk diubah
  'update:drawing',    // Boolean
  'visible-change',    // Array<number> id rute yang dicentang tampil
  'saved',             // Route dari server setelah store/update sukses
])
```
Perilaku:
1. Tombol **Rancang Rute** mengaktifkan mode gambar. Parent menambah vertex pada event `add-route-vertex` selama
   `vertices.length < maxVertices`; bila penuh, planner menampilkan pesan (tanpa angka hardcode).
2. Tombol: **Urungkan** (buang vertex terakhir), **Kosongkan**, **Simpan** (aktif bila nama terisi dan >= 2 vertex),
   **Batal** (kosongkan draft, keluar mode gambar, lepas mode ubah).
3. Perkiraan jarak saat menggambar memakai `haversineM` dari `recorderFilter.js` (hanya perkiraan klien); daftar
   rute menampilkan `distance_m` dari server.
4. Simpan: tanpa `editingId` memakai `POST outdoor.routes.store`, dengan `editingId` memakai `PUT outdoor.routes.update`
   (body lengkap). Body: `{name, geometry: vertices -> [lat, lng], notes, project_id}`. Galat 422 tampil per kolom.
5. Per rute di daftar: kotak centang tampil/sembunyi (awalnya semua tersembunyi), **Ubah** (memuat geometri ke draft,
   mode ubah, mode gambar aktif), **Hapus** (`confirm()` lalu `DELETE`, lalu `saved`-style reload).
6. Setelah simpan atau hapus sukses: parent `router.reload({ only: ['routes'] })` dan draft dikosongkan.

**Pengujian 2.8b:** PHPUnit untuk prop `routes` dan `routeMaxVertices` pada `outdoor.index`; e2e Playwright: masuk mode
gambar, klik tiga titik pada kanvas, isi nama, simpan, rute tampil di daftar dengan jarak > 0 dari server, hapus.
Garis di WebGL tidak dinilai e2e (sama seperti catatan 16.7).

---

## 18. Status Implementasi Fase 2 (hasil; bila bertentangan dengan 5, 8, 17, bagian ini berlaku)

### 18.1 Dibangun
- Backend: `track_point_batches`, `routes`; `TrackRecordingService`, `TrackRecordingController`, `FinalizeTrackJob`,
  event `TrackFinished` (+ listener `DispatchFinalizeTrackJob`, `LogTrackFinished`), `RouteService`, `OutdoorRouteController`.
  Prop halaman `recordingConfig`, `routes`, `routeMaxVertices`.
- Frontend: `recorderFilter.js`, `recorderQueue.js`, `TrackRecorder.vue`, `RoutePlanner.vue`; `OutdoorMap.vue` + prop
  `liveTrace`, `livePosition`, `routes`, `routeDraft`, `drawRoute`, event `add-route-vertex`; `ROUTE_COLOR` `#F97316`.
- Tambahan di luar 17.3: `speedBetween`, `toFix`, `toPayloadPoint` di `recorderFilter.js`.
- Pengujian: PHPUnit 379; `npm run test:js` (`node --test tests/js/*.test.mjs`, 10 tes, tanpa dependensi);
  e2e `outdoor.spec.ts` (3), `outdoor-recording.spec.ts` (2, termasuk balasan batch hilang lalu retry), `outdoor-routes.spec.ts` (1).

### 18.2 Penyimpangan dan keputusan desain
- Idempotensi batch: UNIQUE(track_id, client_batch_id); `seq` dari server (MAX(seq)+1, kunci baris track); retry batch lama dijawab 200 `duplicate`.
- Satu rekaman aktif per user; start kedua 409 `active_exists`. Tidak ada auto-finish.
- Batch yang melewati `track_max_points` diterima sebagian dengan `limit_reached=true`; batch berikutnya 409 `limit_reached`.
- `FinalizeTrackJob` memakai `TrackStatsService::fromPoints`, bukan window function SQL (test berjalan di SQLite). Menyimpang dari bagian 5.
- Auto-pause hanya di klien; server tetap `recording`.
- `OutdoorRoute` (hindari bentrok facade Route); parameter `{outdoorRoute}`; `distance_m` selalu dihitung server; update memakai body lengkap.
- Event discovery dimatikan; listener didaftar manual di `EventServiceProvider::$listen`.
- Log aktivitas hanya bila track punya proyek.
- Peringatan tab tersembunyi baru tampil saat tab kembali terlihat.

### 18.3 Utang teknis dan belum terverifikasi
- **Fase 2 selesai.** Uji rekam di HP nyata lewat tunnel Cloudflare (2026-11-22) dilaporkan berhasil oleh pengembang. Output query titik ganda dan catatan auto-pause/jitter tidak dilampirkan; hasil rinci belum tercatat di sini.
- Batch yatim milik track yang sudah selesai tetap di IndexedDB bila tidak pernah dikirim ulang.
- `recorderQueue.js` hanya teruji lewat e2e (Node tanpa IndexedDB).
- Mode Ubah rute belum punya e2e; garis WebGL tidak dinilai e2e.
- Impor GPX nyata di /outdoor, tile CARTO tanpa watermark, dan pengujian di PostgreSQL (bukan SQLite) belum diverifikasi manusia.
- Nginx `client_max_body_size` 20M: sudah dicek, memenuhi syarat.

---

## 19. Fase 3: Koordinat, Ukur, Navigasi (kontrak; bila bertentangan dengan 10, bagian ini berlaku)

### 19.1 Konverter koordinat (3.1 dan 3.2)
**Backend (3.1, selesai):** `GeoMathService::toUtm`, `fromUtm`, `toMgrs`, `toDms` (deret Krüger n^4, selisih < 1 mm terhadap PROJ;
nilai acuan test dari pyproj dan GeoTrans). Zona hanya 46 sampai 54. Konstanta WGS84 dan faktor skala ada di `config('geolevel.outdoor')`.

**Endpoint (3.2a):** `POST outdoor/convert` (nama `outdoor.convert`), JSON, tanpa simpan ke DB. Satu dari dua bentuk input:
- `{lat, lng}`, atau
- `{zone, hemisphere ('N'|'S'), easting, northing}` (dikonversi ke lat/lng lewat `fromUtm`).

Balasan 200: `{data: {lat, lng, dms: {lat, lng}, utm: {zone, hemisphere, easting, northing}, mgrs}}`.
`lat`/`lng` string 8 desimal; `easting`/`northing` string 3 desimal; `mgrs` string 1 m, atau `null` bila lintang di luar -80..84.
Zona di luar 46 sampai 54: 422 `{errors}` dengan pesan Indonesia. `utm` selalu dihitung ulang dari lat/lng hasil konversi.
MGRS ke koordinat tidak didukung (tidak diminta bagian 10).

**`CoordinateConverter.vue` (3.2b, lazy-loaded; section "Konverter" di sidebar `Index.vue`):**
```js
defineProps({ defaultFormat: { type: String, default: 'dd' } })   // config default_coordinate_format
defineEmits([])
```
Dua mode input (DD atau UTM), tombol Konversi, hasil keempat format (DD, DMS, UTM, MGRS) masing-masing dengan tombol Salin;
galat 422 tampil per kolom. `OutdoorController::index` menambah prop `defaultCoordinateFormat`.

### 19.2 Ukur jarak dan luas (3.3)
**Endpoint (3.3a):** `POST outdoor/measure` (nama `outdoor.measure`), JSON, tanpa simpan.
Body: `{mode: 'line'|'area', points: [[lat, lng], ...]}`; `line` minimal 2 titik, `area` minimal 3, maksimal `route_max_vertices`.
Balasan 200: `{data: {distance_m, perimeter_m, area_m2}}` (string 3 desimal; `perimeter_m` dan `area_m2` hanya untuk `area`, selain itu `null`).
`distance_m` = panjang jalur terbuka (haversine); `perimeter_m` = jalur tertutup. Luas = shoelace pada koordinat UTM.
Mode `area`: semua titik harus dalam satu zona UTM 46 sampai 54, bila tidak 422 pada `points`. Titik di dua sisi ekuator tetap dihitung.
Luas adalah luas **grid UTM**, tidak dikoreksi faktor skala (selisih sekitar 0,08% di dekat meridian tengah); bukan untuk keperluan kadaster.

**`MeasureTool.vue` (3.3b, lazy-loaded; section "Ukur" di sidebar `Index.vue`):**
```js
defineProps({
  vertices:    { type: Array,   default: () => [] },   // Array<[lng, lat]> milik parent
  measuring:   { type: Boolean, default: false },
  maxVertices: { type: Number,  required: true },      // = routeMaxVertices
})
defineEmits(['update:vertices', 'update:measuring'])
```
Memakai ulang mode gambar peta (`draw-route`, `route-draft`, `add-route-vertex`; titik ukur tampil seperti draft rute).
Parent hanya mengizinkan satu alat aktif (rute atau ukur) dan menambah titik ke alat yang aktif.
Mode Jarak atau Luas; tombol Mulai Ukur, Urungkan, Kosongkan, Hitung (aktif bila titik >= 2 untuk Jarak, >= 3 untuk Luas), Selesai.
Hasil dari `outdoor.measure`; galat 422 tampil apa adanya. Mengubah mode atau titik menghapus hasil lama.

### 19.3 HUD navigasi (3.4)
**Fungsi murni (3.4a, `recorderFilter.js`):**
- `bearingDeg(from, to)`: azimut awal 0 sampai 360 derajat dari utara geografis (utara grid dan magnetis tidak dikoreksi).
- `movingSpeedMps(samples, nowMs, windowS = 60)`: `samples = [{lat, lng, t}]`; memakai sampel dalam jendela `windowS` detik terakhir;
  kecepatan = perpindahan bersih (titik terlama ke terbaru) dibagi selisih waktu, `null` bila kurang dari 2 sampel atau waktu tidak maju.
  Perpindahan bersih dipilih agar jitter GPS saat diam tidak terbaca sebagai kecepatan.
- `etaSeconds(distanceM, speedMps, minSpeedMps)`: `null` bila kecepatan `null` atau di bawah `minSpeedMps` (inklusif di ambang), selain itu jarak dibagi kecepatan.

**`NavigationHud.vue` (3.4b, lazy-loaded; section "Navigasi" di sidebar `Index.vue`):**
```js
defineProps({
  waypoints:   { type: Array,  default: () => [] },   // [{ id, name, lat, lng, category }]
  minSpeedMps: { type: Number, required: true },      // config stats_moving_speed_min_mps
})
defineEmits([])
```
Pilih waypoint tujuan, tombol Mulai Navigasi (menjalankan `watchPosition` sendiri) dan Berhenti. Tampilan: jarak (haversine), bearing (derajat dan arah mata angin),
kecepatan dan ETA ("—" bila `etaSeconds` null). `OutdoorController::index` menambah prop `movingSpeedMinMps`.
Belum ada garis ke tujuan di peta.

---

## Referensi

- `docs/map.md` — stack peta, `mapStyle.js`, pola `waitForStyleReady()`
- `docs/engineering-rules.md` — presisi, testing, audit
- `docs/architecture.md` — layer, job, event
- *Survei Terestris Geospasial* (Direktorat SMK) — sistem UTM Indonesia (zona 46–54), Bab VI
- AlpineQuest — https://alpinequest.net
