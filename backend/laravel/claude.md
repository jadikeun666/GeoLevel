# GeoLevel — Waterpass Survey Processing & Visualization

Engineering surveying and leveling web application for civil engineers and surveyors.

Replaces Excel-based workflows with an automated pipeline:
raw field readings → corrected elevations → charts → PDF field book.

---

## Stack

| Layer        | Technology                        |
| ------------ | --------------------------------- |
| Backend      | Laravel 11 (PHP 8.3)             |
| Database     | PostgreSQL 16                    |
| Frontend     | Inertia.js + Vue 3 + Vite        |
| UI Library   | Tailwind CSS v3                  |
| Charts       | Chart.js (via vue-chartjs)       |
| PDF Export   | DomPDF (barryvdh/laravel-dompdf) |
| Excel Export | Maatwebsite Laravel Excel        |
| Auth         | Laravel Breeze                   |
| Queue        | Laravel Queue (database driver)  |

> Note: dev environment runs PHP 8.5.4 (not 8.3 as documented). This works
> for the current stack since `maatwebsite/excel` is already installed and
> wired — only a *future* `composer update` of that package would hit the
> known `phpoffice/phpspreadsheet` PHP 8.5 incompatibility. No action needed
> unless that package needs upgrading.

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
- `ReadingController` — full CRUD
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
  - `Pages/Projects/Show.vue` — tabs (Bacaan/Elevasi/Grafik/Aktivitas), reading
    CRUD modal with live BT validation preview, adjustment modal, recalculate button,
    chart fetch via `chart.longsection` / `chart.crosssection` routes
- `VisualizationService` — `longSection`, `crossSection`, `crossSectionStations`
- `ChartController` — wired to `VisualizationService`, handles `?station=` query param
- `CrossSection` model — `HasFactory` trait added, `$fillable`, `$casts`, `project()` relation
- `CrossSectionFactory` — default state with `station_name`, `station_distance`, `offsets` JSON array
- Full test suite: **OK (171 tests, 412 assertions)** — zero failures

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

## Next Session

Project is feature-complete. Possible extensions:
- Loop network least squares (redundant observations, full normal equation matrix)
- Seeder with canonical worked example from `docs/formulas.md`
- Production deployment config (Supervisor, queue worker, storage symlink)
- E2E tests (Playwright/Cypress) for frontend flows
