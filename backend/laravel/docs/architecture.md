# GeoLevel — Architecture & Workflow

> Read this before implementing Services, Observers, Jobs, or Events.

---

## Layer Responsibilities

```
Request → Controller → Service → Model / DB
                ↓
           Form Request (validation)
                ↓
           Event → Listener → Job (queued)
```

- **Controller** — receive request, call Service, return Inertia response. No logic.
- **Form Request** — validate all input. Never validate inside Controllers.
- **Service** — all business and engineering logic lives here.
- **Observer** — reacts to Model lifecycle events (saved, updated, deleted).
- **Job** — heavy work dispatched to queue (recalculation, export).
- **Event** — signals that a domain action completed.

---

## Services

### `LevelingCalculationService`

Triggered by: `ReadingObserver` on `saved` / `updated` / `deleted`

Responsibilities:
- Delete all existing `computed_elevations` for the project
- Reload all `readings` ordered by `sequence_no ASC`
- Execute calculation pipeline (see `docs/formulas.md`)
- Write new rows to `computed_elevations`
- Set `correction = 0`, `adjusted_elevation = raw_elevation` initially
- Dispatch `SurveyRecalculated` event when done

Rules:
- Must be **idempotent** — safe to run multiple times with same result
- Never mutates any `readings` row
- Must process readings **strictly in sequence_no order**

---

### `ClosureCheckerService`

Triggered by: `SurveyRecalculated` event listener

Responsibilities:
- Compute `fh` (closure error)
- Compute `allowed_tolerance` using SNI formula
- Update `projects.closure_error`, `projects.allowed_tolerance`, `projects.status`
- Status values: `calculated` | `accepted` | `rejected`
- Dispatch `ClosureChecked` event

---

### `AdjustmentService`

Triggered by: `POST /projects/{id}/adjust`

Responsibilities:
- Supported methods: `equal` | `bowditch` | `least_squares`
- Reads `raw_elevation` from `computed_elevations`
- Writes `correction` and `adjusted_elevation` to `computed_elevations`
- Never touches `readings`
- Must be reversible: setting `correction = 0` restores raw state
- Dispatch `AdjustmentApplied` event

---

### `VisualizationService`

Triggered by: `GET /projects/{id}/chart/*`

Responsibilities:
- Return lightweight chart-ready arrays
- Long section: `[{ x: cumulative_distance, y: adjusted_elevation, label: point_name }]`
- Cross section: `{ station, offsets: [{ side, distance, elevation }] }`
- All aggregation at DB layer — no heavy PHP loops

---

### `ExportService`

Triggered by: `GET /projects/{id}/export/*`

Responsibilities:
- Check `project.status === accepted` before proceeding
- Dispatch `GeneratePdfExportJob` or `GenerateExcelExportJob`
- Store output in `storage/app/exports/{project_id}/`
- Dispatch `ExportGenerated` event with file path

---

## Observer

### `ReadingObserver`

Hooks: `saved`, `updated`, `deleted`

On any hook:
1. Dispatch `RecalculateSurveyJob` to queue

Do NOT run recalculation synchronously inside the Observer.

---

## Jobs

| Job                      | Dispatched By          | Max Tries | Timeout |
| ------------------------ | ---------------------- | --------- | ------- |
| `RecalculateSurveyJob`   | `ReadingObserver`      | 3         | 60s     |
| `GeneratePdfExportJob`   | `ExportService`        | 2         | 120s    |
| `GenerateExcelExportJob` | `ExportService`        | 2         | 120s    |

### Failure Behavior

All jobs must implement `failed(Throwable $e)`:

- `RecalculateSurveyJob` failure → set `project.status = draft`, log to `activity_logs`
- Export job failure → delete partial file, log to `activity_logs`, flash error to user
- Dead jobs stay in `failed_jobs` table for manual inspection

---

## Events

| Event                | Fired By                     | Action                                      |
| -------------------- | ---------------------------- | ------------------------------------------- |
| `ReadingSaved`       | `ReadingObserver`            | Dispatch `RecalculateSurveyJob`             |
| `SurveyRecalculated` | `LevelingCalculationService` | Trigger `ClosureCheckerService`, log        |
| `ClosureChecked`     | `ClosureCheckerService`      | Update project status, notify user          |
| `AdjustmentApplied`  | `AdjustmentService`          | Log activity, update status                 |
| `ExportGenerated`    | `ExportService`              | Notify user, log activity                   |

All events must carry: `project_id`, `user_id`

---

## Vue Component Contracts

Props and emits are fixed. Do NOT invent different prop names.

### `ElevationTable.vue`

```ts
Props:
  rows: Array<{
    sequence_no:        number
    point_name:         string
    distance_m:         number | null
    ba:                 number
    bt:                 number
    bb:                 number
    reading_type:       'BS' | 'IS' | 'FS'
    hi:                 number | null
    delta_h:            number | null
    raw_elevation:      number
    correction:         number
    adjusted_elevation: number
  }>
  loading: boolean
Emits: (none)
```

### `LongSectionChart.vue`

```ts
Props:
  dataset: Array<{ x: number, y: number, label: string }>
  title:   string
Emits: (none)
```

### `CrossSectionChart.vue`

```ts
Props:
  station: string
  offsets: Array<{ side: 'L' | 'R' | 'C', distance: number, elevation: number }>
Emits: (none)
```

### `ClosureStatusBadge.vue`

```ts
Props:
  status:            'draft' | 'calculated' | 'accepted' | 'rejected'
  closure_error:     number | null
  allowed_tolerance: number | null
Emits: (none)
```

---

## API Routes

```text
GET    /projects
POST   /projects
GET    /projects/{id}
PUT    /projects/{id}
DELETE /projects/{id}

POST   /projects/{id}/readings
PUT    /projects/{id}/readings/{rid}
DELETE /projects/{id}/readings/{rid}

POST   /projects/{id}/calculate
POST   /projects/{id}/adjust

GET    /projects/{id}/chart/longsection
GET    /projects/{id}/chart/crosssection

GET    /projects/{id}/export/pdf
GET    /projects/{id}/export/excel
GET    /projects/{id}/export/csv
```

---

## Queue Setup

```env
QUEUE_CONNECTION=database
```

```bash
php artisan queue:work --tries=3 --timeout=60
```

For production, use Supervisor to keep the worker alive.
