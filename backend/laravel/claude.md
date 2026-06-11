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

### 🔄 In Progress
- `ClosureCheckerService` — wired via event but needs end-to-end verification
- `ExportController` — status-guard returning 422 instead of 403 (bug)
- `GeneratePdfExportJob` — `Barryvdh\DomPDF\Facade\Pdf` not found (package missing or not registered)

### ⏳ Not Started
- `VisualizationService`
- Vue frontend pages and components
- PDF / Excel / CSV export (PDF blocked by DomPDF issue)
- Chart visualization
- `ElevationTable.vue` component

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

1. Fix `ExportController` status guard — returning 422 instead of 403 for non-accepted projects
2. Fix DomPDF — install/register `barryvdh/laravel-dompdf` so `GeneratePdfExportJob` works
3. Fix `ProjectWorkflowTest` — Inertia view `[app]` not found; stub or bypass Inertia in tests
4. Verify `ClosureCheckerService` end-to-end via `SurveyRecalculated` event
5. Implement `VisualizationService` — long section + cross section chart data
6. Build Vue frontend: `ElevationTable.vue`, `LongSectionChart.vue`, `ClosureStatusBadge.vue`