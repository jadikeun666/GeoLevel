<template>
  <AppLayout :title="project.name">
    <div class="min-h-screen bg-stone-50 font-mono">

      <!-- ── Top bar ─────────────────────────────────────────── -->
      <header class="border-b border-stone-200 bg-white px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Link :href="route('projects.index')" class="text-stone-400 hover:text-stone-700 text-sm">
              ← Proyek
            </Link>
            <span class="text-stone-200">/</span>
            <h1 class="text-base font-bold text-stone-800 truncate max-w-sm">{{ project.name }}</h1>
          </div>
          <div class="flex items-center gap-2">
            <ClosureStatusBadge
              :status="project.status"
              :closure_error="project.closure_error"
              :allowed_tolerance="project.allowed_tolerance"
            />
            <!-- Export buttons — only when accepted -->
            <template v-if="project.status === 'accepted'">
              <ExportButton type="pdf"   :project-id="project.id" />
              <ExportButton type="excel" :project-id="project.id" />
              <ExportButton type="csv"   :project-id="project.id" />
            </template>
          </div>
        </div>

        <!-- Project meta strip -->
        <div class="mt-3 flex gap-6 text-xs text-stone-500 flex-wrap">
          <span>📍 {{ project.location }}</span>
          <span>📅 {{ formatDate(project.survey_date) }}</span>
          <span>🔖 BM: <strong class="text-stone-700">{{ project.benchmark_name }}</strong> = {{ project.benchmark_elevation }} m</span>
          <span>📐 {{ project.tolerance_class }} · {{ project.adjustment_method }}</span>
          <span v-if="project.total_distance_km">📏 {{ project.total_distance_km }} km</span>
        </div>
      </header>

      <!-- ── Tab bar ────────────────────────────────────────── -->
      <nav class="border-b border-stone-200 bg-white px-6 flex gap-0">
        <TabBtn v-for="t in tabs" :key="t.id" :active="tab === t.id" @click="tab = t.id">
          {{ t.label }}
        </TabBtn>
      </nav>

      <!-- ── Tab: Bacaan ────────────────────────────────────── -->
      <div v-show="tab === 'bacaan'" class="px-6 py-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-bold text-stone-700 uppercase tracking-wider">Bacaan Lapangan</h2>
          <button
            @click="showReadingForm = true"
            class="text-xs bg-stone-800 text-white px-3 py-1.5 rounded hover:bg-stone-700 transition-colors"
          >
            + Tambah Bacaan
          </button>
        </div>

        <!-- Reading table (raw) -->
        <div class="overflow-x-auto rounded-lg border border-stone-200">
          <table class="min-w-full text-xs text-stone-700">
            <thead class="bg-stone-100 text-stone-500 uppercase tracking-wider">
              <tr>
                <th class="px-3 py-2 text-right">No</th>
                <th class="px-3 py-2 text-left">Titik</th>
                <th class="px-3 py-2 text-center">Tipe</th>
                <th class="px-3 py-2 text-right">BA</th>
                <th class="px-3 py-2 text-right">BT</th>
                <th class="px-3 py-2 text-right">BB</th>
                <th class="px-3 py-2 text-right">BT Cek</th>
                <th class="px-3 py-2 text-right">Jarak (m)</th>
                <th class="px-3 py-2 text-left">Catatan</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              <tr
                v-for="r in readings"
                :key="r.id"
                class="hover:bg-stone-50 group"
                :class="{ 'bg-red-50': btDeviation(r) > 0.002 }"
              >
                <td class="px-3 py-1.5 text-right text-stone-400">{{ r.sequence_no }}</td>
                <td class="px-3 py-1.5 font-semibold">{{ r.point_name }}</td>
                <td class="px-3 py-1.5 text-center">
                  <span :class="typeBadge(r.reading_type)" class="px-2 py-0.5 rounded text-[10px] font-bold">
                    {{ r.reading_type }}
                  </span>
                </td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ r.ba }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ r.bt }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ r.bb }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums" :class="btDeviation(r) > 0.002 ? 'text-red-600 font-bold' : 'text-stone-400'">
                  {{ btCheck(r) }}
                </td>
                <td class="px-3 py-1.5 text-right tabular-nums text-stone-500">
                  {{ r.distance_m ?? r.distance_computed ?? '—' }}
                </td>
                <td class="px-3 py-1.5 text-stone-400 text-[11px]">{{ r.notes ?? '' }}</td>
                <td class="px-3 py-1.5 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    @click="deleteReading(r.id)"
                    class="text-red-400 hover:text-red-600 text-xs px-1"
                    title="Hapus bacaan"
                  >✕</button>
                </td>
              </tr>
              <tr v-if="readings.length === 0">
                <td colspan="10" class="px-4 py-10 text-center text-stone-400">
                  Belum ada bacaan. Tambahkan bacaan pertama.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- BT validation warning -->
        <p v-if="invalidBtCount > 0" class="mt-3 text-xs text-red-600 font-medium">
          ⚠ {{ invalidBtCount }} bacaan memiliki deviasi BT > 0.002 m (ditandai merah).
        </p>
      </div>

      <!-- ── Tab: Elevasi ───────────────────────────────────── -->
      <div v-show="tab === 'elevasi'" class="px-6 py-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-bold text-stone-700 uppercase tracking-wider">Tabel Elevasi Terkomputasi</h2>
          <div class="flex gap-2">
            <button
              v-if="project.status !== 'draft'"
              @click="showAdjustModal = true"
              class="text-xs border border-stone-300 text-stone-600 px-3 py-1.5 rounded hover:border-stone-500 transition-colors"
            >
              ⚖ Perataan
            </button>
            <button
              @click="recalculate"
              :disabled="recalculating"
              class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-500 disabled:opacity-50 transition-colors"
            >
              {{ recalculating ? 'Menghitung…' : '⟳ Hitung Ulang' }}
            </button>
          </div>
        </div>

        <ElevationTable :rows="elevationRows" :loading="loadingElevations" />

        <!-- Adjustment summary -->
        <div v-if="hasAdjustment" class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
          Perataan diterapkan dengan metode <strong>{{ project.adjustment_method }}</strong>.
          <button @click="resetAdjustment" class="ml-3 underline hover:no-underline">Reset ke elevasi mentah</button>
        </div>
      </div>

      <!-- ── Tab: Grafik ────────────────────────────────────── -->
      <div v-show="tab === 'grafik'" class="px-6 py-5 space-y-6">
        <div class="bg-white border border-stone-200 rounded-xl p-5">
          <h2 class="text-sm font-bold text-stone-700 uppercase tracking-wider mb-4">Profil Memanjang</h2>
          <LongSectionChart :dataset="longSectionData" :title="project.name" />
          <button @click="fetchLongSection" class="mt-3 text-xs text-blue-600 hover:underline">
            Muat ulang data
          </button>
        </div>

        <div v-if="crossStations.length" class="bg-white border border-stone-200 rounded-xl p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-stone-700 uppercase tracking-wider">Profil Melintang</h2>
            <select
              v-model="selectedStation"
              @change="fetchCrossSection"
              class="text-xs border border-stone-200 rounded px-2 py-1 bg-stone-50"
            >
              <option v-for="s in crossStations" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <CrossSectionChart
            v-if="crossSectionData"
            :station="crossSectionData.station"
            :offsets="crossSectionData.offsets"
          />
        </div>
      </div>

      <!-- ── Tab: Aktivitas ─────────────────────────────────── -->
      <div v-show="tab === 'aktivitas'" class="px-6 py-5">
        <h2 class="text-sm font-bold text-stone-700 uppercase tracking-wider mb-4">Log Aktivitas</h2>
        <div class="space-y-2">
          <div
            v-for="log in activityLogs"
            :key="log.id"
            class="flex gap-4 text-xs border-l-2 border-stone-200 pl-4 py-1"
          >
            <span class="text-stone-400 shrink-0 tabular-nums">{{ formatDatetime(log.created_at) }}</span>
            <span class="text-stone-600">
              <strong class="text-stone-800">{{ log.activity_type.replace(/_/g, ' ') }}</strong>
              — {{ log.description }}
            </span>
          </div>
          <p v-if="!activityLogs.length" class="text-stone-400 text-xs">Belum ada aktivitas tercatat.</p>
        </div>
      </div>

    </div><!-- end min-h-screen -->

    <!-- ── Add Reading Modal ──────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showReadingForm"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="showReadingForm = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
          <div class="border-b border-stone-100 px-6 py-4 flex items-center justify-between">
            <h2 class="font-bold text-stone-800">Tambah Bacaan</h2>
            <button @click="showReadingForm = false" class="text-stone-400 hover:text-stone-600 text-xl">×</button>
          </div>

          <form @submit.prevent="submitReading" class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <Field label="Nama Titik" required>
                <input v-model="readingForm.point_name" type="text" v-bind="fa" placeholder="BM-A / TP-1" />
              </Field>
              <Field label="Tipe Bacaan" required>
                <select v-model="readingForm.reading_type" v-bind="fa">
                  <option value="BS">BS — Bacaan Belakang</option>
                  <option value="IS">IS — Bacaan Antara</option>
                  <option value="FS">FS — Bacaan Muka</option>
                </select>
              </Field>
            </div>
            <div class="grid grid-cols-3 gap-3">
              <Field label="BA" required>
                <input v-model="readingForm.ba" type="number" step="0.0001" v-bind="fa" placeholder="1.5230" />
              </Field>
              <Field label="BT" required>
                <input v-model="readingForm.bt" type="number" step="0.0001" v-bind="fa" placeholder="1.2100" />
              </Field>
              <Field label="BB" required>
                <input v-model="readingForm.bb" type="number" step="0.0001" v-bind="fa" placeholder="0.8970" />
              </Field>
            </div>

            <!-- Live BT check -->
            <div v-if="readingForm.ba && readingForm.bb" class="text-xs px-3 py-2 rounded" :class="liveBtOk ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
              BT hitung = <strong>{{ liveBtComputed }}</strong>
              · Deviasi = <strong>{{ liveBtDev }}</strong>
              {{ liveBtOk ? '✓ Valid' : '✗ Melebihi 0.002 m!' }}
            </div>

            <Field label="Jarak Manual (m)">
              <input v-model="readingForm.distance_m" type="number" step="0.001" v-bind="fa" placeholder="Opsional — otomatis dari BA−BB×100" />
            </Field>
            <Field label="Catatan">
              <input v-model="readingForm.notes" type="text" v-bind="fa" placeholder="Opsional" />
            </Field>

            <div v-if="readingErrors" class="text-xs text-red-600 space-y-1">
              <p v-for="(msgs, field) in readingErrors" :key="field">{{ field }}: {{ msgs[0] }}</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showReadingForm = false" class="text-sm text-stone-500 px-4 py-2">Batal</button>
              <button
                type="submit"
                :disabled="submittingReading"
                class="bg-stone-800 hover:bg-stone-700 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors"
              >
                {{ submittingReading ? 'Menyimpan…' : 'Simpan Bacaan' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── Adjustment Modal ───────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showAdjustModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="showAdjustModal = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
          <div class="border-b border-stone-100 px-6 py-4 flex items-center justify-between">
            <h2 class="font-bold text-stone-800">Perataan Kesalahan</h2>
            <button @click="showAdjustModal = false" class="text-stone-400 hover:text-stone-600 text-xl">×</button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <Field label="Metode Perataan">
              <select v-model="adjustMethod" v-bind="fa">
                <option value="equal">Equal Distribution (distribusi merata)</option>
                <option value="bowditch">Bowditch (proporsional jarak)</option>
              </select>
            </Field>
            <div class="bg-stone-50 rounded-lg p-3 text-xs text-stone-600 space-y-1">
              <div>fh = <strong>{{ fmtNum(project.closure_error, 6) }} m</strong></div>
              <div>Toleransi = <strong>{{ fmtNum(project.allowed_tolerance, 6) }} m</strong></div>
              <div>Status: <strong :class="project.status === 'accepted' ? 'text-green-700' : 'text-red-600'">{{ project.status.toUpperCase() }}</strong></div>
            </div>
            <div class="flex justify-end gap-3">
              <button @click="showAdjustModal = false" class="text-sm text-stone-500 px-4 py-2">Batal</button>
              <button
                @click="applyAdjustment"
                :disabled="adjusting"
                class="bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors"
              >
                {{ adjusting ? 'Menerapkan…' : 'Terapkan Perataan' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import ElevationTable from '@/Components/ElevationTable.vue'
import ClosureStatusBadge from '@/Components/ClosureStatusBadge.vue'
import LongSectionChart from '@/Components/LongSectionChart.vue'
import CrossSectionChart from '@/Components/CrossSectionChart.vue'
import ExportButton from '@/Components/ExportButton.vue'
import StatusPill from '@/Components/StatusPill.vue'
import TabBtn from '@/Components/TabBtn.vue'
import Field from '@/Components/Field.vue'

// ── Props ────────────────────────────────────────────────────
const props = defineProps({
  project:      { type: Object, required: true },
  readings:     { type: Array,  default: () => [] },
  elevations:   { type: Array,  default: () => [] },
  activityLogs: { type: Array,  default: () => [] },
})

// ── Tabs ─────────────────────────────────────────────────────
const tabs = [
  { id: 'bacaan',    label: 'Bacaan' },
  { id: 'elevasi',   label: 'Elevasi' },
  { id: 'grafik',    label: 'Grafik' },
  { id: 'aktivitas', label: 'Aktivitas' },
]
const tab = ref('bacaan')

// ── Elevation table rows (join readings + elevations) ────────
const loadingElevations = ref(false)
const elevationRows = computed(() => {
  const readingMap = Object.fromEntries(props.readings.map(r => [r.id, r]))
  return props.elevations.map((e, idx) => {
    const r = readingMap[e.reading_id] ?? {}
    const prev = props.elevations[idx - 1]
    return {
      sequence_no:        e.sequence_no,
      point_name:         e.point_name,
      distance_m:         r.distance_m ?? null,
      ba:                 Number(r.ba ?? 0),
      bt:                 Number(r.bt ?? 0),
      bb:                 Number(r.bb ?? 0),
      reading_type:       r.reading_type ?? 'BS',
      hi:                 e.hi != null ? Number(e.hi) : null,
      delta_h:            prev ? Number(e.raw_elevation) - Number(prev.raw_elevation) : null,
      raw_elevation:      Number(e.raw_elevation),
      correction:         Number(e.correction),
      adjusted_elevation: Number(e.adjusted_elevation),
    }
  })
})

const hasAdjustment = computed(() =>
  props.elevations.some(e => Number(e.correction) !== 0)
)

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
const showReadingForm  = ref(false)
const submittingReading = ref(false)
const readingErrors    = ref(null)

const blankReading = () => ({
  point_name:   '',
  reading_type: 'BS',
  ba: '', bt: '', bb: '',
  distance_m: '',
  notes: '',
})
const readingForm = ref(blankReading())

const fa = {
  class: 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm text-stone-800 focus:outline-none focus:ring-2 focus:ring-stone-400 bg-stone-50',
}

function submitReading() {
  submittingReading.value = true
  readingErrors.value = null
  router.post(route('readings.store', props.project.id), readingForm.value, {
    onSuccess: () => { showReadingForm.value = false; readingForm.value = blankReading() },
    onError:   (e) => { readingErrors.value = e },
    onFinish:  () => { submittingReading.value = false },
    preserveScroll: true,
  })
}

function deleteReading(id) {
  if (!confirm('Hapus bacaan ini?')) return
  router.delete(route('readings.destroy', { project: props.project.id, reading: id }), { preserveScroll: true })
}

// ── Live BT validation ───────────────────────────────────────
const liveBtComputed = computed(() => {
  const ba = parseFloat(readingForm.value.ba)
  const bb = parseFloat(readingForm.value.bb)
  if (isNaN(ba) || isNaN(bb)) return '—'
  return ((ba + bb) / 2).toFixed(4)
})
const liveBtDev = computed(() => {
  const bt = parseFloat(readingForm.value.bt)
  const computed = parseFloat(liveBtComputed.value)
  if (isNaN(bt) || isNaN(computed)) return '—'
  return Math.abs(bt - computed).toFixed(4)
})
const liveBtOk = computed(() => parseFloat(liveBtDev.value) <= 0.002)

// ── Chart data ───────────────────────────────────────────────
const longSectionData  = ref([])
const crossSectionData = ref(null)
const crossStations    = ref([])
const selectedStation  = ref('')

async function fetchLongSection() {
  try {
    const { data } = await axios.get(route('chart.longsection', props.project.id))
    longSectionData.value = data.data
  } catch (e) { console.error(e) }
}

async function fetchCrossSection() {
  if (!selectedStation.value) return
  try {
    const { data } = await axios.get(route('chart.crosssection', props.project.id), {
      params: { station: selectedStation.value }
    })
    crossSectionData.value = data.data
    crossStations.value    = data.stations
  } catch (e) { console.error(e) }
}

onMounted(async () => {
  await fetchLongSection()
  // Fetch cross-section stations list
  try {
    const { data } = await axios.get(route('chart.crosssection', props.project.id))
    crossStations.value    = data.stations
    crossSectionData.value = data.data
    selectedStation.value  = crossStations.value[0] ?? ''
  } catch (e) {}
})

// ── Raw reading helpers ──────────────────────────────────────
function btCheck(r) {
  return ((parseFloat(r.ba) + parseFloat(r.bb)) / 2).toFixed(4)
}
function btDeviation(r) {
  return Math.abs(parseFloat(r.bt) - ((parseFloat(r.ba) + parseFloat(r.bb)) / 2))
}
const invalidBtCount = computed(() => props.readings.filter(r => btDeviation(r) > 0.002).length)

function typeBadge(type) {
  return {
    BS: 'bg-blue-100 text-blue-700',
    IS: 'bg-amber-100 text-amber-700',
    FS: 'bg-rose-100 text-rose-700',
  }[type] ?? 'bg-stone-100 text-stone-600'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
function formatDatetime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}
function fmtNum(v, d = 4) {
  return v != null ? Number(v).toFixed(d) : '—'
}
</script>