# GeoLevel — Database Schema

> Read this before writing migrations, models, or queries.

---

## Precision Policy

- Elevation values → `NUMERIC(12,4)` (4 decimal places)
- Error/correction values → `NUMERIC(12,6)` (6 decimal places)
- Never use `FLOAT` or `DOUBLE PRECISION`
- Rounding only at export/presentation layer
- Use `JSONB` for metadata and structured nested data

---

## Table: `projects`

```sql
id                  BIGINT PRIMARY KEY
user_id             BIGINT FK → users
name                VARCHAR(255)
location            VARCHAR(255)
description         TEXT NULL
survey_date         DATE
benchmark_name      VARCHAR(100)
benchmark_elevation NUMERIC(12,4)
tolerance_class     VARCHAR(10)     -- LAA | LA | LB | LC
adjustment_method   VARCHAR(20)     -- equal | bowditch | least_squares
closure_error       NUMERIC(10,6) NULL
total_distance_km   NUMERIC(10,4) NULL
allowed_tolerance   NUMERIC(10,6) NULL
status              VARCHAR(20)     -- draft | calculated | accepted | rejected
metadata            JSONB
created_at, updated_at
```

---

## Table: `readings`

```sql
id              BIGINT PRIMARY KEY
project_id      BIGINT FK → projects
sequence_no     INT              -- ORDER IS CRITICAL — never skip or reuse
point_name      VARCHAR(50)
reading_type    VARCHAR(10)      -- BS | IS | FS
ba              NUMERIC(10,4)   -- Bacaan Atas
bt              NUMERIC(10,4)   -- Bacaan Tengah (field entry)
bb              NUMERIC(10,4)   -- Bacaan Bawah
distance_m      NUMERIC(10,3) NULL  -- manual override; if NULL use distance_computed
notes           VARCHAR(255) NULL
created_at, updated_at
```

### PostgreSQL Generated Columns

```sql
bt_check NUMERIC(10,4)
  GENERATED ALWAYS AS ((ba + bb) / 2.0) STORED
  -- Validate: |bt - bt_check| <= 0.002 m

distance_computed NUMERIC(10,3)
  GENERATED ALWAYS AS ((ba - bb) * 100.0) STORED
  -- Used when distance_m IS NULL
```

### Index

```sql
CREATE INDEX idx_readings_project_sequence ON readings (project_id, sequence_no ASC);
```

---

## Table: `computed_elevations`

```sql
id                  BIGINT PRIMARY KEY
project_id          BIGINT FK → projects
reading_id          BIGINT FK → readings
sequence_no         INT
point_name          VARCHAR(50)
hi                  NUMERIC(12,4) NULL   -- set only on BS rows
raw_elevation       NUMERIC(12,4)        -- before adjustment
correction          NUMERIC(12,6)        -- applied correction (0 if unadjusted)
adjusted_elevation  NUMERIC(12,4)        -- final elevation
cumulative_distance NUMERIC(12,3)
created_at, updated_at
```

---

## Table: `cross_sections`

```sql
id               BIGINT PRIMARY KEY
project_id       BIGINT FK → projects
station_name     VARCHAR(50)
station_distance NUMERIC(10,3)
offsets          JSONB
-- offsets format:
-- [{"side": "L", "distance": 3.0, "elevation": 101.234}, ...]
-- side values: "L" | "R" | "C"
created_at, updated_at
```

---

## Table: `activity_logs`

```sql
id            BIGINT PRIMARY KEY
project_id    BIGINT FK → projects
user_id       BIGINT FK → users
activity_type VARCHAR(100)
-- e.g. reading_saved | survey_recalculated | adjustment_applied | export_generated | job_failed
description   TEXT
metadata      JSONB
created_at
```

---

## Eloquent Model Rules

- All models must define `$fillable` explicitly — no mass assignment vulnerabilities
- Cast JSONB fields: `'metadata' => 'array'`, `'offsets' => 'array'`
- `Reading` model must have: `$casts = ['ba' => 'decimal:4', 'bt' => 'decimal:4', 'bb' => 'decimal:4']`
- Always eager-load `readings` and `computedElevations` to avoid N+1

---

## PostgreSQL Conventions

- Use window functions (`SUM() OVER`, `LAG() OVER`) for cumulative calculations
- Prefer DB-level aggregation over PHP loops for sums and running totals
- Prefer migrations over raw SQL
- Future-compatible with PostGIS — avoid reserved spatial column names
