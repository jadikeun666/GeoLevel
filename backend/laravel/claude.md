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
| PDF Export   | DomPDF (barryvdh/laravel-dompdf) |
| Excel Export | Maatwebsite Laravel Excel        |
| Auth         | Laravel Breeze (Blade stack)     |
| Queue        | Laravel Queue (database driver)  |
| Web Server   | Nginx 1.28.3 + PHP 8.5-FPM      |
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

### Production commands
```bash
# Status semua service
sudo supervisorctl status

# Restart worker setelah update kode
sudo supervisorctl restart geolevel-worker:*

# Update hosts file jika IP WSL berubah (jalankan setiap buka WSL)
bash ~/update-hosts.sh

# Deploy update (build + cache + migrate + restart worker)
bash ~/deploy-update.sh

# Log worker
tail -f /home/ciko/workspace/geolevel/backend/laravel/storage/logs/worker.log
```

---

## Current Build Status

> Update this every session before starting work.

### ✅ Done
- Laravel project scaffolding
- Laravel Breeze authentication
- PostgreSQL database connection
- Migrations: `projects`, `readings`, `computed_elevations`, `cross_sections`, `activity_logs`
- Migration: soft deletes on `readings`
- Migration: adjusted precision on `computed_elevations`
- Basic route definitions (all API routes registered)
- `LevelingCalculationService` — full calculation pipeline with bcmath
- `ReadingObserver` — dispatches `RecalculateSurveyJob` on saved/updated/deleted
- `RecalculateSurveyJob` — queued, with `failed()` handler
- `ProjectController` — full CRUD + adjust + adjust-reset + calculate
- `ReadingController` — full CRUD + sequence_no renumber after delete
- `AdjustmentService` — equal, bowditch, least_squares (distance-weighted normal equations)
- All Events: `SurveyRecalculated`, `ClosureChecked`, `AdjustmentApplied`, `ExportGenerated`
- All Listeners: `RunClosureCheck`, `LogClosureResult`, `LogAdjustmentApplied`, `LogExportGenerated`
- `ExportService`, `GeneratePdfExportJob`, `GenerateExcelExportJob`, `GenerateCsvExportJob`
- `ExportController`, `ChartController`
- Form Requests: `StoreReadingRequest`, `UpdateReadingRequest`, `StoreProjectRequest`, `UpdateProjectRequest`, `AdjustProjectRequest`
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
    create modal, edit modal, delete confirm modal, least_squares option exposed
  - `Pages/Projects/Show.vue` — tabs (Bacaan/Elevasi/Grafik/Aktivitas/Jaring), reading
    CRUD modal dengan live BT validation preview + inline error per field,
    adjustment modal, recalculate button, chart fetch, network legs CRUD
- `VisualizationService` — `longSection`, `crossSection`, `crossSectionStations`
- `ChartController` — wired to `VisualizationService`, handles `?station=` query param
- `CrossSection` model — `HasFactory` trait added, `$fillable`, `$casts`, `project()` relation
- `CrossSectionFactory` — default state with `station_name`, `station_distance`, `offsets` JSON array
- Full test suite: **OK (204 tests, 528 assertions)** — zero failures
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
- `Show.vue` update: tombol "PDF Jaring" + "Excel Jaring" di tab Jaring — hanya muncul jika `status=accepted` AND `network_std_deviation != null`

### 🔄 In Progress
- (none)

### ⏳ Not Started
- (none identified — core feature-complete per architecture.md scope)

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

---

## Architecture Rules

- Thin Controllers — delegate all logic to Services
- Service Layer handles all business and engineering logic
- Event-Driven Workflow for decoupled side effects
- PostgreSQL is the single source of truth
- Use constructor dependency injection — never `new ServiceName()`
- Use Form Requests for all input validation
- Code in English — UI labels in Indonesian

---

## Main Services

| Service                      | Responsibility                                       | Status |
| ----------------------------- | ---------------------------------------------------- | ------ |
| `LevelingCalculationService` | Core elevation calculation pipeline                  | ✅ Done |
| `ClosureCheckerService`      | Closure error + tolerance check                      | ✅ Done |
| `AdjustmentService`          | Equal / Bowditch / Least Squares (normal equations)  | ✅ Done |
| `VisualizationService`       | Chart-ready dataset preparation                      | ✅ Done |
| `ExportService`              | PDF / Excel / CSV orchestration                      | ✅ Done |

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

### Pola patch file Show.vue
File Show.vue pakai Windows line endings (`\r\n`). Semua patch harus lewat
python3 dengan `raw.replace(b'\r\n', b'\n')` sebelum string matching, lalu
simpan dengan `newline='\n'`. Jangan pakai `str_replace` tool langsung.

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

---

## Seeder — Canonical Survey Data

`database/seeders/CanonicalSurveySeeder.php` — dipanggil dari `DatabaseSeeder`.

Membuat user `demo@geolevel.com` / `password` dengan 2 proyek:

| Proyek | Data | Status | Tujuan |
| ------ | ---- | ------ | ------ |
| Survey Kanonikal BM-A ke BM-B | Persis dari `docs/formulas.md` (BM-A→TP-1→TP-2→BM-B) | **REJECTED** (fh = 0.401 m >> toleransi LA = 0.002452 m) | Demo kasus gagal toleransi |
| Survey Demo Diterima | Data custom, fh kecil | **ACCEPTED** + adjusted (equal) | Demo kasus lolos + export |

Jalankan:
```bash
php artisan db:seed --class=CanonicalSurveySeeder
```

Service signature yang dipakai (jangan pakai nama method yang salah):
- `LevelingCalculationService::recalculate(Project $project): void`
- `AdjustmentService::applyToProject(Project $project, string $method, int $userId): void`
- `ClosureCheckerService::check()` — static helper murni, sudah otomatis dipanggil dalam `recalculate()`

---

## E2E Tests — Playwright

Setup: `@playwright/test` + Chromium, config di `playwright.config.ts` (baseURL `https://geolevel.local`).

```bash
npm run test:e2e          # jalankan semua test (headless)
npm run test:e2e:ui       # mode UI interaktif
```

File: `tests/e2e/survey.spec.ts` (16 test) + `tests/e2e/helpers.ts`.

**Catatan penting untuk selector:**
- Komponen `<Field label="...">` custom — `getByLabel()` tidak akan kerja.
  Gunakan `input[placeholder="..."]` sebagai selector.
- Halaman login pakai Breeze Blade — title tab `"Laravel"`, bukan `"GeoLevel"`.

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

## Next Session

Setup lokal aktif:
- HTTPS via self-signed cert, trusted di Windows Certificate Store
- Nginx: HTTP (80) → HTTPS (443), TLSv1.2/1.3
- `APP_URL=https://geolevel.local`
- Playwright: `baseURL=https://geolevel.local`, `ignoreHTTPSErrors=true`
- `~/deploy-update.sh` — build + cache + migrate + restart worker
- `~/update-hosts.sh` — update IP WSL di hosts Windows

Possible extensions:
- CI pipeline yang jalankan `test:e2e` otomatis
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

### .env.testing QUEUE_CONNECTION=sync (FIXED)
Diubah dari `database` ke `sync` agar test suite tidak membutuhkan queue worker.
`Queue::fake()` tetap berfungsi untuk intercept dan assert job yang di-dispatch.
