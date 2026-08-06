# GeoLevel — Waterpass Survey Processing & Visualization

Engineering surveying and leveling web application for civil engineers and surveyors.

Replaces Excel-based workflows with an automated pipeline:
raw field readings → corrected elevations → charts → PDF field book.

---

## Stack

| Layer        | Technology                        |
| ------------ | --------------------------------- |
| Backend      | Laravel 11 (PHP 8.5.4)           |
| Database     | PostgreSQL 16                    |
| Frontend     | Inertia.js + Vue 3 + Vite        |
| UI Library   | Tailwind CSS v3                  |
| Charts       | Chart.js (via vue-chartjs)       |
| Map          | MapLibre GL JS v5.24.0 (lazy-loaded, CARTO + Esri tiles) |
| PDF Export   | DomPDF (barryvdh/laravel-dompdf) |
| Excel Export | Maatwebsite Laravel Excel        |
| Auth         | Laravel Breeze (Blade stack)     |
| Queue        | Laravel Queue (database driver)  |
| Web Server   | Nginx 1.28.3 + PHP 8.5-FPM       |
| Process Mgr  | Supervisor 4.3.0                 |

> Note: PHP 8.5.4 on Ubuntu 25.04 (resolute). Auth uses Breeze Blade stack
> (not Inertia) — login/register pages are standard Blade views, all other
> pages use Inertia + Vue 3. This is intentional and working correctly.
> Do NOT upgrade `maatwebsite/excel` — known phpoffice/phpspreadsheet
> incompatibility with PHP 8.5.

## Production Environment

| Item | Value |
| ---- | ----- |
| OS | Ubuntu 25.04 (resolute) via WSL2 |
| URL | https://geolevel.local |
| WSL IP | 172.19.176.181 (changes on Windows restart — run `bash ~/update-hosts.sh`) |
| Project path | `/home/ciko/workspace/geolevel/backend/laravel` |
| Nginx config | `/etc/nginx/sites-available/geolevel` (HTTP→HTTPS redirect + TLS) |
| SSL cert | `/etc/ssl/geolevel/geolevel.local.crt` (self-signed, valid 10 tahun, trusted di Windows) |
| Supervisor configs | `/etc/supervisor/conf.d/geolevel-worker.conf` + `geolevel-scheduler.conf` |
| Queue workers | 2 processes (`geolevel-worker:geolevel-worker_00/01`) |
| Scheduler | 1 process (`geolevel-scheduler`) |
| PostgreSQL | Bisa tidak auto-start setelah restart WSL/Windows — cek `sudo service postgresql status` di awal sesi, lihat "Known Fixes Applied" |

### Production commands
```bash
# Status semua service
sudo supervisorctl status

# Restart worker setelah update kode
sudo supervisorctl restart geolevel-worker:*

# Restart PHP-FPM setelah patch backend yang tidak langsung terlihat efeknya
# (OPcache bisa menyajikan bytecode lama — lihat "Known Fixes Applied")
sudo systemctl restart php8.5-fpm

# Update hosts file jika IP WSL berubah (jalankan setiap buka WSL)
bash ~/update-hosts.sh

# Deploy update (build + cache + migrate + restart worker)
bash ~/deploy-update.sh

# Log worker
tail -f /home/ciko/workspace/geolevel/backend/laravel/storage/logs/worker.log

# Cek PostgreSQL jalan sebelum mulai sesi kerja / e2e test
sudo service postgresql status
pg_isready -h 127.0.0.1 -p 5432
```

---

## Current Build Status

> Update this every session before starting work.

### ✅ Done
- Laravel project scaffolding
- Laravel Breeze authentication
- PostgreSQL database connection
- Migrations: `projects`, `readings`, `computed_elevations`, `cross_sections`, `activity_logs`, `survey_points`, `network_legs`
- Migration: soft deletes on `readings`
- Migration: adjusted precision on `computed_elevations`
- Basic route definitions (all API routes registered)
- `LevelingCalculationService` — full calculation pipeline with bcmath
- `ReadingObserver` — dispatches `RecalculateSurveyJob` on saved/updated/deleted
- `RecalculateSurveyJob` — queued, with `failed()` handler
- `ProjectController` — full CRUD + adjust + adjust-reset + calculate + `mapPoints` di `index()`
- `ReadingController` — full CRUD + sequence_no renumber after delete
- `AdjustmentService` — equal, bowditch, least_squares (distance-weighted normal equations)
- All Events: `SurveyRecalculated`, `ClosureChecked`, `AdjustmentApplied`, `ExportGenerated`
- All Listeners: `RunClosureCheck`, `LogClosureResult`, `LogAdjustmentApplied`, `LogExportGenerated`
- `ExportService`, `GeneratePdfExportJob`, `GenerateExcelExportJob`, `GenerateCsvExportJob`
- `ExportController`, `ChartController`
- Form Requests: `StoreReadingRequest`, `UpdateReadingRequest`, `StoreProjectRequest`, `UpdateProjectRequest`, `AdjustProjectRequest`, `StoreSurveyPointRequest`
- Policies: `ProjectPolicy`
- `ExportController` — status-guard returns 403 via `ExportNotAllowedException`
- `barryvdh/laravel-dompdf` installed and wired into `GeneratePdfExportJob`
- `GeneratePdfExportJob` — renders via DomPDF, writes through `Storage::disk('local')`
- `GenerateExcelExportJob` — writes via `Excel::store(..., 'local')`
- `ExportGenerated` event — carries `projectId`, `userId`, `format`, `filePath`
- `resources/views/app.blade.php` — Inertia root template
- `config/inertia.php` published, `pages.paths` corrected to `resources/js/Pages`
- `ClosureCheckerService` — `computeClosureError`, `computeAllowedTolerance`, `determineStatus`, `check`
- `app/Exceptions/ExportNotAllowedException.php`
- Excel export sheets: `ElevasiSheet.php`, `KoreksiSheet.php`, `RingkasanSheet.php`
- `resources/views/exports/field_book.blade.php`
- Vue components (verified against architecture.md contracts):
  - `ElevationTable.vue`, `ClosureStatusBadge.vue`, `LongSectionChart.vue`, `CrossSectionChart.vue`
  - `ExportButton.vue`, `StatusPill.vue`, `TabBtn.vue`, `Field.vue`
  - `Pages/Projects/Index.vue` — listing, search/filter by name+location+status,
    create modal, edit modal, delete confirm modal, least_squares option exposed,
    mini-map overview (lazy-loaded `ProjectsOverviewMap.vue`)
  - `Pages/Projects/Show.vue` — tabs (Bacaan/Elevasi/Jaring/Peta/Grafik/Aktivitas), reading
    CRUD modal dengan live BT validation preview + inline error per field,
    adjustment modal, recalculate button, chart fetch, network legs CRUD,
    tab Peta terintegrasi penuh (koordinat CRUD, GPX import, popup marker, legenda)
- `VisualizationService` — `longSection`, `crossSection`, `crossSectionStations`
- `ChartController` — wired to `VisualizationService`, handles `?station=` query param
- `CrossSection` model — `HasFactory` trait added, `$fillable`, `$casts`, `project()` relation
- `CrossSectionFactory` — default state with `station_name`, `station_distance`, `offsets` JSON array
- Full test suite: **OK (213 tests, 533 assertions)** — zero failures (backend PHPUnit; belum ada penambahan test PHPUnit khusus map di sesi terakhir — lihat utang teknis di `docs/map.md`)
- Network export feature (PDF + Excel untuk hasil loop network least squares):
  - `app/Jobs/Concerns/BuildsNetworkExportData.php` — trait shared helper (buildAdjustedPoints, buildStats, buildConnectivity); kolom DB benar: `from_point`, `to_point`, `distance_m`, `corrected_delta_h`
  - `app/Jobs/GenerateNetworkPdfExportJob.php` — queued, pakai trait, helper methods dihapus
  - `app/Jobs/GenerateNetworkExcelExportJob.php` — queued, pakai trait, 3 sheets
  - `app/Exports/Sheets/NetworkLegsSheet.php` — sheet jalur observasi
  - `app/Exports/Sheets/NetworkAdjustmentSheet.php` — sheet elevasi terkoreksi
  - `app/Exports/Sheets/NetworkSummarySheet.php` — sheet ringkasan statistik
  - `app/Services/NetworkExportService.php` — guard (status=accepted + legs exist) + dispatch
  - `resources/views/exports/network_report.blade.php` — PDF template 3 halaman
  - Routes: `network-legs.export.pdf`, `network-legs.export.excel` (GET)
  - `NetworkLegController::exportPdf/exportExcel` — thin, delegate ke NetworkExportService
  - **Catatan:** service + kedua Job ini tidak lagi dipakai controller (diganti sync-stream,
    lihat "Known Fixes Applied"), tapi test-nya (`NetworkExportControllerTest`, 12 test)
    tetap hijau dan tetap dijalankan — dead code yang aman dibiarkan, kandidat cleanup
- `Show.vue` update: tombol "PDF Jaring" + "Excel Jaring" di tab Jaring — hanya muncul jika `status=accepted` AND `network_std_deviation != null`
- **Map Feature — Fase 1, 2, dan 3 SEMUA SELESAI (per sesi terakhir):**
  - `survey_points` table, model, controller (index/store/update/destroy/importBatch), routes — 11 feature test PASS
  - `resources/js/Components/Map/mapStyle.js` — style config bersama (CARTO Voyager + Esri World Imagery, toggle Peta/Satelit)
  - `SurveyMap.vue` — render marker per tipe (BM/TP/IS/CP), fitBounds otomatis, toggle Peta/Satelit,
    **polyline urutan pengukuran (garis merah `#DC2626`)**, **popup info marker (elevasi/koreksi/jarak
    kumulatif/koordinat/akurasi GPS/tombol Edit)**, **network legs overlay (warna sesuai status proyek)**
  - `LocationPicker.vue` — modal koordinat: mini-map klik/drag, input manual, search Nominatim,
    Geolocation API, toggle Peta/Satelit, **marker referensi (titik lain yang sudah punya koordinat
    ditampilkan sebagai acuan visual, abu-abu semi-transparan dengan label)**
  - `GpxImportModal.vue` — upload .gpx, parse via `gpxparser`, preview + matching status, import batch — diuji dengan file GPX asli
  - `ProjectsOverviewMap.vue` — mini-map di `Projects/Index.vue`, marker warna per status, klik→navigasi
  - `MapLegend.vue` — legenda ikon (warna per tipe titik BM/TP/IS/CP, garis jalur ukur, garis network leg)
  - Tab "Peta" di `Show.vue` — daftar titik dengan/tanpa koordinat, tombol Tambah/Edit/Hapus, tombol Import GPX, legenda
  - `ProjectController::index()` — kirim `mapPoints` (agregat AVG lat/lng per proyek)
  - Semua 5 komponen peta di-lazy-load via `defineAsyncComponent` — `Show-*.js` turun 1,299 kB → 260 kB
  - `vite.config.js` — `chunkSizeWarningLimit: 1200` untuk chunk `mapStyle-*.js` (berisi `maplibre-gl`, ~1MB wajar)
  - `database/seeders/MapDemoSeeder.php` — seeder idempotent khusus dev/test map, user `mapdemo@geolevel.com`
  - `tests/e2e/map.spec.ts` — 3 test e2e, semua hijau (polyline muncul, tetap muncul setelah toggle, titik tanpa koordinat tampil terpisah)

### 🔄 Utang Teknis / Belum Selesai (lihat detail lengkap di `docs/map.md`)
- **Export PDF untuk peta** — belum dikerjakan sama sekali, gambar peta tidak masuk ke field book PDF. **Prioritas utama sesi berikutnya**, sengaja ditunda sampai tampilan visual peta matang (baru selesai di sesi ini).
- **Bentuk ikon custom** per tipe titik (bintang/segitiga/berlian) — sekarang semua lingkaran warna-kode, kosmetik saja
- **Handler `request-add-point`** (klik peta kosong) — di-emit tapi tidak ditangani, alur tambah koordinat sekarang hanya lewat tombol daftar
- **Test PHPUnit untuk `mapPoints`** di `ProjectController::index()` — hanya diverifikasi manual (curl/view-source), belum ada assertion otomatis
- Kegagalan e2e pre-existing `survey.spec.ts:162` ("PDF Jaring" button) — tidak terkait Map Feature, belum diselidiki, lihat "Known Fixes Applied" untuk detail

### ⏳ Not Started
- Export PDF peta (lihat di atas)
- Lanjutan utang teknis Map Feature (lihat `docs/map.md` bagian "Utang Teknis")

---

## Core Engineering Rules

- Raw readings are **immutable** — never overwrite the `readings` table
- Never place engineering formulas inside Controllers
- All calculations must be **deterministic and reproducible**
- Use `NUMERIC` in PostgreSQL — never `FLOAT` or `DOUBLE`
- Recalculate **all** computed elevations on every reading change
- Export only allowed when `project.status = accepted`
- Validate BT deviation: `|BT_field − BT_computed| ≤ 0.002 m`
- All engineering constants live in `config/geolevel.php`
- Koordinat GPS (`survey_points`) **tidak mempengaruhi** kalkulasi elevasi — murni visualisasi, terpisah total dari pipeline sipat datar

---

## Architecture Rules

- Thin Controllers — delegate all logic to Services
- Service Layer handles all business and engineering logic
- Event-Driven Workflow for decoupled side effects
- PostgreSQL is the single source of truth
- Use constructor dependency injection — never `new ServiceName()`
- Use Form Requests for all input validation
- Code in English — UI labels in Indonesian
- Komponen peta (WebGL, berat) **selalu** lazy-loaded via `defineAsyncComponent`, tidak pernah masuk bundle utama

---

## Main Services

| Service                      | Responsibility                                       | Status |
| ----------------------------- | ---------------------------------------------------- | ------ |
| `LevelingCalculationService` | Core elevation calculation pipeline                  | ✅ Done |
| `ClosureCheckerService`      | Closure error + tolerance check                      | ✅ Done |
| `AdjustmentService`          | Equal / Bowditch / Least Squares (normal equations)  | ✅ Done |
| `VisualizationService`       | Chart-ready dataset preparation                      | ✅ Done |
| `ExportService`              | PDF / Excel / CSV orchestration                      | ✅ Done (peta belum masuk field book — lihat utang teknis) |

---

## Documentation

Read the relevant doc before implementing any feature:

| File                        | Read when working on...                       |
| --------------------------- | --------------------------------------------- |
| `docs/formulas.md`          | Any calculation, formula, or validation logic |
| `docs/database.md`          | Migrations, models, queries, schema           |
| `docs/architecture.md`      | Services, observers, jobs, events, queues     |
| `docs/exports.md`           | PDF, Excel, CSV generation                    |
| `docs/engineering-rules.md` | Precision policy, business rules, testing     |
| `docs/map.md`               | Map, coordinates, GPS, spatial visualization  |

---

## Adjustment Methods Reference

| Method          | Formula                              | Notes                                    |
| --------------- | ------------------------------------ | ---------------------------------------- |
| `equal`         | `correction = -fh / n`              | Equal share per FS point                 |
| `bowditch`      | `correction = -fh × (d_i / Σd)`    | Distance-proportional                    |
| `least_squares` | `correction = -fh × (d_i / Σd)`    | Normal equations, same as Bowditch for   |
|                 |                                      | single open traverse; distinct for loop  |
|                 |                                      | networks with redundant obs (future)     |

---

## Known Fixes Applied

### PostgreSQL tidak jalan setelah restart WSL/Windows (OPERATIONAL, bukan kode)
**Gejala:** `SQLSTATE[08006] Connection refused` pada semua route yang butuh DB,
termasuk `/login` — yang lalu membuat SEMUA e2e Playwright timeout di `beforeEach`
(karena `loginAs()` menunggu elemen form yang tidak pernah muncul, halaman malah
menampilkan Laravel error page 500).

**Fix:**
```bash
sudo service postgresql start
pg_isready -h 127.0.0.1 -p 5432   # verifikasi
```
**Pelajaran:** kalau *seluruh* test suite e2e gagal serentak (bukan cuma satu-dua),
curigai infrastruktur (database/webserver mati) dulu sebelum curiga kode — pola
kegagalannya khas: satu error awal ("connection refused") lalu domino ke puluhan
test lain yang sebenarnya tidak ada hubungannya dengan perubahan kode terbaru.

### e2e test jalan di atas database dev (`geolevel`), bukan `geolevel_test` (KLARIFIKASI PENTING)
`playwright.config.ts` set `baseURL: 'https://geolevel.local'` — ini menunjuk ke
environment dev biasa (Nginx + PHP-FPM + `DB_DATABASE=geolevel` dari `.env`),
**bukan** ke database test terisolasi (`.env.testing` → `geolevel_test`) yang
dipakai PHPUnit dengan `RefreshDatabase`.

**Konsekuensi:** state database dev berubah permanen setiap kali seeder dijalankan
atau data diubah manual lewat browser (termasuk oleh developer yang testing manual
via UI). Test e2e yang mengasumsikan state tertentu (misal "TP-2 belum punya
koordinat") bisa gagal bukan karena bug, tapi karena state sudah berubah dari sesi
manual testing sebelumnya.

**Prosedur wajib sebelum menjalankan `npm run test:e2e`:** re-seed data yang
relevan dulu:
```bash
php artisan db:seed --class=CanonicalSurveySeeder   # untuk survey.spec.ts
php artisan db:seed --class=MapDemoSeeder            # untuk map.spec.ts
```
Kedua seeder di atas **idempotent** — aman dijalankan ulang kapan saja, akan
menghapus data proyek lama dengan nama sama sebelum membuat yang baru (lihat
entri "MapDemoSeeder dibuat idempotent" di bawah).

### Tailwind CSS tidak men-scan Vue components (FIXED)
`tailwind.config.js` awalnya hanya scan `.blade.php` — semua class Tailwind
di file `.vue` diabaikan saat `npm run build`, menyebabkan tampilan tanpa styling.

Fix — tambahkan Vue dan JS ke `content` array:
```js
content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',   // ← wajib ada
    './resources/js/**/*.js',    // ← wajib ada
],
```

### Supervisor `numprocs > 1` butuh `process_name` (FIXED)
**Gejala:** `CANT_REREAD: %(process_num) must be present within process_name when numprocs > 1`
**Fix:** Config worker dengan `numprocs=2` harus menyertakan:
```ini
process_name=%(program_name)s_%(process_num)02d
```

### Show.vue — Reading form bugs (FIXED sesi 2026-06-26)

**Bug 1 — Tambah bacaan tidak bisa lebih dari 10 / field kosong lolos:**
- `distance_m: ''` dan `notes: ''` dikirim sebagai string kosong — backend
  Laravel menolak karena rule `numeric` pada string kosong.
- Fix: sanitasi payload sebelum submit, konversi string kosong ke `null`.

**Bug 2 — Tombol hapus tidak berfungsi / data tidak terhapus:**
- `router.delete` tidak punya `only` option — Inertia full reload tapi flash
  error dari backend tidak ter-handle di Vue.
- Fix: tambah `only` + `onError` handler di `deleteReading()`.

**Bug 3 — Kolom jarak kosong (tidak tampil otomatis):**
- `distance_computed` dari PostgreSQL generated column selalu hadir sebagai
  `"0.000"` string — kondisi `!= null` selalu `true`, sehingga tidak jatuh
  ke fallback perhitungan Vue.
- Fix: fungsi `displayDistance(r)` yang parse dan validasi nilai (`> 0`)
  sebelum ditampilkan, dengan fallback `(BA−BB)×100` jika keduanya nol/kosong.

**Bug 4 — sequence_no berantakan setelah hapus:**
- `LevelingCalculationService` mengambil `sequence_no` langsung dari DB
  tanpa renumber — setelah hapus no.6, tersisa gap `1,2,3,4,5,7,8,9`.
- Fix: `ReadingController::destroy` melakukan renumber dalam satu
  `DB::transaction` bersama soft delete, menggunakan `DB::table` (bukan
  Eloquent) agar tidak trigger observer berkali-kali.

**Bug 5 — Error validasi tidak muncul di modal / form tertutup saat input salah:**
- `router.post` dengan `only: [...]` menyebabkan Inertia partial reload —
  `errors` dari Laravel tidak ikut dalam response, `onError` tidak dipanggil.
- Fix: hapus `only` dari `submitReading` agar full response termasuk errors.

**Bug 6 — Rule `BA ≥ BT ≥ BB` memblok input valid:**
- `StoreReadingRequest::withValidator` punya rule urutan yang tidak ada di
  `engineering-rules.md` dan memblok input lapangan yang sah.
- Fix: hapus rule tersebut — satu-satunya validasi engineering yang wajib
  adalah deviasi BT ≤ 0.002 m.

**Bug 7 — Guard `liveBtOk` tidak reliable saat tombol diklik:**
- Validasi bergantung pada `liveBtOk.value` (Vue computed) yang bisa tidak
  sinkron dengan nilai form saat tombol diklik.
- Fix: hitung deviasi BT langsung dari nilai form di `submitReading()` tanpa
  bergantung computed — `const deviasi = Math.abs(bt - (ba+bb)/2)`.

**Bug 8 — Error inline tidak muncul untuk field BA, BT, BB:**
- Hanya `point_name` yang punya `<p v-if="readingErrors?.point_name">`.
- Fix: tambah `:class` border merah dan `<p v-if>` error inline di bawah
  setiap input BA, BT, BB — konsisten dengan pola `point_name`.

**Bug 9 — Form Tambah Jalur: `dari_titik`/`ke_titik` input teks bebas:**
- User harus mengetik manual nama titik yang sudah ada di readings.
- Fix: ganti `<input type="text">` dengan `<select>` + computed
  `uniquePointNames` yang mengambil `point_name` unik dari `props.readings`.

### Pola patch file .vue dengan CRLF line endings
File `.vue` besar (Show.vue, Index.vue) pakai Windows line endings (`\r\n`).
Semua patch harus lewat python3 dengan `raw.replace(b'\r\n', b'\n')` sebelum
string matching, lalu simpan dengan encode ulang `\n` → `\r\n` jika file
aslinya CRLF. Jangan pakai `str_replace` tool langsung.

Catatan tambahan: `SurveyMap.vue`, `LocationPicker.vue`, `MapLegend.vue`
adalah file **LF murni** (bukan CRLF) — selalu verifikasi dulu dengan
`b"\r\n" in raw` sebelum memutuskan strategi patch, jangan asumsikan
semua file `.vue` di proyek ini CRLF.

### ⚠️ Verifikasi patch SELALU wajib, jangan asumsikan command berhasil (PELAJARAN PENTING)
Selama sesi Map Feature Fase 3, terjadi berkali-kali insiden di mana command
`python3 << 'PYEOF' ... PYEOF` yang panjang **tidak benar-benar tereksekusi
penuh** saat di-copy-paste manual oleh user ke terminal (kadang karena teks
penjelasan prosa ikut ter-paste sebelum command, memutus heredoc; kadang
sebab lain yang tidak selalu jelas). Akibatnya: file di disk tetap versi lama,
tapi karena tidak ada error yang mencolok, mudah terlewat dan menyebabkan
debugging berjam-jam ke arah yang salah (sempat dicurigai OPcache, cache
browser, dan salah user login — padahal akar masalahnya sesederhana "patch
tidak pernah jalan").

**Aturan wajib sekarang:** setelah SETIAP command yang mengklaim mematch/patch
sebuah file (baik lewat `python3`, `str_replace`, atau `create_file`), jalankan
`grep`/`cat` verifikasi **sebelum** melangkah ke command berikutnya yang
bergantung pada patch itu (build, test, restart service, dst). Jangan
berasumsi "kalau tidak ada pesan error, berarti berhasil" — untuk heredoc
Python, bahkan kegagalan total menjalankan blok bisa terlihat seperti tidak
terjadi apa-apa di terminal jika shell prompt langsung kembali tanpa error
yang jelas.

### PHP-FPM / OPcache menyajikan kode lama meski file sudah dipatch (klarifikasi)
Sempat dicurigai sebagai penyebab bug `mapPoints` tidak muncul di response
Inertia — **ternyata bukan** akar masalah sebenarnya (lihat entri di atas,
akar masalah aslinya adalah patch yang tidak pernah tereksekusi). Namun
restart PHP-FPM + `php artisan optimize:clear` tetap prosedur yang valid dan
kadang perlu di setup Nginx+PHP-FPM lokal seperti ini (berbeda dari
`php artisan serve` yang auto-reload). Urutan diagnosa yang benar untuk bug
"kode sudah diubah tapi behavior tidak berubah":
1. `grep` file di disk — pastikan isi benar-benar berubah dulu
2. Kalau isi file sudah benar tapi HTTP response masih lama → restart PHP-FPM
3. Kalau masih belum berubah → `php artisan optimize:clear`
4. Verifikasi paling akurat: `curl`/`view-source:` HTML mentah dari Inertia
   page data (`<script data-page="app" type="application/json">`), bukan
   sekadar tampilan visual browser yang bisa kena berbagai lapisan cache

### OpenStreetMap tile CORS gagal di MapLibre GL — ganti ke CARTO + Esri (FIXED)
Tile source awal `tile.openstreetmap.org` menyebabkan sebagian besar tile
gagal fetch (`net::ERR_FAILED`, `AJAXError` di console, muncul CORS policy
error: `No 'Access-Control-Allow-Origin' header is present`). Root cause:
MapLibre GL menggambar tile lewat WebGL (texture), yang **mewajibkan** header
CORS dari server tile — server resmi OSM tidak konsisten mengirim header itu.

Fix — ganti ke dua tile provider yang gratis selamanya, tanpa API key, dan
mengirim CORS header dengan benar:
- **CARTO Basemaps (Voyager)** — mode "Peta" (jalan/vektor-style)
- **Esri World Imagery** — mode "Satelit" (foto udara asli)

Style config dipusatkan di `resources/js/Components/Map/mapStyle.js`
(`STREET_STYLE`, `SATELLITE_STYLE`, `MAP_STYLES`), dipakai bersama oleh
`SurveyMap.vue`, `LocationPicker.vue`, `ProjectsOverviewMap.vue`. Toggle
Peta/Satelit ditambahkan di semua komponen peta lewat `map.setStyle()` —
marker tidak perlu di-render ulang karena berupa elemen DOM terpisah dari style.
Detail lengkap: `docs/map.md` bagian "Stack Peta".

### Bundle size — komponen peta lazy-loaded via defineAsyncComponent (FIXED)
`maplibre-gl` (~1MB unminified) awalnya diimpor statis di `Show.vue` dan
`Index.vue`, menggembungkan `Show-*.js` sampai 1,299 kB dan memicu warning
Vite "chunks larger than 500 kB". Fix: semua komponen peta
(`SurveyMap.vue`, `LocationPicker.vue`, `GpxImportModal.vue`,
`ProjectsOverviewMap.vue`, `MapLegend.vue`) diimpor lewat
`defineAsyncComponent(() => import(...))` alih-alih `import` statis. Hasil:
`Show-*.js` turun ke 260 kB, chunk peta terpisah kecil-kecil (2–12 kB tiap
komponen) plus satu chunk besar `mapStyle-*.js` (~1MB, berisi `maplibre-gl`
itu sendiri) yang hanya di-download browser saat komponen peta pertama kali
mount. Warning ukuran chunk kemudian dihilangkan dengan menaikkan
`chunkSizeWarningLimit: 1200` di `vite.config.js` — bukan menyembunyikan
masalah, tapi menyesuaikan ambang karena ukuran itu memang wajar untuk
library WebGL yang sudah ter-lazy-load dengan benar.

### 4 file test kosong menyebabkan PHPUnit WARN (FIXED)
`tests/Unit/NetworkExportServiceTest.php`, `NetworkExportSheetsTest.php`,
`NetworkExportStatsTest.php`, `tests/Feature/NetworkExportEndpointTest.php`
ternyata berukuran 0 byte (sisa dari sesi cleanup sebelumnya yang tidak
tuntas) — PHPUnit mencoba load sebagai class test karena pola nama file,
gagal, dan menghasilkan `WARN Class ... cannot be found`. Dikonfirmasi bukan
soal isi test yang rusak (tidak ada kode sama sekali di file-nya), aman
dihapus. Setelah dihapus: 213 test tetap hijau (naik dari 202 karena
penambahan `SurveyPointTest` di sesi Fase 1, bukan dari penghapusan ini).

### PHP 8.5 + maatwebsite/excel 4.x — return type fatal (FIXED)
PHP 8.5 menegakkan covariance return type pada interface secara ketat.
`maatwebsite/excel` 4.x menambahkan `collection(): Enumerable` pada interface
`FromCollection`. Ketiga sheet class tidak punya return type → fatal error
"Premature end of PHP process" tanpa pesan yang bisa di-catch.

Fix — tambahkan `use Illuminate\Support\Enumerable` dan deklarasikan return type:
```php
public function collection(): Enumerable { ... }
```
Berlaku untuk: `ElevasiSheet.php`, `KoreksiSheet.php`, `RawReadingsSheet.php`

### ComputedElevationFactory — reading_id FK dan observer suppression (FIXED)
`reading_id` adalah NOT NULL FK. Factory menggunakan closure di `definition()`
(bukan `afterCreating`) dengan `Reading::withoutEvents()` agar `ReadingObserver`
tidak terdaftar berulang dan `RecalculateSurveyJob` tidak di-dispatch saat setup test.

`flushEventListeners()` + `observe()` tidak dipakai — akumulasi registrasi observer
berkali-kali menyebabkan stack overflow di `QUEUE_CONNECTION=sync`.

Untuk test yang butuh kontrol penuh atas data seed (seperti `AdjustmentEndpointTest`),
readings di-insert dengan `Reading::withoutEvents()`, ID-nya ditangkap, lalu
`ComputedElevation::create()` dipanggil langsung dengan `reading_id` eksplisit —
bukan lewat factory — agar nilai `raw_elevation` kanonik dari `docs/formulas.md`
tidak ditimpa oleh `RecalculateSurveyJob`.

**Catatan tambahan:** `MapDemoSeeder` (map feature) mengikuti pola serupa —
insert `ComputedElevation` manual (bukan lewat factory, nilai dummy hardcoded)
karena tujuannya hanya menyediakan data `sequence_no`/`point_name` untuk
polyline dan popup di peta, **bukan** untuk verifikasi angka elevasi.

### ExportButton.vue — axios diganti window.location.href (FIXED)
`ExportButton.vue` menggunakan `axios.get()` yang hanya fetch ke memori JS — tidak
trigger file download di browser. Fix: ganti ke `window.location.href` sehingga
browser langsung membuka URL dan download file.
Berlaku juga untuk `exportNetwork()` di `Show.vue`.

### NetworkExportService — sync bukan queue (FIXED)
`NetworkLegController::exportPdf/exportExcel` awalnya dispatch Job ke queue.
Diganti ke synchronous stream langsung (seperti `ExportController`) agar file
langsung ter-download di browser tanpa perlu queue worker.
`NetworkExportService` + `GenerateNetworkPdfExportJob` + `GenerateNetworkExcelExportJob`
masih ada tapi tidak dipakai oleh controller — dead code aman, kandidat cleanup mendatang.

### .env.testing QUEUE_CONNECTION=sync (FIXED)
Diubah dari `database` ke `sync` agar test suite tidak membutuhkan queue worker.
`Queue::fake()` tetap berfungsi untuk intercept dan assert job yang di-dispatch.

### postcss.config.js hilang dari repo — Tailwind CSS gagal total (FIXED)
Saat mengerjakan Map Feature Fase 2, seluruh styling Tailwind tiba-tiba hilang
(halaman render tanpa CSS sama sekali, font jadi serif default browser, logo
SVG membesar tanpa batas). Root cause: file `postcss.config.js` **hilang dari
repo** (dikonfirmasi lewat `git status` — muncul sebagai `deleted:`, artinya
pernah ter-track lalu terhapus di suatu commit sebelumnya, sebab tidak
diketahui). Tanpa file ini, Vite tidak menjalankan PostCSS dengan plugin
`tailwindcss`, sehingga direktif `@tailwind base/components/utilities` di
`resources/css/app.css` tidak pernah di-expand — hanya lolos sebagai teks
mentah yang kemudian gagal diminifikasi (`[lightningcss minify] Unknown at
rule: @tailwind`).

**Kenapa tidak ketahuan lebih awal:** build pertama setelah menambah dependency
baru tetap menghasilkan CSS ukuran normal, kemungkinan besar karena masih
memakai cache lama di `node_modules/.vite`. Begitu cache itu dihapus total
(`rm -rf node_modules/.vite public/build`) untuk debugging masalah lain,
baru kerusakan yang sebenarnya-sudah-lama-ada ini kelihatan.

Fix — buat ulang `postcss.config.js` di root project:
```js
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
```
Lalu `rm -rf public/build node_modules/.vite && npm run build`.

**Pelajaran untuk debugging serupa di masa depan:** kalau CSS/Tailwind tiba-tiba
rusak total tanpa perubahan kode yang jelas terkait, urutan cek yang efisien:
(1) baca log `npm run build` lengkap termasuk warning, bukan cuma exit code,
(2) pastikan `postcss.config.js` dan `tailwind.config.js` ada dan isinya benar,
(3) baru curigai kode yang baru ditambahkan. Sempat salah duga bahwa
`import 'maplibre-gl/dist/maplibre-gl.css'` di dalam SFC Vue adalah
penyebabnya — ternyata itu jalan buntu, bukan akar masalah. **CSS MapLibre
tetap dimuat via CDN sampai sekarang** (lihat entri terpisah di bawah), bukan
karena masalah ini, tapi sebagai pendekatan yang sengaja terisolasi dari
pipeline Tailwind.

### CSS MapLibre dimuat via CDN, bukan lewat Vite (KEPUTUSAN DESAIN, masih berlaku)
`maplibre-gl/dist/maplibre-gl.css` **tidak** di-import lewat JS (`import '...css'`
di SFC) maupun lewat `@import` di `resources/css/app.css`. Kedua cara itu
sempat dicoba dan menyebabkan masalah bundling CSS (walau akar masalah
sebenarnya adalah `postcss.config.js` yang hilang, lihat entri di atas).
Sebagai pendekatan yang lebih aman dan terisolasi dari pipeline Tailwind,
CSS MapLibre dimuat via `<link>` tag ke CDN jsDelivr langsung di
`resources/views/app.blade.php`:
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@5.24.0/dist/maplibre-gl.css" />
```
Versi di URL CDN harus disinkronkan manual dengan versi `maplibre-gl` di
`package.json` setiap kali di-upgrade.

### `map.setStyle()` + `once('style.load', ...)` race condition — polyline/network legs hilang setelah toggle mode (FIXED)
**Gejala:** setelah toggle Peta → Satelit (atau sebaliknya), garis polyline
(jalur pengukuran) dan/atau garis network legs kadang menghilang total dan
tidak muncul lagi sampai halaman di-reload penuh. Marker (yang berupa
elemen DOM terpisah) tetap muncul normal — hanya layer GeoJSON (`route-line`,
`network-legs-line`) yang hilang.

**Root cause:** kode awalnya memakai `map.once('style.load', callback)` untuk
menunggu style baru selesai dimuat sebelum menambahkan kembali source/layer
GeoJSON. Event `style.load` di MapLibre GL **kadang sudah selesai fire
sebelum listener sempat terpasang** — terutama saat style sudah ter-cache
browser dan proses load-nya sangat cepat (real race condition, bukan bug
logika). Semakin banyak kerja yang perlu dilakukan di callback tersebut
(awalnya cuma `renderRoute()`, lalu bertambah `renderNetworkLegs()`),
semakin besar kemungkinan race ini terjadi karena總 waktu antara
`setStyle()` dipanggil dan listener terpasang bertambah.

**Fix** — ganti `once('style.load', ...)` dengan polling `isStyleLoaded()`
via `requestAnimationFrame`, bukan bergantung pada satu event sekali-tembak:
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
```
Dipanggil dari `setMapStyle()`:
```js
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
Selain itu, `renderRoute()` dan `renderNetworkLegs()` sendiri dibungkus
`try/catch` yang membersihkan source/layer lama secara aman (mengabaikan
error kalau memang belum ada) sebelum menambahkan yang baru, plus fallback
`requestAnimationFrame` retry sekali lagi kalau `addSource`/`addLayer` masih
gagal saat dipanggil (defense in depth, bukan solusi utama — solusi utama
adalah `waitForStyleReady`).

**Pola ini wajib diikuti** untuk fitur peta apa pun ke depan yang menambahkan
GeoJSON source/layer custom (bukan marker DOM) — jangan pakai
`once('style.load', ...)`, selalu pakai `waitForStyleReady()`.

### MapDemoSeeder dibuat idempotent (FIXED, pelajaran seeder)
Awalnya `MapDemoSeeder` selalu `Project::create()` tanpa cek data lama —
setiap kali `php artisan db:seed --class=MapDemoSeeder` dijalankan ulang
(hal yang wajar terjadi berkali-kali selama sesi dev), muncul proyek baru
dengan nama sama ("Peta Demo - Jalur Sipat Datar") menumpuk di database,
membingungkan saat verifikasi manual di browser (developer bisa saja melihat
instance lama, mengira data baru tidak masuk).

Fix — hapus proyek lama dengan nama sama milik user yang sama sebelum
membuat yang baru:
```php
Project::where('user_id', $user->id)
    ->where('name', 'Peta Demo - Jalur Sipat Datar')
    ->get()
    ->each(fn ($old) => $old->delete());
```
**Pola ini dianjurkan untuk semua seeder dev/test baru** ke depan — jangan
biarkan re-run menumpuk data.

---

## Show.vue — Behaviour Contracts (penting untuk debugging)

### submitReading()
- Validasi frontend DULU sebelum kirim ke server (tidak bergantung `liveBtOk.value`)
- Hitung deviasi BT langsung: `Math.abs(bt - (ba+bb)/2) > 0.002` → blok
- Jika ada error frontend → set `readingErrors.value`, `return` — form tetap terbuka
- Sanitasi payload: `distance_m` dan `notes` string kosong → `null`
- Tidak pakai `only` agar `errors` Laravel sampai ke `onError`
- `onSuccess` → tutup modal + reset form
- `onError` → set `readingErrors.value`, modal tetap terbuka

### deleteReading()
- Pakai `only: ['readings', 'elevations', 'project', 'activityLogs']`
- `onError` → alert pesan error (misal hapus diblok karena status accepted)

### displayDistance(r)
- Prioritas: `distance_m` manual (> 0) → `distance_computed` DB (> 0) → hitung Vue `(BA−BB)×100`
- Parse dengan `parseFloat` dan cek `> 0` — jangan cek `!= null` saja

### uniquePointNames (computed)
- Deduplikasi `point_name` dari `props.readings` — dipakai di dropdown form Tambah Jalur
  DAN di computed `pointsWithoutCoords` untuk daftar "Titik tanpa koordinat" di tab Peta

### Peta tab — savePoint() / deletePoint() / openEditPoint()
- `savePoint(payload)` — cek `editingPoint.value.id`: kalau ada → `router.put(route('survey-points.update', ...))`,
  kalau null → `router.post(route('survey-points.store', ...))`. Body selalu sertakan `source: 'manual'`.
- `deletePoint(point)` — `confirm()` dulu, lalu `router.delete(route('survey-points.destroy', ...))`,
  `preserveScroll: true`, `onError` → alert.
- `openEditPoint(point)` — dipanggil dari dua tempat: tombol "Edit" di daftar titik, DAN dari
  event `@edit-point-requested` yang di-emit `SurveyMap.vue` saat tombol "Edit Koordinat" di
  popup marker diklik. Signature harus tetap kompatibel dengan objek `SurveyPoint` penuh
  (bukan cuma `point_name`), karena `SurveyMap.vue` mem-passing seluruh objek `point`.
- `<LocationPicker>` menerima prop tambahan `:reference-points="surveyPoints"` — dipakai untuk
  menampilkan marker referensi abu-abu (titik lain yang sudah punya koordinat) di mini-map picker.

---

## Seeder — Canonical Survey Data

`database/seeders/CanonicalSurveySeeder.php` — dipanggil dari `DatabaseSeeder`.

Membuat user `demo@geolevel.com` / `password` dengan 2 proyek:

| Proyek | Data | Status | Tujuan |
| ------ | ---- | ------ | ------ |
| Survey Kanonikal BM-A ke BM-B | Persis dari `docs/formulas.md` (BM-A→TP-1→TP-2→BM-B) | **REJECTED** (fh = 0.401 m >> toleransi LA = 0.002452 m) | Demo kasus gagal toleransi |
| Survey Demo Diterima | Data custom, fh kecil | **ACCEPTED** + adjusted (equal) | Demo kasus lolos + export |

> Catatan: user `demo@geolevel.com` sekarang juga punya `survey_points` untuk
> proyek "Survey Demo Diterima" (ditambahkan manual selama sesi testing Fase
> 3, bukan lewat seeder) — kalau database di-reset via `migrate:fresh --seed`,
> koordinat ini akan hilang lagi kecuali ditambahkan ke seeder.

Jalankan:
```bash
php artisan db:seed --class=CanonicalSurveySeeder
```

Service signature yang dipakai (jangan pakai nama method yang salah):
- `LevelingCalculationService::recalculate(Project $project): void`
- `AdjustmentService::applyToProject(Project $project, string $method, int $userId): void`
- `ClosureCheckerService::check()` — static helper murni, sudah otomatis dipanggil dalam `recalculate()`

---

## Seeder — Map Demo Data (baru, sesi terakhir)

`database/seeders/MapDemoSeeder.php` — **standalone**, tidak dipanggil dari
`DatabaseSeeder`, jadi harus dijalankan manual saat butuh. **Idempotent**
(lihat "Known Fixes Applied" di atas) — aman dijalankan ulang kapan saja.

Membuat user `mapdemo@geolevel.com` / `password` dengan 1 proyek "Peta Demo -
Jalur Sipat Datar", status `calculated` (sengaja bukan `accepted` — supaya
tidak collide dengan asumsi `survey.spec.ts` yang mengharapkan tepat satu
proyek `accepted` bernama "Survey Demo Diterima"):

| Titik | Koordinat | Catatan |
| ----- | --------- | ------- |
| BM-A | ada | tipe BM |
| TP-1 | ada | tipe TP |
| TP-2 | **tidak ada** | sengaja, untuk uji "titik tanpa koordinat" |
| BM-B | ada | tipe BM |

Plus 3 `network_legs` dummy membentuk loop BM-A → TP-1 → BM-B → BM-A, untuk
menguji overlay jaring di peta.

Jalankan:
```bash
php artisan db:seed --class=MapDemoSeeder
```

**Wajib dijalankan ulang sebelum `npm run test:e2e`** kalau sebelumnya sempat
testing manual lewat browser dengan akun ini (lihat entri "e2e test jalan di
atas database dev" di "Known Fixes Applied").

---

## E2E Tests — Playwright

Setup: `@playwright/test` + Chromium, config di `playwright.config.ts` (baseURL `https://geolevel.local`).

```bash
npm run test:e2e          # jalankan semua test (headless)
npm run test:e2e:ui       # mode UI interaktif
```

File:
- `tests/e2e/survey.spec.ts` (22 test) + `tests/e2e/helpers.ts`
- `tests/e2e/map.spec.ts` (3 test, baru sesi terakhir) — pakai `loginAs()` dari `helpers.ts`
  (bukan `login`, nama fungsi yang benar), login sebagai `mapdemo@geolevel.com`

**Catatan penting untuk selector:**
- Komponen `<Field label="...">` custom — `getByLabel()` tidak akan kerja.
  Gunakan `input[placeholder="..."]` sebagai selector.
- Halaman login pakai Breeze Blade — title tab `"Laravel"`, bukan `"GeoLevel"`.
- `TabBtn.vue` adalah `<button>` polos, **bukan** `role="tab"` — pakai
  `getByRole('button', { name: 'Peta', exact: true })`, bukan `getByRole('tab', ...)`.
- Label "Peta" muncul **dua kali** di halaman yang sama setelah tab Peta dibuka:
  tombol tab itu sendiri (DOM lebih awal) dan tombol toggle "Peta"/"Satelit"
  di dalam `SurveyMap.vue` (DOM lebih lambat, hanya muncul setelah komponen
  di-mount). Gunakan `.first()` untuk klik tab, `.last()` untuk klik toggle
  balik ke mode Peta setelah sempat pindah ke Satelit.
- Konten tab yang tidak aktif tetap ada di DOM (`v-show`, bukan `v-if`) —
  selector generik seperti `getByText('TP-2')` bisa strict-mode-violation
  karena match banyak elemen tersembunyi di tab lain. Scope selector ke
  elemen spesifik (mis. `page.locator('span.font-mono', { hasText: 'TP-2' })`).
- Assertion terhadap canvas MapLibre GL (`.maplibregl-canvas`) butuh timeout
  lebih longgar (15 detik) dan `page.waitForLoadState('networkidle')` sebelum
  assert — WebGL rendering dan tile fetch punya variasi timing yang lebih
  besar dari elemen DOM biasa.
- MapLibre GL merender semuanya ke satu `<canvas>` WebGL — Playwright **tidak
  bisa** meng-assert "garis ada di koordinat X,Y" lewat DOM selector biasa.
  Test polyline/overlay saat ini hanya proxy check (canvas visible + tidak
  ada console error) — bukan bukti definitif garis benar-benar tergambar
  dengan benar secara visual. Verifikasi visual sesungguhnya masih manual.

---

## Loop Network Least Squares (Redundant Observations)

Tabel `network_legs` — terpisah dari `readings`. Project standar tidak perlu
menyentuh ini; hanya diisi jika ada jalur redundant yang membentuk loop.

| Komponen | File |
| --- | --- |
| Migration | `2026_06_21_100000_create_network_legs_table.php` |
| Migration | `2026_06_21_100100_add_network_stats_to_projects_table.php` |
| Model | `app/Models/NetworkLeg.php` |
| Service | `app/Services/LeastSquaresAdjustmentService.php` |
| Controller | `app/Http/Controllers/NetworkLegController.php` |
| Request | `app/Http/Requests/StoreNetworkLegRequest.php` |
| Routes | `network-legs.index/store/destroy/adjust/export.pdf/export.excel` |

**Catatan penting untuk fitur peta:** `NetworkLeg` model **tidak punya kolom
`status` per-leg** — hanya `residual` sebagai indikator kualitas fit numerik.
Warna overlay network legs di peta (lihat `SurveyMap.vue`) karena itu
mengikuti **status proyek** (draft/calculated/accepted/rejected), sama
seperti skema warna yang sudah dipakai `ProjectsOverviewMap.vue` — bukan
warna per-leg individual.

### Form Tambah Jalur di Show.vue
- `dari_titik` dan `ke_titik` adalah dropdown `<select>` dengan opsi dari
  `uniquePointNames` — computed dari `point_name` unik di `props.readings`.
- Tidak perlu ketik manual — otomatis terisi saat ada bacaan di proyek.

### Redundancy detection
`Project::hasRedundantNetworkObservations()`: `n_legs >= n_unique_points`.

### Precision exception
`LeastSquaresAdjustmentService` — satu-satunya pengecualian aturan "never use float".
Operasi matriks internal pakai PHP `float`; input/output tetap string presisi tinggi.

---

## Map Feature (ringkasan — lihat `docs/map.md` untuk detail penuh)

Status: **Fase 1, 2, dan 3 SEMUA SELESAI** (per sesi terakhir). Tile provider
CARTO Basemaps (mode Peta) + Esri World Imagery (mode Satelit), bukan
`tile.openstreetmap.org` (diganti karena CORS gagal di MapLibre GL, lihat
"Known Fixes Applied").

| Komponen | File | Status |
| --- | --- | --- |
| Style config bersama | `resources/js/Components/Map/mapStyle.js` | ✅ |
| Peta utama (tab Peta) | `resources/js/Components/Map/SurveyMap.vue` | ✅ marker + toggle + **polyline + popup + network overlay** |
| Modal koordinat | `resources/js/Components/Map/LocationPicker.vue` | ✅ + **marker referensi** |
| Modal import GPX | `resources/js/Components/Map/GpxImportModal.vue` | ✅ |
| Mini-map daftar proyek | `resources/js/Components/Map/ProjectsOverviewMap.vue` | ✅ |
| Legenda ikon | `resources/js/Components/Map/MapLegend.vue` | ✅ (baru sesi terakhir) |
| Model + migration | `app/Models/SurveyPoint.php` | ✅ |
| Controller CRUD | `app/Http/Controllers/SurveyPointController.php` | ✅ |
| `mapPoints` di index | `ProjectController::index()` | ✅ (tanpa test otomatis — utang teknis) |
| Export PDF peta | — | ❌ **belum dikerjakan, prioritas berikutnya** |

Semua komponen peta lazy-loaded via `defineAsyncComponent`. Detail props
contract, database schema, precision policy, dan daftar lengkap utang teknis
dengan rekomendasi prioritas: **baca `docs/map.md`**.

---

## Next Session

Setup lokal aktif:
- HTTPS via self-signed cert, trusted di Windows Certificate Store
- Nginx: HTTP (80) → HTTPS (443), TLSv1.2/1.3
- `APP_URL=https://geolevel.local`
- Playwright: `baseURL=https://geolevel.local`, `ignoreHTTPSErrors=true`
- `~/deploy-update.sh` — build + cache + migrate + restart worker
- `~/update-hosts.sh` — update IP WSL di hosts Windows
- **Cek PostgreSQL jalan di awal sesi**: `sudo service postgresql status`

Prioritas berikutnya (urutan disarankan):
1. **Export PDF untuk peta** — tampilan visual sudah matang (polyline, popup,
   network overlay, legenda semua selesai dan terverifikasi), saatnya
   diintegrasikan ke field book PDF (`resources/views/exports/field_book.blade.php`).
   MapLibre GL adalah WebGL/canvas — DomPDF tidak bisa merender WebGL langsung,
   jadi kemungkinan besar butuh screenshot canvas ke gambar statis (misal
   `map.getCanvas().toDataURL()`) yang dikirim ke backend sebagai base64,
   atau pendekatan static map image dari tile provider. Perlu didiskusikan
   pendekatan teknisnya di awal sesi sebelum implementasi.
2. Test PHPUnit untuk `mapPoints` di `ProjectController::index()`
3. Bentuk ikon custom per tipe titik (bintang/segitiga/berlian) — kosmetik
4. Handler `request-add-point` (klik peta kosong untuk tambah koordinat langsung)
5. Investigasi kegagalan pre-existing `survey.spec.ts:162` (tombol "PDF Jaring"
   tidak muncul) — tidak terkait Map Feature, belum diselidiki akar masalahnya

Kandidat cleanup (tidak urgent, tidak mengganggu apa pun):
- `NetworkExportService` + `GenerateNetworkPdfExportJob` + `GenerateNetworkExcelExportJob`
  — dead code sejak diganti sync-stream, test-nya tetap hijau
- CI pipeline yang jalankan `test:e2e` otomatis
