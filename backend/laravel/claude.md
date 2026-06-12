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
- `AdjustmentService` — equal, bowditch, reversible
- All Events: `SurveyRecalculated`, `ClosureChecked`, `AdjustmentApplied`, `ExportGenerated`
- All Listeners: `RunClosureCheck`, `LogClosureResult`, `LogAdjustmentApplied`, `LogExportGenerated`
- `ExportService`, `GeneratePdfExportJob`, `GenerateExcelExportJob`, `GenerateCsvExportJob`
- `ExportController`, `ChartController`
- Form Requests: `StoreReadingRequest`, `UpdateReadingRequest`, `StoreProjectRequest`, `UpdateProjectRequest`, `AdjustProjectRequest`
- Policies: `ProjectPolicy`
- `ExportController` — status-guard now returns 403 via `ExportNotAllowedException`
- `barryvdh/laravel-dompdf` installed and wired into `GeneratePdfExportJob`
- `GeneratePdfExportJob` — renders via DomPDF (`->output()`), writes through
  `Storage::disk('local')` (Storage::fake()-safe for tests)
- `ExportGenerated` event — carries `projectId`, `userId`, `format`, `filePath`
  (fixes `LogExportGenerated` undefined-property error)
- `resources/views/app.blade.php` — Inertia root template created
- `config/inertia.php` published, `pages.paths` corrected to `resources/js/Pages`
  (was defaulting to lowercase `js/pages`, breaking `assertInertia()->component()`)
- `ProjectWorkflowTest` — all 20 tests pass (Inertia render + auth + CRUD + calculate/adjust/export/chart)
- `ExportControllerTest` — all tests pass, including
  `export_file_stored_under_project_exports_directory`
- `ClosureCheckerService` — covered by full suite, all assertions pass (91/91)
- `app/Exceptions/ExportNotAllowedException.php`
- Excel export sheets split into PSR-4-compliant files:
  `ElevasiSheet.php`, `KoreksiSheet.php`, `RingkasanSheet.php`
- `resources/views/exports/field_book.blade.php` exists
- Vue page stubs exist: `Pages/Projects/Index.vue`, `Pages/Projects/Show.vue`,
  `Pages/Projects/ElevationTable.vue`, `Pages/Projects/ClosureStatusBadge.vue`,
  `Pages/Projects/LongSectionChart.vue`, `Pages/Projects/CrossSectionChart.vue`

**Full test suite: `OK (91 tests, 230 assertions)`**

### 🔄 In Progress
- `GenerateExcelExportJob` — still uses `ExportService::exportDir()` with raw
  `mkdir()`/file write (same pattern that broke `GeneratePdfExportJob` under
  `Storage::fake()`). Not currently failing because no test like
  `export_file_stored_under_project_exports_directory` exists for Excel —
  but should be refactored to write via `Storage::disk('local')->put()` for
  consistency before such a test is added.
- Vue page files exist (`Index.vue`, `Show.vue`, `ElevationTable.vue`,
  `ClosureStatusBadge.vue`, `LongSectionChart.vue`, `CrossSectionChart.vue`)
  but content/completeness against `architecture.md` prop/emit contracts is
  **not yet verified**.
- Component placement: `ElevationTable.vue`, `ClosureStatusBadge.vue`,
  `LongSectionChart.vue`, `CrossSectionChart.vue` currently sit inside
  `resources/js/Pages/Projects/` — per `architecture.md` these are reusable
  components (not Inertia pages) and should eventually move to
  `resources/js/Components/`. Non-blocking; `inertia.pages.paths` only
  resolves what's explicitly `Inertia::render()`'d, so this doesn't break
  tests, but worth cleaning up before the component tree grows.

### ⏳ Not Started
- `VisualizationService`
- Chart visualization wiring (frontend ↔ `ChartController` data)

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

| Service                      | Responsibility                          |
| ---------------------------- | --------------------------------------- |
| `LevelingCalculationService` | Core elevation calculation pipeline     |
| `ClosureCheckerService`      | Closure error + tolerance check         |
| `AdjustmentService`          | Equal / Bowditch / Least Squares        |
| `VisualizationService`       | Chart-ready dataset preparation         |
| `ExportService`              | PDF / Excel / CSV orchestration         |

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

## Immediate Next Tasks

1. Refactor `GenerateExcelExportJob` to write via `Storage::disk('local')->put()`
   instead of `ExportService::exportDir()` + raw `mkdir()`, matching the
   `GeneratePdfExportJob` fix — add a parity test
   (`export_excel_file_stored_under_project_exports_directory`) to lock it in.
2. Verify Vue page/component files (`Index.vue`, `Show.vue`,
   `ElevationTable.vue`, `LongSectionChart.vue`, `CrossSectionChart.vue`,
   `ClosureStatusBadge.vue`) against the prop/emit contracts in
   `architecture.md`.
3. Move `ElevationTable.vue`, `ClosureStatusBadge.vue`, `LongSectionChart.vue`,
   `CrossSectionChart.vue` from `resources/js/Pages/Projects/` to
   `resources/js/Components/` (cleanup, non-blocking).
4. Implement `VisualizationService` — long section + cross section chart data
   (DB-level aggregation, per `architecture.md`).
5. Wire `ChartController` endpoints to `VisualizationService` and confirm
   `long_section_chart_returns_json_array` / `cross_section_chart_returns_json`
   assertions reflect real computed data, not placeholder shape.