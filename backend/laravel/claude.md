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
- Basic route definitions

###  ✅ In Progress
- `LevelingCalculationService`
- `ReadingObserver`
- `ProjectController` CRUD
- `ReadingController` CRUD

### ✅ Not Started
- `ClosureCheckerService`
- `AdjustmentService`
- `VisualizationService`
- `ExportService`
- All Jobs, Events
- Vue frontend pages and components
- PDF / Excel / CSV export
- Chart visualization

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

| File                    | Read when working on...                        |
| ----------------------- | ---------------------------------------------- |
| `docs/formulas.md`      | Any calculation, formula, or validation logic  |
| `docs/database.md`      | Migrations, models, queries, schema            |
| `docs/architecture.md`  | Services, observers, jobs, events, queues      |
| `docs/exports.md`       | PDF, Excel, CSV generation                     |
| `docs/engineering-rules.md` | Precision policy, business rules, testing  |

---

## Immediate Next Tasks

1. Complete `LevelingCalculationService` — implement core formulas from `docs/formulas.md`
2. Wire `ReadingObserver` to trigger recalculation on save/update/delete
3. Complete `ProjectController` and `ReadingController` CRUD
4. Implement `ClosureCheckerService`
5. Build `ElevationTable.vue` component
