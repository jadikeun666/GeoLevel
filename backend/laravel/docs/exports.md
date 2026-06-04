# GeoLevel — Export Formats

> Read this before implementing ExportService, Blade templates, or Excel sheets.

---

## General Rules

- Export is **only allowed** when `project.status = accepted`
- All export jobs must be queued — never run synchronously
- Store output in `storage/app/exports/{project_id}/`
- Exports must be reproducible from stored data alone

---

## PDF Field Book

### Layout

- Paper: A4 portrait
- Language: Indonesian
- Generated via: `resources/views/exports/field_book.blade.php` + DomPDF
- Includes: longitudinal profile chart, closure summary, adjustment summary

### Column Order (exact)

```
No | Titik | Jarak | BA | BT | BB | BS | IS | FS | HI | ΔH | Elevasi Sementara | Koreksi | Elevasi Tetap
```

### DomPDF Config

```php
'default_paper_size'        => 'a4',
'default_paper_orientation' => 'portrait',
'default_font'              => 'sans-serif',
```

---

## Excel Export

4 sheets, in this order:

| Sheet | Name          | Contents                                  |
| ----- | ------------- | ----------------------------------------- |
| 1     | Bacaan Mentah | All raw `readings` rows                   |
| 2     | Elevasi       | All `computed_elevations` rows            |
| 3     | Koreksi       | Adjustment corrections per point          |
| 4     | Ringkasan     | Project summary, closure error, tolerance |

---

## CSV Export

Flat export of `computed_elevations` for AutoCAD Civil 3D / GIS workflows.

Column order:

```
sequence_no, point_name, cumulative_distance, adjusted_elevation, correction
```

No headers required — downstream tools import by column position.

---

## API Response for Export Requests

On success (file ready):
```json
{ "success": true, "message": "Export generated", "data": { "url": "/storage/exports/..." } }
```

On pending (job queued):
```json
{ "success": true, "message": "Export queued. You will be notified when ready." }
```

On error (status not accepted):
```json
{ "success": false, "message": "Export not allowed. Survey status must be accepted." }
```
