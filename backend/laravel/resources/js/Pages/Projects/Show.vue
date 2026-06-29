<template>
  <AppLayout :title="project.name">
    <div class="min-h-screen bg-[#F7F6F3]">

      <!-- ── Project header ──────────────────────────────────── -->
      <header class="relative overflow-hidden bg-[#1A1A2E]">
        <div class="pointer-events-none absolute inset-0 opacity-[0.06]"
             style="background-image: linear-gradient(#60A5FA 1px, transparent 1px), linear-gradient(90deg, #60A5FA 1px, transparent 1px); background-size: 32px 32px;" />

        <div class="relative px-6 pt-5 pb-4">
          <!-- Breadcrumb -->
          <Link :href="route('projects.index')"
            class="inline-flex items-center gap-1.5 font-mono text-[10px] text-blue-400 hover:text-blue-300 tracking-widest uppercase transition-colors mb-3">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Proyek
          </Link>

          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <h1 class="text-xl font-bold text-white leading-tight">{{ project.name }}</h1>
              <!-- Meta strip -->
              <div class="flex flex-wrap gap-x-5 gap-y-1 mt-2 font-mono text-[11px] text-slate-400">
                <span class="flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                  {{ project.location }}
                </span>
                <span>{{ formatDate(project.survey_date) }}</span>
                <span class="text-blue-300">BM: <strong class="text-white">{{ project.benchmark_name }}</strong> = {{ project.benchmark_elevation }} m</span>
                <span>{{ project.tolerance_class }} · {{ project.adjustment_method }}</span>
                <span v-if="project.total_distance_km">{{ project.total_distance_km }} km</span>
              </div>
            </div>

            <!-- Status badge + exports -->
            <div class="flex items-center gap-2 flex-wrap shrink-0">
              <ClosureStatusBadge
                :status="project.status"
                :closure_error="project.closure_error"
                :allowed_tolerance="project.allowed_tolerance"
              />
              <template v-if="project.status === 'accepted'">
                <ExportButton type="pdf"   :project-id="project.id" />
                <ExportButton type="excel" :project-id="project.id" />
                <ExportButton type="csv"   :project-id="project.id" />
              </template>
            </div>
          </div>
        </div>

        <!-- Tab bar (inside header, on dark bg) -->
        <nav class="px-6 flex gap-0 border-t border-white/10 mt-2">
          <button
            v-for="t in tabs"
            :key="t.id"
            @click="tab = t.id"
            :class="[
              'px-4 py-2.5 text-xs font-semibold tracking-wide border-b-2 transition-colors',
              tab === t.id
                ? 'border-blue-400 text-white'
                : 'border-transparent text-slate-500 hover:text-slate-300'
            ]"
          >{{ t.label }}</button>
        </nav>
      </header>

      <!-- ── Tab: Bacaan ──────────────────────────────────────── -->
      <div v-show="tab === 'bacaan'" class="px-6 py-5">

        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-bold text-slate-700">Bacaan Lapangan</h2>
            <p class="text-xs text-slate-400 font-mono mt-0.5">{{ readings.length }} baris · <span :class="invalidBtCount > 0 ? 'text-red-500' : 'text-emerald-500'">{{ invalidBtCount }} deviasi BT</span></p>
          </div>
          <button @click="openReadingForm()"
            class="inline-flex items-center gap-1.5 text-xs bg-[#1A1A2E] hover:bg-slate-700 text-white px-3.5 py-2 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Bacaan
          </button>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full font-mono text-xs text-slate-700">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                  <th class="px-3 py-2.5 text-right text-slate-400 font-medium">No</th>
                  <th class="px-3 py-2.5 text-left text-slate-600 font-semibold">Titik</th>
                  <th class="px-3 py-2.5 text-center text-slate-600 font-semibold">Tipe</th>
                  <th class="px-3 py-2.5 text-right text-slate-600 font-semibold">BA</th>
                  <th class="px-3 py-2.5 text-right text-slate-600 font-semibold">BT</th>
                  <th class="px-3 py-2.5 text-right text-slate-600 font-semibold">BB</th>
                  <th class="px-3 py-2.5 text-right text-slate-500 font-medium">BT Cek</th>
                  <th class="px-3 py-2.5 text-right text-slate-500 font-medium">Jarak (m)</th>
                  <th class="px-3 py-2.5 text-left text-slate-400 font-medium">Catatan</th>
                  <th class="px-3 py-2.5 w-8"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="r in readings"
                  :key="r.id"
                  :class="[
                    'group transition-colors',
                    btDeviation(r) > 0.002 ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-blue-50/40'
                  ]"
                >
                  <td class="px-3 py-1.5 text-right text-slate-400">{{ r.sequence_no }}</td>
                  <td class="px-3 py-1.5 font-semibold text-slate-800">{{ r.point_name }}</td>
                  <td class="px-3 py-1.5 text-center">
                    <span :class="typeBadge(r.reading_type)" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase">{{ r.reading_type }}</span>
                  </td>
                  <td class="px-3 py-1.5 text-right tabular-nums">{{ r.ba }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums">{{ r.bt }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums">{{ r.bb }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums" :class="btDeviation(r) > 0.002 ? 'text-red-600 font-bold' : 'text-slate-400'">
                    {{ btCheck(r) }}
                  </td>
                  <td class="px-3 py-1.5 text-right tabular-nums text-slate-500">{{ r.distance_m ?? r.distance_computed ?? '—' }}</td>
                  <td class="px-3 py-1.5 text-slate-400">{{ r.notes ?? '' }}</td>
                  <td class="px-3 py-1.5 text-right">
                    <button @click="deleteReading(r.id)"
                      class="opacity-0 group-hover:opacity-100 transition-opacity text-red-400 hover:text-red-600 w-6 h-6 flex items-center justify-center rounded">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </td>
                </tr>
                <tr v-if="readings.length === 0">
                  <td colspan="10" class="px-4 py-12 text-center text-slate-400">
                    Belum ada bacaan. Tambahkan bacaan pertama untuk memulai perhitungan.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="invalidBtCount > 0" class="mt-3 flex items-center gap-1.5 text-xs text-red-600 font-medium">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
          {{ invalidBtCount }} bacaan dengan deviasi BT &gt; 0.002 m (ditandai merah)
        </p>
      </div>

      <!-- ── Tab: Elevasi ──────────────────────────────────────── -->
      <div v-show="tab === 'elevasi'" class="px-6 py-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-bold text-slate-700">Tabel Elevasi Terkomputasi</h2>
            <p class="text-xs text-slate-400 font-mono mt-0.5">{{ elevations.length }} titik</p>
          </div>
          <div class="flex gap-2">
            <button
              v-if="project.status !== 'draft'"
              @click="showAdjustModal = true"
              class="inline-flex items-center gap-1.5 text-xs border border-slate-300 text-slate-600 px-3 py-2 rounded-lg hover:border-slate-500 hover:bg-white transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
              Perataan
            </button>
            <button @click="recalculate" :disabled="recalculating"
              class="inline-flex items-center gap-1.5 text-xs bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white px-3 py-2 rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" :class="recalculating && 'animate-spin'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              {{ recalculating ? 'Menghitung…' : 'Hitung Ulang' }}
            </button>
          </div>
        </div>

        <ElevationTable :rows="elevationRows" :loading="loadingElevations" />

        <div v-if="hasAdjustment" class="mt-4 flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
          Perataan aktif dengan metode <strong class="mx-1">{{ project.adjustment_method }}</strong>.
          <button @click="resetAdjustment" class="ml-auto underline hover:no-underline font-medium">Reset</button>
        </div>
      </div>

      <!-- ── Tab: Jaring (Loop Network Least Squares) ───────────── -->
      <div v-show="tab === 'jaring'" class="px-6 py-5">

        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-bold text-slate-700">Jaring Sipat Datar (Redundant)</h2>
            <p class="text-xs text-slate-400 font-mono mt-0.5">
              {{ networkLegs.length }} jalur
              <span v-if="hasRedundancy" class="text-emerald-500">· siap diratakan (kuadrat terkecil)</span>
              <span v-else class="text-amber-500">· belum ada observasi berlebih</span>
            </p>
          </div>
          <div class="flex gap-2">
            <button
              v-if="hasRedundancy"
              @click="runLeastSquares"
              :disabled="adjustingNetwork"
              class="inline-flex items-center gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white px-3.5 py-2 rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" :class="adjustingNetwork && 'animate-spin'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              {{ adjustingNetwork ? 'Meratakan…' : 'Jalankan Perataan' }}
            </button>
            <button @click="showLegForm = true"
              class="inline-flex items-center gap-1.5 text-xs bg-[#1A1A2E] hover:bg-slate-700 text-white px-3.5 py-2 rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
              Tambah Jalur
            </button>
          </div>
        </div>

        <!-- Info box: belum redundant -->
        <div v-if="!hasRedundancy && networkLegs.length > 0" class="mb-4 flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
          <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
          <span>Jaring belum punya observasi berlebih (redundant). Tambahkan minimal satu jalur tambahan yang membentuk loop tertutup — misalnya jalur silang antar titik yang sudah ada — sebelum perataan kuadrat terkecil bisa dijalankan.</span>
        </div>

        <!-- Network stats (setelah adjustment) -->
        <div v-if="project.network_std_deviation != null" class="mb-4 grid grid-cols-3 gap-3">
          <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm">
            <p class="text-[10px] text-slate-400 font-mono uppercase tracking-wide">Standar Deviasi (σ₀)</p>
            <p class="text-sm font-bold text-slate-800 font-mono mt-1">{{ fmtNum(project.network_std_deviation, 6) }} m</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm">
            <p class="text-[10px] text-slate-400 font-mono uppercase tracking-wide">Variansi (σ₀²)</p>
            <p class="text-sm font-bold text-slate-800 font-mono mt-1">{{ fmtNum(project.network_variance, 8) }}</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm">
            <p class="text-[10px] text-slate-400 font-mono uppercase tracking-wide">Derajat Kebebasan</p>
            <p class="text-sm font-bold text-slate-800 font-mono mt-1">{{ project.network_degrees_of_freedom }}</p>
          </div>
        </div>

        <!-- Legs table -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full font-mono text-xs text-slate-700">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                  <th class="px-3 py-2.5 text-left text-slate-600 font-semibold">Dari</th>
                  <th class="px-3 py-2.5 text-left text-slate-600 font-semibold">Ke</th>
                  <th class="px-3 py-2.5 text-right text-slate-600 font-semibold">ΔH Ukur</th>
                  <th class="px-3 py-2.5 text-right text-slate-500 font-medium">Jarak (m)</th>
                  <th class="px-3 py-2.5 text-right text-slate-600 font-semibold">ΔH Terkoreksi</th>
                  <th class="px-3 py-2.5 text-right text-slate-500 font-medium">Residual</th>
                  <th class="px-3 py-2.5 w-8"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="leg in networkLegs" :key="leg.id" class="group hover:bg-emerald-50/40 transition-colors">
                  <td class="px-3 py-1.5 font-semibold text-slate-800">{{ leg.from_point }}</td>
                  <td class="px-3 py-1.5 font-semibold text-slate-800">{{ leg.to_point }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums">{{ leg.observed_delta_h }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums text-slate-500">{{ leg.distance_m }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums" :class="leg.corrected_delta_h != null ? 'text-emerald-600 font-bold' : 'text-slate-300'">
                    {{ leg.corrected_delta_h ?? '—' }}
                  </td>
                  <td class="px-3 py-1.5 text-right tabular-nums text-slate-400">{{ leg.residual ?? '—' }}</td>
                  <td class="px-3 py-1.5 text-right">
                    <button @click="deleteLeg(leg.id)"
                      class="opacity-0 group-hover:opacity-100 transition-opacity text-red-400 hover:text-red-600 w-6 h-6 flex items-center justify-center rounded">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </td>
                </tr>
                <tr v-if="networkLegs.length === 0">
                  <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                    Belum ada jalur jaring. Tambahkan jalur untuk membentuk loop dengan observasi berlebih.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── Tab: Grafik ──────────────────────────────────────── -->
      <div v-show="tab === 'grafik'" class="px-6 py-5 space-y-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-slate-700">Profil Memanjang</h2>
            <button @click="fetchLongSection" class="text-xs text-blue-600 hover:underline">Muat ulang</button>
          </div>
          <LongSectionChart :dataset="longSectionData" :title="project.name" />
        </div>

        <div v-if="crossStations.length" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-slate-700">Profil Melintang</h2>
            <select v-model="selectedStation" @change="fetchCrossSection"
              class="text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
              <option v-for="s in crossStations" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <CrossSectionChart v-if="crossSectionData" :station="crossSectionData.station" :offsets="crossSectionData.offsets" />
        </div>
      </div>

      <!-- ── Tab: Aktivitas ──────────────────────────────────── -->
      <div v-show="tab === 'aktivitas'" class="px-6 py-5">
        <h2 class="text-sm font-bold text-slate-700 mb-4">Log Aktivitas</h2>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-100">
          <div v-for="log in activityLogs" :key="log.id" class="flex gap-4 px-4 py-3 text-xs">
            <span class="font-mono text-slate-400 shrink-0 tabular-nums pt-0.5">{{ formatDatetime(log.created_at) }}</span>
            <div>
              <span class="font-semibold text-slate-700 capitalize">{{ log.activity_type.replace(/_/g, ' ') }}</span>
              <span class="text-slate-500 ml-1.5">{{ log.description }}</span>
            </div>
          </div>
          <div v-if="!activityLogs.length" class="px-4 py-10 text-center text-slate-400 text-xs">
            Belum ada aktivitas tercatat.
          </div>
        </div>
      </div>

    </div>

    <!-- ── Add Reading Modal ─────────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                  leave-active-class="transition duration-100 ease-in"  leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="showReadingForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-[#1A1A2E] px-6 py-4 flex items-center justify-between">
              <div>
                <p class="font-mono text-[10px] text-blue-400 tracking-widest uppercase">Input</p>
                <h2 class="font-bold text-white">Tambah Bacaan</h2>
              </div>
              <button @click="closeReadingForm()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors text-xl">×</button>
            </div>

            <form @submit.prevent="submitReading" class="px-6 py-5 space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <Field label="Nama Titik" required>
                  <input v-model="readingForm.point_name" type="text" v-bind="fa" placeholder="BM-A / TP-1"
                    :class="readingErrors?.point_name ? 'border-red-400 focus:ring-red-400' : ''" />
                  <p v-if="readingErrors?.point_name" class="mt-1 text-xs text-red-600">
                    {{ readingErrors.point_name[0] }}
                  </p>
                </Field>
                <Field label="Tipe Bacaan" required>
                  <select v-model="readingForm.reading_type" v-bind="fa">
                    <option value="BS">BS — Bacaan Belakang</option>
                    <option value="IS">IS — Bacaan Antara</option>
                    <option value="FS">FS — Bacaan Muka</option>
                  </select>
                </Field>
              </div>

              <!-- BA / BT / BB -->
              <div class="grid grid-cols-3 gap-3">
                <Field label="BA" required>
                  <input v-model="readingForm.ba" type="number" step="0.0001" v-bind="fa" placeholder="1.5230" class="font-mono"
                    :class="readingErrors?.ba ? 'border-red-400 focus:ring-red-400' : ''" />
                  <p v-if="readingErrors?.ba" class="mt-1 text-xs text-red-600">{{ readingErrors.ba[0] }}</p>
                </Field>
                <Field label="BT" required>
                  <input v-model="readingForm.bt" type="number" step="0.0001" v-bind="fa" placeholder="1.2100" class="font-mono"
                    :class="readingErrors?.bt ? 'border-red-400 focus:ring-red-400' : ''" />
                  <p v-if="readingErrors?.bt" class="mt-1 text-xs text-red-600">{{ readingErrors.bt[0] }}</p>
                </Field>
                <Field label="BB" required>
                  <input v-model="readingForm.bb" type="number" step="0.0001" v-bind="fa" placeholder="0.8970" class="font-mono"
                    :class="readingErrors?.bb ? 'border-red-400 focus:ring-red-400' : ''" />
                  <p v-if="readingErrors?.bb" class="mt-1 text-xs text-red-600">{{ readingErrors.bb[0] }}</p>
                </Field>
              </div>

              <!-- Live BT check -->
              <div v-if="readingForm.ba && readingForm.bb"
                class="flex items-center gap-2 rounded-lg px-3 py-2 font-mono text-xs border"
                :class="liveBtOk ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'">
                <svg v-if="liveBtOk" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                BT hitung = <strong class="mx-1">{{ liveBtComputed }}</strong>
                · Deviasi = <strong class="mx-1">{{ liveBtDev }}</strong>
                {{ liveBtOk ? '✓ Valid' : '✗ Melebihi 0.002 m' }}
              </div>

              <Field label="Jarak Manual (m)">
                <input v-model="readingForm.distance_m" type="number" step="0.001" v-bind="fa" placeholder="Otomatis dari (BA − BB) × 100" />
              </Field>
              <Field label="Catatan">
                <input v-model="readingForm.notes" type="text" v-bind="fa" placeholder="Opsional" />
              </Field>



              <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="closeReadingForm()" class="text-sm text-slate-500 hover:text-slate-700 px-4 py-2">Batal</button>
                <button type="submit" :disabled="submittingReading"
                  class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                  {{ submittingReading ? 'Menyimpan…' : 'Simpan Bacaan' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Adjustment Modal ──────────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                  leave-active-class="transition duration-100 ease-in"  leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="showAdjustModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="bg-amber-500 px-6 py-4 flex items-center justify-between">
              <div>
                <p class="font-mono text-[10px] text-amber-100 tracking-widest uppercase mb-0.5">Perataan</p>
                <h2 class="font-bold text-white">Perataan Kesalahan</h2>
              </div>
              <button @click="showAdjustModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-amber-200 hover:text-white hover:bg-white/10 transition-colors text-xl">×</button>
            </div>
            <div class="px-6 py-5 space-y-4">
              <Field label="Metode Perataan">
                <select v-model="adjustMethod" v-bind="fa">
                  <option value="equal">Equal Distribution (distribusi merata)</option>
                  <option value="bowditch">Bowditch (proporsional jarak)</option>
                </select>
              </Field>
              <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 font-mono text-xs space-y-1.5 text-slate-600">
                <div class="flex justify-between"><span>fh</span><strong>{{ fmtNum(project.closure_error, 6) }} m</strong></div>
                <div class="flex justify-between"><span>Toleransi</span><strong>{{ fmtNum(project.allowed_tolerance, 6) }} m</strong></div>
                <div class="flex justify-between border-t border-slate-200 pt-1.5 mt-1.5">
                  <span>Status</span>
                  <strong :class="project.status === 'accepted' ? 'text-emerald-600' : 'text-red-500'">{{ project.status.toUpperCase() }}</strong>
                </div>
              </div>
              <div class="flex justify-end gap-3">
                <button @click="showAdjustModal = false" class="text-sm text-slate-500 px-4 py-2">Batal</button>
                <button @click="applyAdjustment" :disabled="adjusting"
                  class="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                  {{ adjusting ? 'Menerapkan…' : 'Terapkan Perataan' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Network Leg Modal ─────────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                  leave-active-class="transition duration-100 ease-in"  leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="showLegForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between">
              <div>
                <p class="font-mono text-[10px] text-emerald-100 tracking-widest uppercase mb-0.5">Jaring</p>
                <h2 class="font-bold text-white">Tambah Jalur</h2>
              </div>
              <button @click="showLegForm = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-emerald-200 hover:text-white hover:bg-white/10 transition-colors text-xl">×</button>
            </div>

            <form @submit.prevent="submitLeg" class="px-6 py-5 space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <Field label="Dari Titik" required>
                  <select v-model="legForm.from_point" v-bind="fa">
                    <option value="" disabled>-- Pilih titik --</option>
                    <option v-for="p in uniquePointNames" :key="p" :value="p">{{ p }}</option>
                  </select>
                </Field>
                <Field label="Ke Titik" required>
                  <select v-model="legForm.to_point" v-bind="fa">
                    <option value="" disabled>-- Pilih titik --</option>
                    <option v-for="p in uniquePointNames" :key="p" :value="p">{{ p }}</option>
                  </select>
                </Field>
              </div>

              <Field label="Beda Tinggi Terukur — ΔH (m)" required>
                <input v-model="legForm.observed_delta_h" type="number" step="0.000001" v-bind="fa" placeholder="1.234500" class="font-mono" />
              </Field>

              <Field label="Jarak (m)" required>
                <input v-model="legForm.distance_m" type="number" step="0.001" v-bind="fa" placeholder="150.500" class="font-mono" />
              </Field>

              <Field label="Catatan">
                <input v-model="legForm.notes" type="text" v-bind="fa" placeholder="Opsional" />
              </Field>

              <div v-if="legErrors" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 space-y-1">
                <p v-for="(msgs, field) in legErrors" :key="field" class="text-xs text-red-700">{{ field }}: {{ msgs[0] }}</p>
              </div>

              <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="showLegForm = false" class="text-sm text-slate-500 hover:text-slate-700 px-4 py-2">Batal</button>
                <button type="submit" :disabled="submittingLeg"
                  class="bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                  {{ submittingLeg ? 'Menyimpan…' : 'Simpan Jalur' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import ElevationTable from '@/Components/ElevationTable.vue'
import ClosureStatusBadge from '@/Components/ClosureStatusBadge.vue'
import LongSectionChart from '@/Components/LongSectionChart.vue'
import CrossSectionChart from '@/Components/CrossSectionChart.vue'
import ExportButton from '@/Components/ExportButton.vue'
import Field from '@/Components/Field.vue'

// STEP 4 — props dengan tambahan networkLegs
const props = defineProps({
  project:      { type: Object, required: true },
  readings:     { type: Array,  default: () => [] },
  elevations:   { type: Array,  default: () => [] },
  activityLogs: { type: Array,  default: () => [] },
  networkLegs:  { type: Array,  default: () => [] },
})

// ── Tabs — STEP 1: tambah tab 'jaring' ───────────────────────
const tabs = [
  { id: 'bacaan',    label: 'Bacaan' },
  { id: 'elevasi',   label: 'Elevasi' },
  { id: 'jaring',    label: 'Jaring' },
  { id: 'grafik',    label: 'Grafik' },
  { id: 'aktivitas', label: 'Aktivitas' },
]
const tab = ref('bacaan')

// ── Elevation rows ───────────────────────────────────────────
const loadingElevations = ref(false)
const elevationRows = computed(() => {
  const readingMap = Object.fromEntries(props.readings.map(r => [r.id, r]))
  return props.elevations.map((e, idx) => {
    const r = readingMap[e.reading_id] ?? {}
    const prev = props.elevations[idx - 1]
    return {
      sequence_no: e.sequence_no, point_name: e.point_name,
      distance_m: r.distance_m ?? null,
      ba: Number(r.ba ?? 0), bt: Number(r.bt ?? 0), bb: Number(r.bb ?? 0),
      reading_type: r.reading_type ?? 'BS',
      hi: e.hi != null ? Number(e.hi) : null,
      delta_h: prev ? Number(e.raw_elevation) - Number(prev.raw_elevation) : null,
      raw_elevation: Number(e.raw_elevation),
      correction: Number(e.correction),
      adjusted_elevation: Number(e.adjusted_elevation),
    }
  })
})

const hasAdjustment = computed(() => props.elevations.some(e => Number(e.correction) !== 0))

// ── Recalculate ──────────────────────────────────────────────
const recalculating = ref(false)
function recalculate() {
  recalculating.value = true
  router.post(route('projects.calculate', props.project.id), {}, {
    onFinish: () => { recalculating.value = false },
    preserveScroll: true,
  })
}

// ── Adjustment ───────────────────────────────────────────────
const showAdjustModal = ref(false)
const adjustMethod    = ref(props.project.adjustment_method ?? 'equal')
const adjusting       = ref(false)

function applyAdjustment() {
  adjusting.value = true
  router.post(route('projects.adjust', props.project.id), { method: adjustMethod.value }, {
    onSuccess: () => { showAdjustModal.value = false },
    onFinish:  () => { adjusting.value = false },
    preserveScroll: true,
  })
}

function resetAdjustment() {
  if (!confirm('Reset semua koreksi ke 0?')) return
  router.post(route('projects.adjust.reset', props.project.id), {}, { preserveScroll: true })
}

// ── Reading form ─────────────────────────────────────────────
const showReadingForm   = ref(false)
const submittingReading = ref(false)
const readingErrors     = ref(null)
const blankReading = () => ({ point_name: '', reading_type: 'BS', ba: '', bt: '', bb: '', distance_m: '', notes: '' })
const readingForm = ref(blankReading())

function openReadingForm() {
  readingForm.value   = blankReading()
  readingErrors.value = null
  showReadingForm.value = true
}
function closeReadingForm() {
  showReadingForm.value = false
  readingErrors.value   = null
}

const fa = { class: 'w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-400 bg-slate-50 placeholder-slate-400 transition-colors' }

function submitReading() {
  // Validasi frontend — tidak bergantung pada liveBtOk computed
  // agar guard selalu reliable terlepas dari urutan definisi
  const ferr = {}
  if (!readingForm.value.point_name.trim()) ferr.point_name = ['Nama titik wajib diisi']

  const ba = parseFloat(readingForm.value.ba)
  const bt = parseFloat(readingForm.value.bt)
  const bb = parseFloat(readingForm.value.bb)

  if (isNaN(ba)) ferr.ba = ['BA wajib diisi']
  if (isNaN(bt)) ferr.bt = ['BT wajib diisi']
  if (isNaN(bb)) ferr.bb = ['BB wajib diisi']

  // Cek deviasi BT langsung dari nilai form — tidak pakai liveBtOk.value
  // agar tidak ada risiko computed belum terupdate saat tombol diklik
  if (!isNaN(ba) && !isNaN(bt) && !isNaN(bb)) {
    const btHitung = (ba + bb) / 2
    const deviasi  = Math.abs(bt - btHitung)
    if (deviasi > 0.002) {
      ferr.bt = ['Deviasi BT (' + deviasi.toFixed(4) + ' m) melebihi batas 0.002 m. BT lapangan harus mendekati (BA+BB)/2 = ' + btHitung.toFixed(4) + ' m']
    }
  }

  if (Object.keys(ferr).length) {
    readingErrors.value = ferr
    return  // form tetap terbuka, tidak ada request ke server
  }

  submittingReading.value = true
  readingErrors.value = null

  const payload = {
    ...readingForm.value,
    distance_m: readingForm.value.distance_m !== '' ? readingForm.value.distance_m : null,
    notes:      readingForm.value.notes !== ''      ? readingForm.value.notes      : null,
  }

  router.post(route('readings.store', { project: props.project.id }), payload, {
    onSuccess: () => {
      showReadingForm.value = false
      readingForm.value     = blankReading()
      readingErrors.value   = null
    },
    onError: (e) => {
      readingErrors.value = e
    },
    onFinish:  () => { submittingReading.value = false },
    preserveScroll: true,
    preserveState:  true,
  })
}

function deleteReading(id) {
  if (!confirm('Hapus bacaan ini?')) return
  router.delete(route('readings.destroy', { project: props.project.id, reading: id }), {
    preserveScroll: true,
    preserveState:  false,
  })
}

// ── Live BT ──────────────────────────────────────────────────
const liveBtComputed = computed(() => {
  const ba = parseFloat(readingForm.value.ba), bb = parseFloat(readingForm.value.bb)
  return (!isNaN(ba) && !isNaN(bb)) ? ((ba + bb) / 2).toFixed(4) : '—'
})
const liveBtDev = computed(() => {
  const bt = parseFloat(readingForm.value.bt), c = parseFloat(liveBtComputed.value)
  return (!isNaN(bt) && !isNaN(c)) ? Math.abs(bt - c).toFixed(4) : '—'
})
const liveBtOk = computed(() => parseFloat(liveBtDev.value) <= 0.002)

// ── Charts ───────────────────────────────────────────────────
const longSectionData  = ref([])
const crossSectionData = ref(null)
const crossStations    = ref([])
const selectedStation  = ref('')

async function fetchLongSection() {
  try { const { data } = await axios.get(route('chart.longsection', props.project.id)); longSectionData.value = data.data } catch (e) {}
}
async function fetchCrossSection() {
  if (!selectedStation.value) return
  try {
    const { data } = await axios.get(route('chart.crosssection', props.project.id), { params: { station: selectedStation.value } })
    crossSectionData.value = data.data; crossStations.value = data.stations
  } catch (e) {}
}

onMounted(async () => {
  await fetchLongSection()
  try {
    const { data } = await axios.get(route('chart.crosssection', props.project.id))
    crossStations.value = data.stations; crossSectionData.value = data.data
    selectedStation.value = crossStations.value[0] ?? ''
  } catch (e) {}
})

// ── Reading helpers ──────────────────────────────────────────
function btCheck(r)     { return ((parseFloat(r.ba) + parseFloat(r.bb)) / 2).toFixed(4) }
function btDeviation(r) { return Math.abs(parseFloat(r.bt) - ((parseFloat(r.ba) + parseFloat(r.bb)) / 2)) }
const invalidBtCount = computed(() => props.readings.filter(r => btDeviation(r) > 0.002).length)

function typeBadge(type) {
  return { BS: 'bg-blue-100 text-blue-700', IS: 'bg-amber-100 text-amber-700', FS: 'bg-rose-100 text-rose-700' }[type] ?? 'bg-slate-100 text-slate-600'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
function formatDatetime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}
function fmtNum(v, d = 4) { return v != null ? Number(v).toFixed(d) : '—' }

// ── Network Legs (Loop Network Least Squares) — STEP 5 ────────
const networkLegs = ref(props.networkLegs)

const hasRedundancy = computed(() => {
  if (networkLegs.value.length < 2) return false
  const points = new Set()
  networkLegs.value.forEach(l => { points.add(l.from_point); points.add(l.to_point) })
  return networkLegs.value.length >= points.size
})

// Daftar nama titik unik dari readings — dipakai sebagai opsi dropdown
// form Tambah Jalur agar user tidak perlu mengetik manual
const uniquePointNames = computed(() => {
  const names = props.readings.map(r => r.point_name).filter(Boolean)
  return [...new Set(names)]
})

const showLegForm   = ref(false)
const submittingLeg = ref(false)
const legErrors     = ref(null)
const blankLeg = () => ({ from_point: '', to_point: '', observed_delta_h: '', distance_m: '', notes: '' })
const legForm = ref(blankLeg())

function submitLeg() {
  submittingLeg.value = true; legErrors.value = null
  router.post(route('network-legs.store', props.project.id), legForm.value, {
    onSuccess: () => {
      showLegForm.value = false
      legForm.value = blankLeg()
      refreshNetworkLegs()
    },
    onError:   (e) => { legErrors.value = e },
    onFinish:  () => { submittingLeg.value = false },
    preserveScroll: true,
  })
}

function deleteLeg(id) {
  if (!confirm('Hapus jalur ini?')) return
  router.delete(route('network-legs.destroy', { project: props.project.id, leg: id }), {
    preserveScroll: true,
    onSuccess: () => refreshNetworkLegs(),
  })
}

const adjustingNetwork = ref(false)
function runLeastSquares() {
  adjustingNetwork.value = true
  router.post(route('network-legs.adjust', props.project.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      adjustingNetwork.value = false
      refreshNetworkLegs()
    },
  })
}

async function refreshNetworkLegs() {
  try {
    const { data } = await axios.get(route('network-legs.index', props.project.id))
    networkLegs.value = data.legs
  } catch (e) {}
}
</script>