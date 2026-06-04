# GeoLevel — Engineering Rules, Precision Policy & Testing Standards

> Read this before writing any calculation logic, validation, or test.

---

## Precision Policy

| Data Type             | PostgreSQL Type  | PHP Handling              |
| --------------------- | ---------------- | ------------------------- |
| Elevations            | NUMERIC(12,4)    | Cast to string for bcmath |
| Corrections / Errors  | NUMERIC(12,6)    | Cast to string for bcmath |
| Distances             | NUMERIC(10,3)    | Cast to string for bcmath |
| Metadata / Offsets    | JSONB            | Cast to array             |

Rules:
- Never use `FLOAT` or `DOUBLE PRECISION` in PostgreSQL
- Never use PHP `float` arithmetic for elevation calculations — use `bcmath`
- Rounding only occurs at the export/presentation layer
- Internal pipeline preserves maximum precision throughout

---

## `config/geolevel.php` — Required

All engineering constants must live here. Never hardcode.

```php
return [
    'bt_deviation_limit' => 0.002,

    'tolerance_classes' => [
        'LAA' => 0.002,
        'LA'  => 0.004,   // default
        'LB'  => 0.008,
        'LC'  => 0.012,
    ],

    'default_tolerance_class'   => 'LA',
    'default_adjustment_method' => 'equal',

    'export_disk' => 'local',
    'export_path' => 'exports',
    'default_paper' => 'a4',
];
```

---

## Business Rules

1. Never overwrite raw readings — `readings` table is immutable
2. Recalculate all computed elevations on every reading change (create / update / delete)
3. Export only allowed when `project.status = accepted`
4. Validate BT deviation ≤ 0.002 m — reject reading if violated
5. Auto-compute optical distance from BA/BB if `distance_m` is NULL
6. Adjustment must be reversible — setting correction to 0 restores raw elevations
7. Default tolerance class = `LA`
8. Default adjustment method = `equal`
9. All calculations must be reproducible from stored raw readings alone
10. Never hard-delete readings — preserve survey history permanently

---

## Testing Standards

- Every engineering formula → dedicated unit test in `tests/Unit/`
- Use the worked example in `docs/formulas.md` as the primary regression seed
- Closure calculation → assert exact `fh` to 6 decimal places
- Adjustment → assert `adjusted_elevation` to 4 decimal places
- BT deviation → test both passing (≤ 0.002) and failing (> 0.002) cases
- Export endpoints → feature test asserting HTTP 200 and file creation
- Use `RefreshDatabase` in all feature tests
- Precision assertions: `assertEqualsWithDelta($expected, $actual, 0.000001)`

---

## Audit & Traceability

- Every recalculation writes a row to `activity_logs` with timestamp
- `activity_logs.metadata` stores before/after snapshots on sensitive operations
- Export files stored permanently in `storage/app/exports/{project_id}/`
- Adjustment history preserved via `correction` column — never wiped

---

## Domain Terminology Reference

| Indonesian          | English                  | Symbol |
| ------------------- | ------------------------ | ------ |
| Beda Tinggi         | Height Difference        | ΔH     |
| Bacaan Atas         | Upper Thread Reading     | BA     |
| Bacaan Tengah       | Middle Thread Reading    | BT     |
| Bacaan Bawah        | Lower Thread Reading     | BB     |
| Bacaan Belakang     | Backsight                | BS     |
| Bacaan Muka         | Foresight                | FS     |
| Bacaan Antara       | Intermediate Sight       | IS     |
| Tinggi Alat         | Height of Instrument     | HI     |
| Titik Tetap         | Benchmark                | BM     |
| Jarak Optis         | Optical Distance         | D      |
| Kesalahan Penutup   | Closure Error            | fh     |
| Sipat Datar         | Differential Leveling    | —      |
| Profil Memanjang    | Longitudinal Section     | —      |
| Profil Melintang    | Cross-Section            | —      |
| Hitung Perataan     | Error Adjustment         | —      |
| Patok               | Survey Stake / Peg       | —      |
| Rambu Ukur          | Leveling Staff / Rod     | —      |

---

## Reference Standards

1. Dasar-Dasar Ilmu Ukur Tanah — Miswar Tumpu et al.
2. Survei Terestris Geospasial — Direktorat SMK
3. Analisis Hasil Pengukuran Tinggi Takhimetri dengan Sipat Datar Teliti
4. SNI 19-6988-2004 — Jaringan Kontrol Vertikal
