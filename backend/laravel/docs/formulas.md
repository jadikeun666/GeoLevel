# GeoLevel — Engineering Formulas

> Read this before implementing any calculation, validation, or service method.
> Do NOT deviate from these formulas.

---

## Core Formulas

```text
BT_computed  = (BA + BB) / 2
BT_deviation = |BT_field − BT_computed|   → must be ≤ 0.002 m

D            = (BA - BB) × 100             → optical distance in meters

ΔH           = BT_belakang − BT_muka       → height difference between two stations

HI           = Elevation_prev_point + BS   → height of instrument after backsight setup

Elevation_FS = HI − FS                    → elevation at foresight point
Elevation_IS = HI − IS                    → elevation at intermediate sight point
```

---

## Closure Error

```text
Open traverse:
  fh = |ΣBS − ΣFS|

Closed loop:
  fh = Σ(all ΔH)
```

---

## Allowed Tolerance (SNI 19-6988-2004)

```text
r = c × √(d_km)

Tolerance Classes:
  LAA → c = 2 mm
  LA  → c = 4 mm   ← default
  LB  → c = 8 mm
  LC  → c = 12 mm
```

---

## Adjustment Methods

```text
Equal Distribution:
  correction_i = −fh / n
  (applied equally to every computed elevation)

Bowditch (distance-proportional):
  correction_i = −fh × (d_i / Σd)
  (correction weighted by cumulative distance at each point)

Least Squares:
  (reserved — future implementation)
```

---

## BT Validation

```text
|BT_field − BT_computed| ≤ 0.002 m

If exceeded → reject the reading and return a validation error.
Do NOT save the reading.
```

---

## Worked Example (Use as Regression Seed)

This is the canonical test dataset. Use it for unit tests and seeders.

### Input

```
Starting BM-A elevation = 100.0000 m

Seq | Point | Type | BA     | BT     | BB
----|-------|------|--------|--------|-------
 1  | BM-A  | BS   | 1.5230 | 1.2100 | 0.8970
 2  | TP-1  | FS   | 1.4870 | 1.1750 | 0.8630
 3  | TP-1  | BS   | 1.6210 | 1.3050 | 0.9890
 4  | TP-2  | FS   | 1.3940 | 1.0820 | 0.7700
 5  | TP-2  | BS   | 1.5560 | 1.2430 | 0.9300
 6  | BM-B  | FS   | 1.4120 | 1.1000 | 0.7880
```

### Step-by-Step

```
--- Leg 1: BM-A → TP-1 ---
HI(BM-A)     = 100.0000 + 1.2100 = 101.2100
Elev(TP-1)   = 101.2100 − 1.1750 = 100.0350

--- Leg 2: TP-1 → TP-2 ---
HI(TP-1)     = 100.0350 + 1.3050 = 101.3400
Elev(TP-2)   = 101.3400 − 1.0820 = 100.2580

--- Leg 3: TP-2 → BM-B ---
HI(TP-2)     = 100.2580 + 1.2430 = 101.5010
Elev(BM-B)   = 101.5010 − 1.1000 = 100.4010
```

### Closure Check

```
ΣBS = 1.2100 + 1.3050 + 1.2430 = 3.7580
ΣFS = 1.1750 + 1.0820 + 1.1000 = 3.3570
fh  = |3.7580 − 3.3570| = 0.4010 m

Total distance = 375.6 m = 0.3756 km
Tolerance (LA) = 4mm × √0.3756 = 0.002452 m
Status: REJECTED (fh exceeds tolerance — intentional for demo)
```

### Equal Distribution Adjustment

```
correction_each  = −0.4010 / 3 = −0.133667
Elev(TP-1) adj   = 100.0350 − 0.133667 = 99.901333
Elev(TP-2) adj   = 100.2580 − 0.267333 = 99.990667
Elev(BM-B) adj   = 100.4010 − 0.401000 = 100.000000
```

---

## Constants (from `config/geolevel.php`)

```php
'bt_deviation_limit' => 0.002,
'tolerance_classes'  => ['LAA' => 0.002, 'LA' => 0.004, 'LB' => 0.008, 'LC' => 0.012],
'default_tolerance_class'   => 'LA',
'default_adjustment_method' => 'equal',
```

Never hardcode these values. Always read from config.
