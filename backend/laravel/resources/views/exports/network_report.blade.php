<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Jaring Sipat Datar – {{ $project->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.4;
        }

        /* ── Header ───────────────────────────────────────── */
        .page-header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .page-header h1 {
            font-size: 13pt;
            font-weight: 700;
            color: #1d4ed8;
        }
        .page-header .subtitle {
            font-size: 8.5pt;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ── Meta info grid ───────────────────────────────── */
        .meta-grid {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .meta-grid .meta-row { display: table-row; }
        .meta-grid .meta-label,
        .meta-grid .meta-val {
            display: table-cell;
            padding: 2px 6px 2px 0;
            vertical-align: top;
        }
        .meta-grid .meta-label { width: 130px; font-weight: 600; color: #374151; }
        .meta-grid .meta-sep   { display: table-cell; padding: 2px 6px 2px 0; }

        /* ── Section titles ───────────────────────────────── */
        .section-title {
            font-size: 9.5pt;
            font-weight: 700;
            color: #1d4ed8;
            border-left: 3px solid #1d4ed8;
            padding-left: 6px;
            margin: 14px 0 8px;
        }

        /* ── Tables ───────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        thead th {
            background: #1d4ed8;
            color: #fff;
            padding: 4px 6px;
            text-align: center;
            border: 1px solid #1e40af;
            font-weight: 600;
        }
        tbody td {
            padding: 3px 6px;
            border: 1px solid #d1d5db;
            text-align: center;
        }
        tbody tr:nth-child(even) td { background: #f0f4ff; }
        tbody tr:last-child td { font-weight: 600; background: #e0e7ff; }

        .td-left  { text-align: left !important; }
        .td-right { text-align: right !important; }
        .td-mono  { font-family: monospace; }

        /* ── Status badge ─────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: 700;
        }
        .badge-accepted { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-draft    { background: #f3f4f6; color: #374151; }

        /* ── Summary box ──────────────────────────────────── */
        .summary-box {
            border: 1px solid #93c5fd;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 14px;
            background: #eff6ff;
        }
        .summary-box table { font-size: 8.5pt; }
        .summary-box td { border: none; padding: 2px 8px 2px 0; text-align: left; }
        .summary-box .lbl { font-weight: 600; color: #1e40af; width: 200px; }
        .summary-box .val { font-family: monospace; color: #1a1a1a; }

        /* ── Matrix section ───────────────────────────────── */
        .matrix-container {
            margin-bottom: 12px;
        }
        .matrix-title {
            font-size: 8pt;
            font-weight: 700;
            color: #374151;
            margin-bottom: 4px;
        }
        .matrix-note {
            font-size: 7.5pt;
            color: #6b7280;
            margin-top: 4px;
            font-style: italic;
        }

        /* ── Convergence ──────────────────────────────────── */
        .convergence-ok   { color: #059669; font-weight: 700; }
        .convergence-fail { color: #dc2626; font-weight: 700; }

        /* ── Footer ───────────────────────────────────────── */
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #d1d5db;
            padding: 4px 0;
            font-size: 7pt;
            color: #9ca3af;
            text-align: center;
        }

        .page-break { page-break-before: always; }

        .highlight-warn { color: #b45309; font-weight: 700; }
        .highlight-ok   { color: #065f46; font-weight: 700; }
    </style>
</head>
<body>

<div class="page-footer">
    GeoLevel &mdash; Laporan Jaring Sipat Datar &mdash; {{ $project->name }}
    &mdash; Dicetak: {{ now()->format('d/m/Y H:i') }}
</div>

{{-- ══════════════════════════════════════════════════════════
     HALAMAN 1 — INFO PROYEK + RINGKASAN JARING
     ═════════════════════════════════════════════════════════ --}}

<div class="page-header">
    <h1>Laporan Jaring Sipat Datar (Loop Network)</h1>
    <div class="subtitle">Perataan Kuadrat Terkecil (Least Squares Adjustment) &mdash; SNI 19-6988-2004</div>
</div>

{{-- Info Proyek --}}
<div class="section-title">Informasi Proyek</div>
<div class="summary-box">
    <table>
        <tr>
            <td class="lbl">Nama Proyek</td>
            <td>:</td>
            <td class="val">{{ $project->name }}</td>
            <td width="30"></td>
            <td class="lbl">Status</td>
            <td>:</td>
            <td>
                @php $st = $project->status ?? 'draft'; @endphp
                <span class="badge badge-{{ in_array($st, ['accepted']) ? 'accepted' : ($st === 'rejected' ? 'rejected' : 'draft') }}">
                    {{ strtoupper($st) }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="lbl">Lokasi</td>
            <td>:</td>
            <td class="val">{{ $project->location }}</td>
            <td></td>
            <td class="lbl">Tanggal Survei</td>
            <td>:</td>
            <td class="val">{{ \Carbon\Carbon::parse($project->survey_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="lbl">Benchmark</td>
            <td>:</td>
            <td class="val">{{ $project->benchmark_name }} ({{ number_format($project->benchmark_elevation, 4) }} m)</td>
            <td></td>
            <td class="lbl">Kelas Toleransi</td>
            <td>:</td>
            <td class="val">{{ strtoupper($project->tolerance_class) }}</td>
        </tr>
        <tr>
            <td class="lbl">Deskripsi</td>
            <td>:</td>
            <td class="val" colspan="5">{{ $project->description ?: '-' }}</td>
        </tr>
    </table>
</div>

{{-- Ringkasan Jaring --}}
<div class="section-title">Ringkasan Jaring</div>
<div class="summary-box">
    <table>
        <tr>
            <td class="lbl">Jumlah Jalur (Observasi)</td>
            <td>:</td>
            <td class="val">{{ $stats['n_legs'] }} jalur</td>
            <td width="30"></td>
            <td class="lbl">Jumlah Titik Unik</td>
            <td>:</td>
            <td class="val">{{ $stats['n_points'] }} titik</td>
        </tr>
        <tr>
            <td class="lbl">Derajat Kebebasan (r)</td>
            <td>:</td>
            <td class="val">{{ $stats['redundancy'] }}</td>
            <td></td>
            <td class="lbl">Total Jarak Jaring</td>
            <td>:</td>
            <td class="val">{{ number_format($stats['total_distance_km'], 4) }} km</td>
        </tr>
        <tr>
            <td class="lbl">Kesalahan Baku Referensi (σ₀)</td>
            <td>:</td>
            <td class="val {{ abs($stats['sigma0_mm']) <= 5 ? 'highlight-ok' : 'highlight-warn' }}">
                {{ number_format($stats['sigma0_mm'], 4) }} mm
            </td>
            <td></td>
            <td class="lbl">Metode Perataan</td>
            <td>:</td>
            <td class="val">Kuadrat Terkecil (Weighted Least Squares)</td>
        </tr>
        <tr>
            <td class="lbl">Kesalahan Penutup Total</td>
            <td>:</td>
            @php $fh_mm = ($project->closure_error ?? 0) * 1000; @endphp
            <td class="val {{ abs($fh_mm) <= (($project->allowed_tolerance ?? 999) * 1000) ? 'highlight-ok' : 'highlight-warn' }}">
                {{ number_format($fh_mm, 3) }} mm
            </td>
            <td></td>
            <td class="lbl">Toleransi Diijinkan</td>
            <td>:</td>
            <td class="val">{{ number_format(($project->allowed_tolerance ?? 0) * 1000, 3) }} mm</td>
        </tr>
        <tr>
            <td class="lbl">Konvergensi Iterasi</td>
            <td>:</td>
            <td class="{{ $stats['converged'] ? 'convergence-ok' : 'convergence-fail' }}">
                {{ $stats['converged'] ? '✓ Konvergen (' . $stats['iterations'] . ' iterasi)' : '✗ Tidak Konvergen' }}
            </td>
            <td></td>
            <td class="lbl">Iterasi Maksimum</td>
            <td>:</td>
            <td class="val">{{ $stats['max_iterations'] }}</td>
        </tr>
    </table>
</div>

{{-- Daftar Jalur Observasi --}}
<div class="section-title">Daftar Jalur Observasi (Network Legs)</div>
<table>
    <thead>
        <tr>
            <th style="width:30px">No</th>
            <th>Dari Titik</th>
            <th>Ke Titik</th>
            <th>Beda Tinggi Observasi (m)</th>
            <th>Jarak (km)</th>
            <th>Bobot (1/d)</th>
            <th>Beda Tinggi Terkoreksi (m)</th>
            <th>Koreksi (mm)</th>
            <th>Residual (mm)</th>
        </tr>
    </thead>
    <tbody>
        @php $totalDist = 0; $totalCorr = 0; @endphp
        @foreach ($legs as $i => $leg)
            @php
                $corrMm  = ($leg->corrected_delta_h !== null ? ((float)$leg->corrected_delta_h - (float)$leg->observed_delta_h) : 0) * 1000;
                $adjDh   = ($leg->corrected_delta_h !== null ? (float)$leg->corrected_delta_h : (float)$leg->observed_delta_h);
                $resMm   = ($leg->residual ?? 0) * 1000;
                $totalDist += (float) $leg->distance_m / 1000;
                $totalCorr += $corrMm;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="td-left">{{ $leg->from_point }}</td>
                <td class="td-left">{{ $leg->to_point }}</td>
                <td class="td-mono td-right">{{ number_format($leg->observed_delta_h, 4) }}</td>
                <td class="td-mono td-right">{{ number_format((float) $leg->distance_m / 1000, 4) }}</td>
                <td class="td-mono td-right">{{ number_format(1 / max((float) $leg->distance_m / 1000, 0.0001), 4) }}</td>
                <td class="td-mono td-right">{{ number_format($adjDh, 4) }}</td>
                <td class="td-mono td-right {{ abs($corrMm) > 5 ? 'highlight-warn' : '' }}">
                    {{ number_format($corrMm, 3) }}
                </td>
                <td class="td-mono td-right">{{ number_format($resMm, 3) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4"><strong>Total</strong></td>
            <td class="td-mono td-right"><strong>{{ number_format($totalDist, 4) }}</strong></td>
            <td></td>
            <td></td>
            <td class="td-mono td-right"><strong>{{ number_format($totalCorr, 3) }}</strong></td>
            <td></td>
        </tr>
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════
     HALAMAN 2 — ELEVASI TITIK TERKOREKSI
     ═════════════════════════════════════════════════════════ --}}

<div class="page-break"></div>

<div class="page-header">
    <h1>Laporan Jaring Sipat Datar — Elevasi Terkoreksi</h1>
    <div class="subtitle">{{ $project->name }} &mdash; {{ \Carbon\Carbon::parse($project->survey_date)->format('d/m/Y') }}</div>
</div>

<div class="section-title">Elevasi Titik Hasil Perataan</div>
<table>
    <thead>
        <tr>
            <th style="width:30px">No</th>
            <th>Nama Titik</th>
            <th>Elevasi Awal (m)</th>
            <th>Koreksi (mm)</th>
            <th>Elevasi Terkoreksi (m)</th>
            <th>Std. Deviasi (mm)</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($adjustedPoints as $i => $pt)
            @php
                $corrMm = ($pt['correction'] ?? 0) * 1000;
                $isBM   = ($pt['is_benchmark'] ?? false);
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="td-left"><strong>{{ $pt['point_name'] }}</strong></td>
                <td class="td-mono td-right">{{ number_format($pt['initial_elevation'], 4) }}</td>
                <td class="td-mono td-right {{ abs($corrMm) > 5 ? 'highlight-warn' : '' }}">
                    {{ $isBM ? '—' : number_format($corrMm, 3) }}
                </td>
                <td class="td-mono td-right highlight-ok">
                    {{ number_format($pt['adjusted_elevation'], 4) }}
                </td>
                <td class="td-mono td-right">
                    {{ isset($pt['std_dev_mm']) ? number_format($pt['std_dev_mm'], 3) : '—' }}
                </td>
                <td class="td-left">{{ $isBM ? 'Benchmark (tetap)' : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Perbandingan Beda Tinggi --}}
<div class="section-title">Perbandingan Beda Tinggi: Observasi vs Terkoreksi</div>
<table>
    <thead>
        <tr>
            <th style="width:30px">No</th>
            <th>Jalur</th>
            <th>ΔH Observasi (m)</th>
            <th>ΔH Terkoreksi (m)</th>
            <th>Selisih (mm)</th>
            <th>Bobot</th>
            <th>Residual Berbobot (mm)</th>
        </tr>
    </thead>
    <tbody>
        @php $sumWv2 = 0; @endphp
        @foreach ($legs as $i => $leg)
            @php
                $adjDh   = ($leg->corrected_delta_h !== null ? (float)$leg->corrected_delta_h : (float)$leg->observed_delta_h);
                $diffMm  = ($leg->corrected_delta_h !== null ? ((float)$leg->corrected_delta_h - (float)$leg->observed_delta_h) : 0) * 1000;
                $w       = 1 / max((float) $leg->distance_m / 1000.0001, 0.0001);
                $resMm   = ($leg->residual ?? 0) * 1000;
                $wv2     = $w * $resMm * $resMm;
                $sumWv2 += $wv2;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="td-left">{{ $leg->from_point }} → {{ $leg->to_point }}</td>
                <td class="td-mono td-right">{{ number_format($leg->observed_delta_h, 4) }}</td>
                <td class="td-mono td-right">{{ number_format($adjDh, 4) }}</td>
                <td class="td-mono td-right {{ abs($diffMm) > 5 ? 'highlight-warn' : '' }}">{{ number_format($diffMm, 3) }}</td>
                <td class="td-mono td-right">{{ number_format($w, 4) }}</td>
                <td class="td-mono td-right">{{ number_format(sqrt($wv2), 3) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5"><strong>ΣWv² (VTPV)</strong></td>
            <td></td>
            <td class="td-mono td-right"><strong>{{ number_format($sumWv2, 6) }}</strong></td>
        </tr>
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════
     HALAMAN 3 — ANALISIS STATISTIK
     ═════════════════════════════════════════════════════════ --}}

<div class="page-break"></div>

<div class="page-header">
    <h1>Laporan Jaring Sipat Datar — Analisis Statistik</h1>
    <div class="subtitle">{{ $project->name }} &mdash; {{ \Carbon\Carbon::parse($project->survey_date)->format('d/m/Y') }}</div>
</div>

<div class="section-title">Analisis Kesalahan Baku</div>
<div class="summary-box">
    <table>
        <tr>
            <td class="lbl">VTPV (Σ Residual Berbobot²)</td>
            <td>:</td>
            <td class="val">{{ number_format($stats['vtpv'] ?? 0, 6) }}</td>
        </tr>
        <tr>
            <td class="lbl">Derajat Kebebasan (r = n – u)</td>
            <td>:</td>
            <td class="val">{{ $stats['redundancy'] }} ({{ $stats['n_legs'] }} observasi – {{ $stats['n_unknowns'] }} unknown)</td>
        </tr>
        <tr>
            <td class="lbl">Variance Factor (σ₀²)</td>
            <td>:</td>
            <td class="val">{{ number_format($stats['variance_factor'] ?? 0, 6) }}</td>
        </tr>
        <tr>
            <td class="lbl">Kesalahan Baku Referensi (σ₀)</td>
            <td>:</td>
            <td class="val {{ abs($stats['sigma0_mm'] ?? 0) <= 5 ? 'highlight-ok' : 'highlight-warn' }}">
                {{ number_format($stats['sigma0_mm'] ?? 0, 4) }} mm
            </td>
        </tr>
        <tr>
            <td class="lbl">Evaluasi Kualitas Perataan</td>
            <td>:</td>
            <td class="{{ abs($stats['sigma0_mm'] ?? 0) <= 5 ? 'highlight-ok' : 'highlight-warn' }}">
                @if (abs($stats['sigma0_mm'] ?? 0) <= 5)
                    ✓ Baik — σ₀ ≤ 5 mm
                @elseif (abs($stats['sigma0_mm'] ?? 0) <= 10)
                    ⚠ Cukup — σ₀ antara 5–10 mm
                @else
                    ✗ Perlu diperiksa — σ₀ > 10 mm
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- Tabel Residual Terstandarisasi --}}
<div class="section-title">Residual Terstandarisasi (Deteksi Outlier)</div>
<table>
    <thead>
        <tr>
            <th style="width:30px">No</th>
            <th>Jalur</th>
            <th>Residual v (mm)</th>
            <th>Std. Dev. Residual (mm)</th>
            <th>Residual Terstand. (w)</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($legs as $i => $leg)
            @php
                $resMm    = ($leg->residual ?? 0) * 1000;
                $sigma0   = $stats['sigma0_mm'] ?? 1;
                $w_val    = $sigma0 > 0 ? abs($resMm) / $sigma0 : 0;
                $isOutlier = $w_val > 3.29; // 99% confidence
                $isSuspect = $w_val > 1.96 && !$isOutlier;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="td-left">{{ $leg->from_point }} → {{ $leg->to_point }}</td>
                <td class="td-mono td-right">{{ number_format($resMm, 3) }}</td>
                <td class="td-mono td-right">{{ number_format($sigma0, 3) }}</td>
                <td class="td-mono td-right {{ $isOutlier ? 'highlight-warn' : ($isSuspect ? 'highlight-warn' : '') }}">
                    {{ number_format($w_val, 3) }}
                </td>
                <td class="{{ $isOutlier ? 'highlight-warn' : ($isSuspect ? 'highlight-warn' : 'highlight-ok') }}">
                    @if ($isOutlier)
                        ✗ OUTLIER (|w| > 3.29)
                    @elseif ($isSuspect)
                        ⚠ Mencurigakan (|w| > 1.96)
                    @else
                        ✓ Normal
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Informasi Jaring Konektivitas --}}
<div class="section-title">Konektivitas Jaring</div>
<table>
    <thead>
        <tr>
            <th>Titik</th>
            <th>Jumlah Jalur Terhubung</th>
            <th>Terhubung Ke</th>
            <th>Peran</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($connectivity as $pt => $info)
            <tr>
                <td class="td-left"><strong>{{ $pt }}</strong></td>
                <td>{{ $info['degree'] }}</td>
                <td class="td-left">{{ implode(', ', $info['neighbors']) }}</td>
                <td class="{{ $info['is_benchmark'] ? 'highlight-ok' : '' }} td-left">
                    {{ $info['is_benchmark'] ? 'Benchmark (Datum)' : ($info['degree'] >= 2 ? 'Junction' : 'Endpoint') }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Catatan & Tanda Tangan --}}
<div class="section-title">Catatan</div>
<div class="summary-box">
    <table>
        <tr>
            <td style="font-size:8pt; color:#374151; line-height:1.6">
                1. Perataan dilakukan dengan metode Kuadrat Terkecil Berbobot (Weighted Least Squares).<br>
                2. Bobot observasi: w<sub>i</sub> = 1 / d<sub>i</sub> (km), proporsional terhadap kebalikan jarak jalur.<br>
                3. Outlier terdeteksi jika residual terstandarisasi |w| > 3.29 (tingkat kepercayaan 99%).<br>
                4. Referensi standar: SNI 19-6988-2004 — Jaringan Kontrol Vertikal dengan Metode Sipat Datar.<br>
                5. Laporan ini digenerate otomatis oleh GeoLevel pada {{ now()->format('d/m/Y H:i:s') }}.
            </td>
        </tr>
    </table>
</div>

<br><br>
<table style="font-size:8.5pt; border:none;">
    <tr>
        <td style="width:33%; text-align:center; border:none;">
            <br><br>
            ________________________<br>
            Pelaksana Survei
        </td>
        <td style="width:33%; text-align:center; border:none;">
            <br><br>
            ________________________<br>
            Pemeriksa
        </td>
        <td style="width:33%; text-align:center; border:none;">
            {{ $project->location }}, {{ now()->format('d/m/Y') }}<br><br>
            ________________________<br>
            Penanggung Jawab
        </td>
    </tr>
</table>

</body>
</html>