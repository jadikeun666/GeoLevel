<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: sans-serif; font-size: 9px; color: #111; }

  .page-header { text-align: center; margin-bottom: 10px; }
  .page-header h1 { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
  .page-header h2 { font-size: 11px; margin-top: 2px; }

  .meta-grid { display: flex; gap: 30px; margin-bottom: 10px; }
  .meta-col { flex: 1; }
  .meta-row { display: flex; gap: 6px; margin-bottom: 2px; }
  .meta-label { width: 130px; font-weight: bold; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  th, td { border: 1px solid #999; padding: 2px 4px; text-align: center; }
  th { background: #e0e0e0; font-weight: bold; font-size: 8px; }
  tr:nth-child(even) { background: #f9f9f9; }
  td.left { text-align: left; }

  .section-title { font-size: 10px; font-weight: bold; margin: 8px 0 4px; border-bottom: 1px solid #333; padding-bottom: 2px; }

  .summary-grid { display: flex; gap: 30px; }
  .summary-box { flex: 1; border: 1px solid #aaa; padding: 6px; }
  .summary-box h4 { font-size: 9px; text-transform: uppercase; margin-bottom: 4px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
  .summary-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
  .sum-label { color: #555; }
  .sum-val { font-weight: bold; }

  .status-badge { display: inline-block; padding: 1px 8px; border-radius: 3px; font-weight: bold; font-size: 9px; }
  .status-accepted  { background: #d1fae5; color: #065f46; }
  .status-rejected  { background: #fee2e2; color: #991b1b; }
  .status-calculated { background: #dbeafe; color: #1e40af; }
  .status-draft     { background: #f3f4f6; color: #374151; }

  .footer { text-align: right; font-size: 8px; color: #888; margin-top: 10px; border-top: 1px solid #ddd; padding-top: 4px; }
</style>
</head>
<body>

{{-- ── Header ─────────────────────────────────────────────────── --}}
<div class="page-header">
  <h1>Buku Lapangan Sipat Datar</h1>
  <h2>{{ $project->name }}</h2>
</div>

{{-- ── Project Metadata ────────────────────────────────────────── --}}
<div class="meta-grid">
  <div class="meta-col">
    <div class="meta-row"><span class="meta-label">Lokasi</span><span>{{ $project->location }}</span></div>
    <div class="meta-row"><span class="meta-label">Tanggal Survei</span><span>{{ $project->survey_date?->format('d/m/Y') ?? '-' }}</span></div>
    <div class="meta-row"><span class="meta-label">Benchmark</span><span>{{ $project->benchmark_name }}</span></div>
    <div class="meta-row"><span class="meta-label">Elevasi Benchmark</span><span>{{ number_format($project->benchmark_elevation, 4) }} m</span></div>
  </div>
  <div class="meta-col">
    <div class="meta-row"><span class="meta-label">Kelas Toleransi</span><span>{{ $project->tolerance_class }}</span></div>
    <div class="meta-row"><span class="meta-label">Metode Perataan</span><span>{{ ucfirst($project->adjustment_method) }}</span></div>
    <div class="meta-row"><span class="meta-label">Status</span>
      <span class="status-badge status-{{ $project->status }}">{{ strtoupper($project->status) }}</span>
    </div>
    <div class="meta-row"><span class="meta-label">Dicetak</span><span>{{ now()->format('d/m/Y H:i') }}</span></div>
  </div>
</div>

{{-- ── Closure Summary ─────────────────────────────────────────── --}}
<div class="section-title">Ringkasan Penutup</div>
<div class="summary-grid">
  <div class="summary-box">
    <h4>Kesalahan Penutup</h4>
    <div class="summary-row">
      <span class="sum-label">fh (Kesalahan Penutup)</span>
      <span class="sum-val">{{ $project->closure_error !== null ? number_format($project->closure_error, 6) . ' m' : '-' }}</span>
    </div>
    <div class="summary-row">
      <span class="sum-label">Toleransi yang Diizinkan</span>
      <span class="sum-val">{{ $project->allowed_tolerance !== null ? number_format($project->allowed_tolerance, 6) . ' m' : '-' }}</span>
    </div>
    <div class="summary-row">
      <span class="sum-label">Jarak Total</span>
      <span class="sum-val">{{ $project->total_distance_km !== null ? number_format($project->total_distance_km, 4) . ' km' : '-' }}</span>
    </div>
  </div>
  <div class="summary-box">
    <h4>Perataan</h4>
    <div class="summary-row">
      <span class="sum-label">Metode</span>
      <span class="sum-val">{{ ucfirst($project->adjustment_method ?? '-') }}</span>
    </div>
    <div class="summary-row">
      <span class="sum-label">Jumlah Titik FS</span>
      <span class="sum-val">{{ $rows->where('correction', '!=', 0)->count() ?: $rows->count() }}</span>
    </div>
    @if($project->description)
    <div class="summary-row">
      <span class="sum-label">Keterangan</span>
      <span class="sum-val">{{ $project->description }}</span>
    </div>
    @endif
  </div>
</div>

{{-- ── Data Table ───────────────────────────────────────────────── --}}
<div class="section-title">Tabel Data Sipat Datar</div>
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Titik</th>
      <th>Jarak (m)</th>
      <th>BA</th>
      <th>BT</th>
      <th>BB</th>
      <th>BS</th>
      <th>IS</th>
      <th>FS</th>
      <th>HI</th>
      <th>ΔH</th>
      <th>Elevasi Sementara</th>
      <th>Koreksi</th>
      <th>Elevasi Tetap</th>
    </tr>
  </thead>
  <tbody>
    @php
      $prevElev = null;
    @endphp
    @foreach ($rows as $row)
      @php
        // Retrieve matched reading for BA/BT/BB and type
        $reading = $row->reading ?? null;
        $type    = $reading?->reading_type ?? '';
        $bs = $type === 'BS' ? number_format($reading->bt, 4) : '';
        $is = $type === 'IS' ? number_format($reading->bt, 4) : '';
        $fs = $type === 'FS' ? number_format($reading->bt, 4) : '';
        $dh = $prevElev !== null ? number_format($row->raw_elevation - $prevElev, 4) : '';
        $prevElev = (float) $row->raw_elevation;
      @endphp
      <tr>
        <td>{{ $row->sequence_no }}</td>
        <td class="left">{{ $row->point_name }}</td>
        <td>{{ number_format($row->cumulative_distance, 3) }}</td>
        <td>{{ $reading ? number_format($reading->ba, 4) : '' }}</td>
        <td>{{ $reading ? number_format($reading->bt, 4) : '' }}</td>
        <td>{{ $reading ? number_format($reading->bb, 4) : '' }}</td>
        <td>{{ $bs }}</td>
        <td>{{ $is }}</td>
        <td>{{ $fs }}</td>
        <td>{{ $row->hi !== null ? number_format($row->hi, 4) : '' }}</td>
        <td>{{ $dh }}</td>
        <td>{{ number_format($row->raw_elevation, 4) }}</td>
        <td>{{ number_format($row->correction, 6) }}</td>
        <td><strong>{{ number_format($row->adjusted_elevation, 4) }}</strong></td>
      </tr>
    @endforeach
  </tbody>
</table>

{{-- ── Footer ──────────────────────────────────────────────────── --}}
<div class="footer">
  GeoLevel — Dihasilkan otomatis &bull; {{ now()->format('d/m/Y H:i:s') }}
</div>

</body>
</html>