<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GeoLevel — Sistem Survei Waterpass Digital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{
      --navy:#1A1A2E;--navy2:#16213E;--blue:#2563EB;--blue2:#3B82F6;
      --emerald:#10B981;--amber:#F59E0B;--red:#EF4444;
      --text:#F1F5F9;--text2:#94A3B8;--text3:#64748B;
      --card:rgba(255,255,255,0.05);--border:rgba(255,255,255,0.1);
    }
    body{font-family:'Inter',system-ui,sans-serif;background:var(--navy);color:var(--text);overflow-x:hidden}

    /* ── Animations ── */
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
    @keyframes pulse-ring{0%{transform:scale(1);opacity:.6}100%{transform:scale(1.6);opacity:0}}
    @keyframes scan{0%{top:0}100%{top:100%}}
    @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
    @keyframes scrollX{from{transform:translateX(0)}to{transform:translateX(-50%)}}

    .fade-up{animation:fadeUp .7s ease forwards;opacity:0}
    .d1{animation-delay:.1s}.d2{animation-delay:.25s}.d3{animation-delay:.4s}
    .d4{animation-delay:.55s}.d5{animation-delay:.7s}.d6{animation-delay:.85s}

    /* ── Nav ── */
    nav{
      position:sticky;top:0;z-index:100;
      background:rgba(26,26,46,.92);backdrop-filter:blur(12px);
      border-bottom:1px solid var(--border);
      display:flex;align-items:center;justify-content:space-between;
      padding:0 32px;height:48px;
    }
    .nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
    .nav-icon{display:grid;grid-template-columns:1fr 1fr;gap:3px;width:20px}
    .nav-icon span{display:block;border:1.5px solid var(--blue2);border-radius:2px}
    .nav-icon span:nth-child(1){height:8px;opacity:1}
    .nav-icon span:nth-child(2){height:8px;opacity:.5}
    .nav-icon span:nth-child(3){height:8px;opacity:.5}
    .nav-icon span:nth-child(4){height:8px;opacity:.3}
    .nav-brand-text{font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:.2em;color:rgba(255,255,255,.85);text-transform:uppercase}
    .nav-links{display:flex;align-items:center;gap:8px}
    .nav-link{font-size:13px;color:var(--text2);padding:6px 14px;border-radius:6px;cursor:pointer;transition:.2s;text-decoration:none;border:none;background:none}
    .nav-link:hover{color:var(--text);background:rgba(255,255,255,.08)}
    .btn-login{background:var(--blue);color:#fff;font-size:13px;font-weight:600;padding:6px 18px;border-radius:7px;border:none;cursor:pointer;transition:.2s;text-decoration:none;display:inline-flex;align-items:center}
    .btn-login:hover{background:#1d4ed8;transform:translateY(-1px)}

    /* ── Hero ── */
    .hero{
      position:relative;min-height:92vh;display:flex;flex-direction:column;
      align-items:center;justify-content:center;overflow:hidden;padding:80px 32px 60px;
    }
    .grid-canvas{position:absolute;inset:0;width:100%;height:100%}
    .hero-content{position:relative;z-index:2;text-align:center;max-width:720px}
    .hero-eyebrow{
      display:inline-flex;align-items:center;gap:8px;
      font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.2em;
      color:var(--blue2);text-transform:uppercase;
      background:rgba(37,99,235,.15);border:1px solid rgba(37,99,235,.3);
      padding:5px 14px;border-radius:999px;margin-bottom:24px;
    }
    .dot-pulse{width:7px;height:7px;border-radius:50%;background:var(--blue2);position:relative;flex-shrink:0}
    .dot-pulse::after{content:'';position:absolute;inset:-3px;border-radius:50%;border:1px solid var(--blue2);animation:pulse-ring 1.6s ease-out infinite}
    .hero-title{font-size:52px;font-weight:700;line-height:1.1;margin-bottom:20px;letter-spacing:-.02em}
    .hero-title span{color:var(--blue2)}
    .hero-sub{font-size:16px;color:var(--text2);line-height:1.7;max-width:520px;margin:0 auto 40px}
    .hero-cta{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap}
    .btn-primary{
      display:inline-flex;align-items:center;gap:8px;
      background:var(--blue);color:#fff;font-weight:600;font-size:15px;
      padding:12px 28px;border-radius:10px;border:none;cursor:pointer;transition:.25s;
      box-shadow:0 4px 24px rgba(37,99,235,.35);text-decoration:none;
    }
    .btn-primary:hover{background:#1d4ed8;transform:translateY(-2px);box-shadow:0 8px 32px rgba(37,99,235,.45)}
    .btn-ghost{
      display:inline-flex;align-items:center;gap:8px;
      background:transparent;color:var(--text);font-size:15px;font-weight:500;
      padding:12px 28px;border-radius:10px;
      border:1px solid rgba(255,255,255,.2);cursor:pointer;transition:.2s;
    }
    .btn-ghost:hover{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.4)}

    /* ── Instrument float ── */
    .instrument-wrap{position:absolute;right:5%;top:50%;transform:translateY(-50%);opacity:.18;pointer-events:none;animation:float 6s ease-in-out infinite}

    /* ── Stats bar ── */
    .stats-bar{
      display:flex;align-items:stretch;border-top:1px solid var(--border);border-bottom:1px solid var(--border);
      background:rgba(255,255,255,.02);overflow:hidden;
    }
    .stat-item{flex:1;padding:20px 24px;text-align:center;border-right:1px solid var(--border);position:relative;overflow:hidden}
    .stat-item:last-child{border-right:none}
    .stat-item::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
    .stat-item.blue::before{background:var(--blue)}
    .stat-item.emerald::before{background:var(--emerald)}
    .stat-item.amber::before{background:var(--amber)}
    .stat-item.red::before{background:var(--red)}
    .stat-num{font-family:'JetBrains Mono',monospace;font-size:28px;font-weight:600;display:block;line-height:1}
    .stat-label{font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-top:5px}
    .stat-item.blue .stat-num{color:var(--blue2)}
    .stat-item.emerald .stat-num{color:var(--emerald)}
    .stat-item.amber .stat-num{color:var(--amber)}
    .stat-item.red .stat-num{color:var(--red)}

    /* ── Section ── */
    .section{padding:80px 32px}
    .section-eyebrow{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.25em;color:var(--blue2);text-transform:uppercase;margin-bottom:12px}
    .section-title{font-size:36px;font-weight:700;line-height:1.2;margin-bottom:12px;letter-spacing:-.02em}
    .section-sub{font-size:15px;color:var(--text2);line-height:1.7;max-width:480px}

    /* ── Feature cards ── */
    .features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:52px}
    @media(max-width:900px){.features-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:600px){.features-grid{grid-template-columns:1fr}}
    .feat-card{
      background:var(--card);border:1px solid var(--border);border-radius:14px;
      padding:28px 24px;transition:.3s;cursor:default;position:relative;overflow:hidden;
    }
    .feat-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(37,99,235,.06),transparent);opacity:0;transition:.3s}
    .feat-card:hover{border-color:rgba(37,99,235,.4);transform:translateY(-3px)}
    .feat-card:hover::before{opacity:1}
    .feat-icon{
      width:44px;height:44px;border-radius:10px;
      background:rgba(37,99,235,.2);border:1px solid rgba(37,99,235,.3);
      display:flex;align-items:center;justify-content:center;margin-bottom:16px;
    }
    .feat-icon svg{width:22px;height:22px;stroke:var(--blue2);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .feat-title{font-size:15px;font-weight:600;margin-bottom:8px}
    .feat-desc{font-size:13px;color:var(--text2);line-height:1.65}
    .feat-tag{
      display:inline-block;font-family:'JetBrains Mono',monospace;font-size:10px;
      background:rgba(37,99,235,.15);color:var(--blue2);border:1px solid rgba(37,99,235,.25);
      padding:2px 8px;border-radius:4px;margin-top:14px;
    }

    /* ── Workflow section ── */
    .workflow-section{padding:80px 32px;background:rgba(255,255,255,.02);border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
    .workflow-grid{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;margin-top:52px}
    @media(max-width:800px){.workflow-grid{grid-template-columns:1fr}}
    .workflow-steps{display:flex;flex-direction:column;gap:0}
    .workflow-step{
      display:flex;gap:20px;padding:20px 0;border-bottom:1px solid var(--border);
      transition:.2s;position:relative;
    }
    .workflow-step:last-child{border-bottom:none}
    .workflow-step:hover .step-num{background:rgba(37,99,235,.3)}
    .step-num{
      width:36px;height:36px;border-radius:50%;
      background:rgba(37,99,235,.15);border:1px solid rgba(37,99,235,.3);
      display:flex;align-items:center;justify-content:center;
      font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600;
      color:var(--blue2);flex-shrink:0;transition:.2s;
    }
    .step-content h4{font-size:14px;font-weight:600;margin-bottom:4px}
    .step-content p{font-size:13px;color:var(--text2);line-height:1.6}

    /* ── Chart preview ── */
    .chart-preview{
      background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;
      padding:20px;position:relative;overflow:hidden;
    }
    .chart-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
    .chart-title{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text2);letter-spacing:.1em}
    .chart-badge{font-size:10px;background:rgba(16,185,129,.15);color:var(--emerald);border:1px solid rgba(16,185,129,.3);padding:2px 8px;border-radius:4px;font-family:'JetBrains Mono',monospace}
    .scan-line{position:absolute;left:0;right:0;height:1px;background:rgba(37,99,235,.4);animation:scan 3s linear infinite;pointer-events:none;z-index:2}

    /* ── Table preview ── */
    .table-preview{background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-top:8px}
    .table-preview table{width:100%;font-family:'JetBrains Mono',monospace;font-size:11px;border-collapse:collapse}
    .table-preview th{background:rgba(26,26,46,.8);color:var(--text3);padding:7px 10px;text-align:right;font-weight:500;letter-spacing:.05em}
    .table-preview th:first-child{text-align:left}
    .table-preview td{padding:6px 10px;text-align:right;border-top:1px solid rgba(255,255,255,.04);color:var(--text2)}
    .table-preview td:first-child{text-align:left;color:var(--text);font-weight:600}
    .table-preview tr.bs td{color:rgba(96,165,250,.9)}
    .table-preview tr.fs td{color:rgba(252,165,165,.9)}

    /* ── Tolerance cards ── */
    .tol-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:40px}
    @media(max-width:700px){.tol-grid{grid-template-columns:repeat(2,1fr)}}
    .tol-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center;transition:.25s}
    .tol-card:hover{transform:translateY(-2px)}
    .tol-class{font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:700;margin-bottom:4px}
    .tol-val{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text2);margin-bottom:8px}
    .tol-bar{height:4px;border-radius:2px;margin-top:8px}
    .tol-card.laa .tol-class{color:var(--emerald)}.tol-card.laa .tol-bar{background:var(--emerald)}
    .tol-card.la  .tol-class{color:var(--blue2)}.tol-card.la  .tol-bar{background:var(--blue2)}
    .tol-card.lb  .tol-class{color:var(--amber)}.tol-card.lb  .tol-bar{background:var(--amber)}
    .tol-card.lc  .tol-class{color:var(--red)}.tol-card.lc  .tol-bar{background:var(--red)}

    /* ── CTA section ── */
    .cta-section{
      padding:80px 32px;text-align:center;
      background:linear-gradient(180deg,transparent,rgba(37,99,235,.08) 50%,transparent);
      border-top:1px solid var(--border);
    }
    .cta-title{font-size:40px;font-weight:700;margin-bottom:16px;line-height:1.2}
    .cta-sub{font-size:15px;color:var(--text2);max-width:440px;margin:0 auto 36px;line-height:1.7}

    /* ── Formula strip ── */
    .formula-strip{
      background:rgba(255,255,255,.03);border-top:1px solid var(--border);border-bottom:1px solid var(--border);
      padding:16px 32px;overflow:hidden;position:relative;
    }
    .formula-scroll{
      display:flex;gap:48px;font-family:'JetBrains Mono',monospace;font-size:12px;
      color:var(--text3);white-space:nowrap;animation:scrollX 20s linear infinite;
    }
    .formula-scroll span{color:var(--blue2)}

    /* ── Footer ── */
    footer{background:var(--navy2);border-top:1px solid var(--border);padding:48px 32px 24px}
    .footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:40px}
    @media(max-width:800px){.footer-grid{grid-template-columns:1fr 1fr}}
    @media(max-width:500px){.footer-grid{grid-template-columns:1fr}}
    .footer-brand{font-family:'JetBrains Mono',monospace;font-size:13px;letter-spacing:.15em;font-weight:600;margin-bottom:12px;color:var(--text)}
    .footer-desc{font-size:13px;color:var(--text3);line-height:1.7;max-width:240px}
    .footer-col h5{font-size:11px;text-transform:uppercase;letter-spacing:.15em;color:var(--text3);margin-bottom:14px;font-family:'JetBrains Mono',monospace}
    .footer-col a{display:block;font-size:13px;color:var(--text2);margin-bottom:8px;text-decoration:none;transition:.15s}
    .footer-col a:hover{color:var(--text)}
    .footer-bottom{display:flex;align-items:center;justify-content:space-between;padding-top:24px;border-top:1px solid var(--border);flex-wrap:wrap;gap:8px}
    .footer-copy{font-size:12px;color:var(--text3)}
    .footer-sni{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text3);letter-spacing:.1em}

    /* ── Modal ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:200;align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal{background:#1e2235;border:1px solid rgba(255,255,255,.15);border-radius:16px;width:360px;overflow:hidden;animation:fadeUp .25s ease}
    .modal-header{background:var(--navy);padding:20px 24px;border-bottom:1px solid rgba(255,255,255,.08)}
    .modal-header p{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--blue2);letter-spacing:.2em;text-transform:uppercase;margin-bottom:4px}
    .modal-header h3{font-size:18px;font-weight:700}
    .modal-body{padding:24px}
    .form-field{margin-bottom:16px}
    .form-label{display:block;font-size:12px;color:var(--text2);margin-bottom:6px}
    .form-input{
      width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);
      border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text);
      outline:none;transition:.2s;font-family:inherit;
    }
    .form-input:focus{border-color:rgba(37,99,235,.6);background:rgba(37,99,235,.06)}
    .modal-btns{display:flex;gap:10px;margin-top:20px}
    .btn-cancel{flex:1;background:transparent;border:1px solid rgba(255,255,255,.15);color:var(--text2);padding:9px;border-radius:8px;cursor:pointer;font-size:13px;transition:.2s}
    .btn-cancel:hover{border-color:rgba(255,255,255,.3)}
    .btn-submit{flex:2;background:var(--blue);border:none;color:#fff;font-weight:600;padding:9px;border-radius:8px;cursor:pointer;font-size:13px;transition:.2s}
    .btn-submit:hover{background:#1d4ed8}
  </style>
</head>
<body>

<!-- ── NAV ── -->
<nav>
  <a class="nav-brand" href="{{ url('/') }}">
    <div class="nav-icon">
      <span></span><span></span><span></span><span></span>
    </div>
    <span class="nav-brand-text">GeoLevel</span>
  </a>
  <div class="nav-links">
    <a class="nav-link" href="#fitur">Fitur</a>
    <a class="nav-link" href="#alur">Alur Kerja</a>
    <a class="nav-link" href="#standar">Standar SNI</a>

    @auth
      {{-- Jika sudah login, arahkan langsung ke dashboard/projects --}}
      <a class="btn-login" href="{{ route('projects.index') }}">Dashboard →</a>
    @else
      {{-- Jika belum login, tombol membuka modal --}}
      <button class="btn-login" onclick="openModal()">Masuk ke Sistem</button>
    @endauth
  </div>
</nav>

<!-- ── FORMULA TICKER ── -->
<div class="formula-strip">
  <div class="formula-scroll">
    <span>BT = (BA + BB) / 2</span>
    <span style="color:var(--text3)">·</span>
    <span>fh = |ΣBS − ΣFS|</span>
    <span style="color:var(--text3)">·</span>
    <span>r = c√d <span style="color:var(--text3)">(SNI 19-6988-2004)</span></span>
    <span style="color:var(--text3)">·</span>
    <span>HI = H<sub>prev</sub> + BS</span>
    <span style="color:var(--text3)">·</span>
    <span>Elev<sub>FS</sub> = HI − FS</span>
    <span style="color:var(--text3)">·</span>
    <span>D = (BA − BB) × 100</span>
    <span style="color:var(--text3)">·</span>
    <span>δ<sub>BT</sub> ≤ 0.002 m</span>
    <span style="color:var(--text3)">·</span>
    <span>Koreksi<sub>i</sub> = −fh / n</span>
    <span style="color:var(--text3)">·</span>
    <!-- duplicate for seamless loop -->
    <span>BT = (BA + BB) / 2</span>
    <span style="color:var(--text3)">·</span>
    <span>fh = |ΣBS − ΣFS|</span>
    <span style="color:var(--text3)">·</span>
    <span>r = c√d <span style="color:var(--text3)">(SNI 19-6988-2004)</span></span>
    <span style="color:var(--text3)">·</span>
    <span>HI = H<sub>prev</sub> + BS</span>
    <span style="color:var(--text3)">·</span>
    <span>Elev<sub>FS</sub> = HI − FS</span>
    <span style="color:var(--text3)">·</span>
    <span>D = (BA − BB) × 100</span>
    <span style="color:var(--text3)">·</span>
    <span>δ<sub>BT</sub> ≤ 0.002 m</span>
    <span style="color:var(--text3)">·</span>
    <span>Koreksi<sub>i</sub> = −fh / n</span>
  </div>
</div>

<!-- ── HERO ── -->
<section class="hero" id="hero">
  <canvas class="grid-canvas" id="gridCanvas"></canvas>

  <!-- Floating survey instrument SVG -->
  <div class="instrument-wrap">
    <svg width="160" height="260" viewBox="0 0 160 260" fill="none">
      <line x1="80" y1="180" x2="20" y2="255" stroke="#60A5FA" stroke-width="3" stroke-linecap="round"/>
      <line x1="80" y1="180" x2="80" y2="258" stroke="#60A5FA" stroke-width="3" stroke-linecap="round"/>
      <line x1="80" y1="180" x2="140" y2="255" stroke="#60A5FA" stroke-width="3" stroke-linecap="round"/>
      <rect x="60" y="170" width="40" height="14" rx="4" fill="#2563EB" stroke="#60A5FA" stroke-width="1"/>
      <rect x="45" y="130" width="70" height="42" rx="6" fill="#1e3a6e" stroke="#60A5FA" stroke-width="1.5"/>
      <circle cx="48" cy="151" r="10" fill="#152a52" stroke="#60A5FA" stroke-width="1.5"/>
      <circle cx="48" cy="151" r="5" fill="#0f1e3c" stroke="#60A5FA" stroke-width="1"/>
      <circle cx="112" cy="151" r="10" fill="#152a52" stroke="#60A5FA" stroke-width="1.5"/>
      <circle cx="112" cy="151" r="5" fill="#0f1e3c" stroke="#60A5FA" stroke-width="1"/>
      <line x1="45" y1="151" x2="115" y2="151" stroke="#60A5FA" stroke-width=".7" stroke-dasharray="4 3"/>
      <rect x="62" y="135" width="36" height="10" rx="5" fill="#0f1e3c" stroke="#60A5FA" stroke-width="1"/>
      <circle cx="80" cy="140" r="3" fill="#10B981"/>
      <rect x="140" y="60" width="12" height="196" rx="2" fill="#1e3a6e" stroke="#60A5FA" stroke-width="1"/>
      <line x1="140" y1="100" x2="152" y2="100" stroke="#EF4444" stroke-width="2"/>
      <line x1="140" y1="120" x2="152" y2="120" stroke="#F59E0B" stroke-width="1.5"/>
      <line x1="140" y1="140" x2="152" y2="140" stroke="#F59E0B" stroke-width="1.5"/>
      <line x1="140" y1="160" x2="152" y2="160" stroke="#EF4444" stroke-width="2"/>
      <line x1="140" y1="180" x2="152" y2="180" stroke="#F59E0B" stroke-width="1.5"/>
      <line x1="112" y1="151" x2="140" y2="151" stroke="#60A5FA" stroke-width="1" stroke-dasharray="3 2" opacity=".7"/>
    </svg>
  </div>

  <div class="hero-content">
    <div class="hero-eyebrow fade-up d1">
      <span class="dot-pulse"></span>
      Sistem Survei Sipat Datar Digital
    </div>
    <h1 class="hero-title fade-up d2">
      Waterpass Survey<br><span>Lebih Akurat.</span><br>Lebih Cepat.
    </h1>
    <p class="hero-sub fade-up d3">
      GeoLevel mengotomasi pipeline perhitungan sipat datar — dari bacaan lapangan hingga elevasi terkoreksi, grafik profil, dan ekspor PDF field book sesuai standar SNI.
    </p>
    <div class="hero-cta fade-up d4">
      @auth
        <a class="btn-primary" href="{{ route('projects.index') }}">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          Buka Dashboard
        </a>
      @else
        <button class="btn-primary" onclick="openModal()">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          Mulai Sekarang
        </button>
      @endauth
      <button class="btn-ghost" onclick="document.getElementById('fitur').scrollIntoView({behavior:'smooth'})">
        Pelajari Fitur ↓
      </button>
    </div>
  </div>

  <!-- Floating data cards -->
  <div style="position:absolute;bottom:40px;left:32px;z-index:2;animation:float 5s ease-in-out infinite" class="fade-up d5">
    <div style="background:rgba(26,26,46,.9);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:12px 16px;backdrop-filter:blur(8px)">
      <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text3);margin-bottom:4px">fh terkomputasi</div>
      <div style="font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:600;color:var(--emerald)">0.001000 m</div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px">✓ DITERIMA — LA Class</div>
    </div>
  </div>
  <div style="position:absolute;bottom:40px;right:32px;z-index:2;animation:float 5s 1.5s ease-in-out infinite" class="fade-up d6">
    <div style="background:rgba(26,26,46,.9);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:12px 16px;backdrop-filter:blur(8px)">
      <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text3);margin-bottom:4px">Deviasi BT</div>
      <div style="font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:600;color:var(--blue2)">0.0002 m</div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px">✓ ≤ 0.002 m — Valid</div>
    </div>
  </div>
</section>

<!-- ── STATS BAR ── -->
<div class="stats-bar">
  <div class="stat-item blue">
    <span class="stat-num" id="c1">0</span>
    <div class="stat-label">Proyek Aktif</div>
  </div>
  <div class="stat-item emerald">
    <span class="stat-num" id="c2">0</span>
    <div class="stat-label">Toleransi Kelas</div>
  </div>
  <div class="stat-item amber">
    <span class="stat-num" id="c3">0</span>
    <div class="stat-label">Format Ekspor</div>
  </div>
  <div class="stat-item red">
    <span class="stat-num" id="c4">0%</span>
    <div class="stat-label">bcmath Presisi</div>
  </div>
</div>

<!-- ── FEATURES ── -->
<section class="section" id="fitur">
  <div class="section-eyebrow fade-up">Kemampuan Sistem</div>
  <h2 class="section-title fade-up d1">Dari Lapangan ke<br>Field Book Otomatis</h2>
  <p class="section-sub fade-up d2">Seluruh pipeline perhitungan sipat datar diotomasi dengan presisi bcmath dan validasi ketat setiap langkah.</p>

  <div class="features-grid">
    <div class="feat-card fade-up d1">
      <div class="feat-icon">
        <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div class="feat-title">Validasi BT Real-Time</div>
      <div class="feat-desc">Deviasi BT dihitung otomatis saat input — bacaan ditolak jika |BT_field − BT_computed| > 0.002 m sesuai standar lapangan.</div>
      <div class="feat-tag">bcmath · presisi penuh</div>
    </div>
    <div class="feat-card fade-up d2">
      <div class="feat-icon" style="background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.3)">
        <svg viewBox="0 0 24 24" style="stroke:var(--emerald)"><path d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>
      </div>
      <div class="feat-title">Pipeline Elevasi Otomatis</div>
      <div class="feat-desc">Setiap perubahan bacaan memicu recalculation — HI, ΔH, dan elevasi semua titik dihitung ulang secara idempoten.</div>
      <div class="feat-tag" style="background:rgba(16,185,129,.15);color:var(--emerald);border-color:rgba(16,185,129,.25)">event-driven · queue</div>
    </div>
    <div class="feat-card fade-up d3">
      <div class="feat-icon" style="background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.3)">
        <svg viewBox="0 0 24 24" style="stroke:var(--amber)"><path d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
      </div>
      <div class="feat-title">Perataan Bowditch & Equal</div>
      <div class="feat-desc">Dua metode perataan kesalahan — distribusi merata atau proporsional jarak. Koreksi dapat direset kapan saja.</div>
      <div class="feat-tag" style="background:rgba(245,158,11,.15);color:var(--amber);border-color:rgba(245,158,11,.25)">reversible · bowditch</div>
    </div>
    <div class="feat-card fade-up d4">
      <div class="feat-icon" style="background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.3)">
        <svg viewBox="0 0 24 24" style="stroke:var(--red)"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      </div>
      <div class="feat-title">Grafik Profil Memanjang</div>
      <div class="feat-desc">Visualisasi profil longitudinal dan cross-section berbasis Chart.js — dataset siap pakai untuk perencanaan konstruksi.</div>
      <div class="feat-tag" style="background:rgba(239,68,68,.15);color:var(--red);border-color:rgba(239,68,68,.25)">chart.js · interaktif</div>
    </div>
    <div class="feat-card fade-up d5">
      <div class="feat-icon" style="background:rgba(139,92,246,.15);border-color:rgba(139,92,246,.3)">
        <svg viewBox="0 0 24 24" style="stroke:#A78BFA"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      </div>
      <div class="feat-title">Ekspor PDF / Excel / CSV</div>
      <div class="feat-desc">Field book PDF A4, spreadsheet 4-sheet Excel, dan CSV flat untuk AutoCAD Civil 3D — hanya tersedia saat status Diterima.</div>
      <div class="feat-tag" style="background:rgba(139,92,246,.15);color:#A78BFA;border-color:rgba(139,92,246,.25)">DomPDF · Maatwebsite</div>
    </div>
    <div class="feat-card fade-up d6">
      <div class="feat-icon" style="background:rgba(20,184,166,.15);border-color:rgba(20,184,166,.3)">
        <svg viewBox="0 0 24 24" style="stroke:#2DD4BF"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
      </div>
      <div class="feat-title">Log Aktivitas Lengkap</div>
      <div class="feat-desc">Setiap recalculation, perataan, dan ekspor dicatat di activity_logs dengan snapshot before/after untuk auditabilitas penuh.</div>
      <div class="feat-tag" style="background:rgba(20,184,166,.15);color:#2DD4BF;border-color:rgba(20,184,166,.25)">audit trail · immutable</div>
    </div>
  </div>
</section>

<!-- ── WORKFLOW + LIVE PREVIEW ── -->
<section class="workflow-section" id="alur">
  <div class="section-eyebrow fade-up">Alur Kerja</div>
  <h2 class="section-title fade-up d1">Pipeline Sipat Datar<br>End-to-End</h2>

  <div class="workflow-grid">
    <div class="workflow-steps">
      <div class="workflow-step fade-up d1">
        <div class="step-num">01</div>
        <div class="step-content">
          <h4>Input Bacaan Lapangan</h4>
          <p>Masukkan BA, BT, BB setiap titik. Validasi deviasi BT real-time — bacaan bermasalah langsung ditolak sebelum tersimpan.</p>
        </div>
      </div>
      <div class="workflow-step fade-up d2">
        <div class="step-num">02</div>
        <div class="step-content">
          <h4>Hitung Elevasi Otomatis</h4>
          <p>Observer memicu recalculation job setiap ada perubahan. HI, ΔH, dan semua elevasi dihitung ulang dengan bcmath.</p>
        </div>
      </div>
      <div class="workflow-step fade-up d3">
        <div class="step-num">03</div>
        <div class="step-content">
          <h4>Cek Kesalahan Penutup</h4>
          <p>fh dihitung dan dibandingkan toleransi r = c√d sesuai SNI. Status otomatis berubah: Dihitung → Diterima / Ditolak.</p>
        </div>
      </div>
      <div class="workflow-step fade-up d4">
        <div class="step-num">04</div>
        <div class="step-content">
          <h4>Terapkan Perataan</h4>
          <p>Pilih metode Equal atau Bowditch. Koreksi diterapkan ke adjusted_elevation. Reversible — reset kapan saja ke elevasi mentah.</p>
        </div>
      </div>
      <div class="workflow-step fade-up d5">
        <div class="step-num">05</div>
        <div class="step-content">
          <h4>Ekspor &amp; Dokumentasi</h4>
          <p>PDF field book, Excel 4-sheet, atau CSV untuk GIS. Hanya tersedia ketika status proyek = Diterima.</p>
        </div>
      </div>
    </div>

    <!-- Live table preview -->
    <div class="fade-up d2">
      <div class="chart-preview">
        <div class="scan-line"></div>
        <div class="chart-header">
          <span class="chart-title">TABEL ELEVASI — PREVIEW</span>
          <span class="chart-badge">● LIVE</span>
        </div>
        <div class="table-preview">
          <table>
            <thead>
              <tr>
                <th>Titik</th><th>Tipe</th><th>BT</th><th>HI</th><th>Elev. Tetap</th>
              </tr>
            </thead>
            <tbody>
              <tr class="bs"><td>BM-A</td><td>BS</td><td>1.2100</td><td>101.2100</td><td>100.0000</td></tr>
              <tr class="fs"><td>TP-1</td><td>FS</td><td>1.1750</td><td>—</td><td>100.0350</td></tr>
              <tr class="bs"><td>TP-1</td><td>BS</td><td>1.3050</td><td>101.3400</td><td>100.0350</td></tr>
              <tr class="fs"><td>TP-2</td><td>FS</td><td>1.0820</td><td>—</td><td>100.2580</td></tr>
              <tr class="bs"><td>TP-2</td><td>BS</td><td>1.2430</td><td>101.5010</td><td>100.2580</td></tr>
              <tr class="fs"><td>BM-B</td><td>FS</td><td>1.1000</td><td>—</td><td>100.4010</td></tr>
            </tbody>
          </table>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
          <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);border-radius:6px;padding:8px 12px;flex:1">
            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text3);margin-bottom:2px">Kesalahan Penutup</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:15px;color:var(--red);font-weight:600">fh = 0.401000 m</div>
          </div>
          <div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);border-radius:6px;padding:8px 12px;flex:1">
            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text3);margin-bottom:2px">Toleransi LA</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:15px;color:var(--emerald);font-weight:600">tol = 0.002452 m</div>
          </div>
        </div>
        <!-- Elevation chart bars -->
        <div style="margin-top:16px">
          <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text3);margin-bottom:8px;letter-spacing:.1em">PROFIL ELEVASI</div>
          <div style="display:flex;align-items:flex-end;gap:6px;height:64px" id="bars"></div>
          <div style="display:flex;gap:6px;margin-top:4px" id="barLabels"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── TOLERANCE CLASSES ── -->
<section class="section" id="standar" style="border-top:1px solid var(--border)">
  <div class="section-eyebrow fade-up">SNI 19-6988-2004</div>
  <h2 class="section-title fade-up d1">Kelas Toleransi Jaringan<br>Kontrol Vertikal</h2>
  <p class="section-sub fade-up d2">GeoLevel mendukung 4 kelas toleransi sipat datar. Formula: <span style="font-family:'JetBrains Mono',monospace;color:var(--blue2)">r = c√d</span> di mana d dalam km.</p>

  <div class="tol-grid">
    <div class="tol-card laa fade-up d1">
      <div class="tol-class">LAA</div>
      <div class="tol-val">c = 2 mm/√km</div>
      <div style="font-size:12px;color:var(--text2)">Orde tinggi — jaringan geodetik presisi sangat tinggi</div>
      <div class="tol-bar" style="width:25%"></div>
    </div>
    <div class="tol-card la fade-up d2">
      <div class="tol-class">LA</div>
      <div class="tol-val">c = 4 mm/√km</div>
      <div style="font-size:12px;color:var(--text2)">Default — cocok untuk jaringan kontrol vertikal umum</div>
      <div class="tol-bar" style="width:50%"></div>
    </div>
    <div class="tol-card lb fade-up d3">
      <div class="tol-class">LB</div>
      <div class="tol-val">c = 8 mm/√km</div>
      <div style="font-size:12px;color:var(--text2)">Kelas menengah — survei konstruksi dan irigasi</div>
      <div class="tol-bar" style="width:75%"></div>
    </div>
    <div class="tol-card lc fade-up d4">
      <div class="tol-class">LC</div>
      <div class="tol-val">c = 12 mm/√km</div>
      <div style="font-size:12px;color:var(--text2)">Kelas rendah — survei pendahuluan dan sketsa</div>
      <div class="tol-bar" style="width:100%"></div>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section">
  <div class="hero-eyebrow fade-up" style="margin:0 auto 24px">
    <span class="dot-pulse"></span>
    Siap Digunakan
  </div>
  <h2 class="cta-title fade-up d1">Mulai Survei Pertama<br>Anda Sekarang</h2>
  <p class="cta-sub fade-up d2">Ganti spreadsheet Excel dengan sistem yang tervalidasi, teraudit, dan menghasilkan field book siap cetak.</p>
  <div class="fade-up d3">
    @auth
      <a class="btn-primary" href="{{ route('projects.index') }}" style="margin:0 auto">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        Buka Dashboard
      </a>
    @else
      <button class="btn-primary" onclick="openModal()" style="margin:0 auto">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        Masuk ke GeoLevel
      </button>
    @endauth
  </div>
</section>

<!-- ── FOOTER ── -->
<footer>
  <div class="footer-grid">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <div class="nav-icon" style="transform:scale(1.2)">
          <span></span><span></span><span></span><span></span>
        </div>
        <div class="footer-brand">GeoLevel</div>
      </div>
      <p class="footer-desc">Sistem digital untuk perhitungan sipat datar waterpass — dari bacaan lapangan hingga field book terstandar SNI.</p>
      <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
        <span style="font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(37,99,235,.15);color:var(--blue2);border:1px solid rgba(37,99,235,.25);padding:3px 8px;border-radius:4px">Laravel 11</span>
        <span style="font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(16,185,129,.12);color:var(--emerald);border:1px solid rgba(16,185,129,.25);padding:3px 8px;border-radius:4px">Vue 3 + Inertia</span>
        <span style="font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(245,158,11,.12);color:var(--amber);border:1px solid rgba(245,158,11,.25);padding:3px 8px;border-radius:4px">PostgreSQL</span>
        <span style="font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(139,92,246,.12);color:#A78BFA;border:1px solid rgba(139,92,246,.25);padding:3px 8px;border-radius:4px">bcmath</span>
      </div>
    </div>
    <div class="footer-col">
      <h5>Fitur</h5>
      <a href="#fitur">Input Bacaan</a>
      <a href="#fitur">Perhitungan Elevasi</a>
      <a href="#fitur">Perataan Kesalahan</a>
      <a href="#fitur">Profil Memanjang</a>
      <a href="#fitur">Ekspor Field Book</a>
    </div>
    <div class="footer-col">
      <h5>Standar</h5>
      <a href="#standar">SNI 19-6988-2004</a>
      <a href="#standar">Kelas LAA / LA / LB / LC</a>
      <a href="#standar">Metode Bowditch</a>
      <a href="#standar">Validasi BT ≤ 0.002m</a>
    </div>
    <div class="footer-col">
      <h5>Sistem</h5>
      @auth
        <a href="{{ route('projects.index') }}">Dashboard Proyek</a>
        <a href="{{ route('projects.index') }}">Log Aktivitas</a>
        <a href="{{ route('profile.edit') }}">Manajemen Akun</a>
        <a href="{{ route('projects.index') }}">Buka Sistem</a>
      @else
        <a href="#">Dashboard Proyek</a>
        <a href="#">Log Aktivitas</a>
        <a href="#">Manajemen Akun</a>
        <a href="{{ route('login') }}">Masuk</a>
      @endauth
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">© {{ date('Y') }} GeoLevel · Sistem Survei Waterpass Digital</div>
    <div class="footer-sni">SNI 19-6988-2004 · SIPAT DATAR · BCMATH PRESISI</div>
  </div>
</footer>

<!-- ── LOGIN MODAL ── -->
{{-- Modal ini hanya ditampilkan untuk guest. User yang sudah login langsung diarahkan ke dashboard. --}}
@guest
<div class="modal-overlay" id="modal" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-header">
      <p>GeoLevel · Sistem Survei Waterpass</p>
      <h3>Masuk ke Sistem</h3>
    </div>
    <div class="modal-body">
      {{-- Form diarahkan ke route login Laravel Breeze --}}
      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-field">
          <label class="form-label" for="email">Email</label>
          <input
            id="email"
            type="email"
            name="email"
            class="form-input"
            placeholder="surveyor@geolevel.id"
            value="{{ old('email') }}"
            required
            autocomplete="email"
          />
          @error('email')
            <p style="color:var(--red);font-size:12px;margin-top:4px">{{ $message }}</p>
          @enderror
        </div>
        <div class="form-field">
          <label class="form-label" for="password">Password</label>
          <input
            id="password"
            type="password"
            name="password"
            class="form-input"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          />
          @error('password')
            <p style="color:var(--red);font-size:12px;margin-top:4px">{{ $message }}</p>
          @enderror
        </div>
        <div class="modal-btns">
          <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
          <button type="submit" class="btn-submit">Masuk →</button>
        </div>
      </form>
      @if (Route::has('password.request'))
        <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:14px">
          Lupa password?
          <a href="{{ route('password.request') }}" style="color:var(--blue2)">Reset di sini</a>
        </p>
      @endif
    </div>
  </div>
</div>
@endguest

<script>
  /* ── Modal helpers ── */
  function openModal() {
    const m = document.getElementById('modal');
    if (m) m.classList.add('open');
  }
  function closeModal() {
    const m = document.getElementById('modal');
    if (m) m.classList.remove('open');
  }

  /* ── Auto-open modal jika ada error validasi (redirect balik dari login) ── */
  @if ($errors->any())
    document.addEventListener('DOMContentLoaded', function() { openModal(); });
  @endif

  /* ── Grid canvas background ── */
  const cv = document.getElementById('gridCanvas');
  const ctx = cv.getContext('2d');
  let W, H, pts = [], svyPts = [];

  function resize() {
    W = cv.offsetWidth; H = cv.offsetHeight; cv.width = W; cv.height = H;
    pts = [];
    for (let i = 0; i < 18; i++) {
      pts.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .3, vy: (Math.random() - .5) * .3, r: Math.random() * 2 + 1 });
    }
    svyPts = [];
    const elevs = [100, 100.035, 100.258, 100.401];
    for (let i = 0; i < 4; i++) {
      svyPts.push({ x: 80 + (W - 160) * (i / 3), y: H * .65 - elevs[i] * 0.3 + 30, elev: elevs[i] });
    }
  }
  resize();
  window.addEventListener('resize', resize);

  function drawGrid() {
    ctx.clearRect(0, 0, W, H);
    const gS = 40;
    ctx.strokeStyle = 'rgba(96,165,250,0.08)'; ctx.lineWidth = .8;
    for (let x = 0; x < W; x += gS) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); }
    for (let y = 0; y < H; y += gS) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
    ctx.strokeStyle = 'rgba(37,99,235,0.25)'; ctx.lineWidth = 2;
    ctx.beginPath();
    svyPts.forEach((p, i) => i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y));
    ctx.stroke();
    svyPts.forEach(p => {
      ctx.beginPath(); ctx.arc(p.x, p.y, 5, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(37,99,235,.6)'; ctx.fill();
      ctx.strokeStyle = 'rgba(96,165,250,.8)'; ctx.lineWidth = 1.5; ctx.stroke();
      ctx.strokeStyle = 'rgba(96,165,250,.15)'; ctx.lineWidth = 1; ctx.setLineDash([3, 3]);
      ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p.x, H * .8); ctx.stroke();
      ctx.setLineDash([]);
    });
    pts.forEach(p => {
      p.x += p.vx; p.y += p.vy;
      if (p.x < 0 || p.x > W) p.vx *= -1;
      if (p.y < 0 || p.y > H) p.vy *= -1;
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(96,165,250,0.15)'; ctx.fill();
    });
    for (let i = 0; i < pts.length; i++) {
      for (let j = i + 1; j < pts.length; j++) {
        const d = Math.hypot(pts[i].x - pts[j].x, pts[i].y - pts[j].y);
        if (d < 100) {
          ctx.strokeStyle = `rgba(96,165,250,${.06 * (1 - d / 100)})`; ctx.lineWidth = .5;
          ctx.beginPath(); ctx.moveTo(pts[i].x, pts[i].y); ctx.lineTo(pts[j].x, pts[j].y); ctx.stroke();
        }
      }
    }
    requestAnimationFrame(drawGrid);
  }
  drawGrid();

  /* ── Counter animation ── */
  function animateCount(el, target, suffix, dur) {
    let start = null;
    const step = ts => {
      if (!start) start = ts;
      const prog = Math.min((ts - start) / dur, 1);
      el.textContent = Math.round(prog * target) + (suffix || '');
      if (prog < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }
  setTimeout(() => {
    animateCount(document.getElementById('c1'), 120, '', 1200);
    animateCount(document.getElementById('c2'), 4, '', 800);
    animateCount(document.getElementById('c3'), 3, '', 800);
    animateCount(document.getElementById('c4'), 100, '%', 1000);
  }, 400);

  /* ── Elevation bar chart ── */
  const elData = [
    { label: 'BM-A', val: 100.000, type: 'bs' },
    { label: 'TP-1',  val: 100.035, type: 'fs' },
    { label: 'TP-2',  val: 100.258, type: 'bs' },
    { label: 'BM-B',  val: 100.401, type: 'fs' }
  ];
  const barsEl   = document.getElementById('bars');
  const labelsEl = document.getElementById('barLabels');
  const base = 99.9, range = 0.55;
  elData.forEach((d, i) => {
    const h = Math.round(((d.val - base) / range) * 54);
    const bar = document.createElement('div');
    bar.style.cssText = `flex:1;height:${h}px;border-radius:3px 3px 0 0;background:${d.type === 'bs' ? 'rgba(96,165,250,.6)' : 'rgba(252,165,165,.6)'};position:relative;`;
    const tip = document.createElement('div');
    tip.style.cssText = 'font-family:JetBrains Mono,monospace;font-size:9px;color:rgba(255,255,255,.6);position:absolute;top:-18px;left:50%;transform:translateX(-50%);white-space:nowrap';
    tip.textContent = d.val.toFixed(3);
    bar.appendChild(tip);
    barsEl.appendChild(bar);
    const lbl = document.createElement('div');
    lbl.style.cssText = 'flex:1;font-family:JetBrains Mono,monospace;font-size:9px;color:rgba(255,255,255,.35);text-align:center';
    lbl.textContent = d.label;
    labelsEl.appendChild(lbl);
  });

  /* ── Fade-up intersection observer ── */
  const obs = new IntersectionObserver(
    entries => entries.forEach(e => { if (e.isIntersecting) e.target.style.animationPlayState = 'running'; }),
    { threshold: .15 }
  );
  document.querySelectorAll('.fade-up').forEach(el => {
    el.style.animationPlayState = 'paused';
    obs.observe(el);
  });
</script>

</body>
</html>