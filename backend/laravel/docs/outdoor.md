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
8. **Tile hanya dari provider gratis tanpa API key** (CARTO Voyager, Esri World Imagery).
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
  (props tidak boleh diubah diam-diam).

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

## Referensi

- `docs/map.md` — stack peta, `mapStyle.js`, pola `waitForStyleReady()`
- `docs/engineering-rules.md` — presisi, testing, audit
- `docs/architecture.md` — layer, job, event
- *Survei Terestris Geospasial* (Direktorat SMK) — sistem UTM Indonesia (zona 46–54), Bab VI
- AlpineQuest — https://alpinequest.net
