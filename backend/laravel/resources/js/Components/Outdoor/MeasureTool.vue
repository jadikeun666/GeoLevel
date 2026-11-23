<template>
  <div class="space-y-3" data-testid="measure">
    <h2 class="text-sm font-bold text-slate-700">Ukur</h2>

    <div class="flex rounded-md overflow-hidden border border-slate-200 text-xs font-medium w-fit">
      <button type="button" data-testid="measure-mode-line" @click="setMode('line')"
              :class="mode === 'line' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'" class="px-2.5 py-1">Jarak</button>
      <button type="button" data-testid="measure-mode-area" @click="setMode('area')"
              :class="mode === 'area' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'" class="px-2.5 py-1 border-l border-slate-200">Luas</button>
    </div>

    <button v-if="!measuring" type="button" data-testid="measure-start" @click="start"
            class="w-full rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600">Mulai Ukur</button>

    <template v-else>
      <p class="text-[11px] text-slate-500">
        Klik peta untuk menambah titik: <span data-testid="measure-count" class="font-mono">{{ vertices.length }}</span>
        (minimal {{ minPoints }}).
      </p>
      <p v-if="vertices.length >= maxVertices" class="text-[11px] text-red-600">Batas jumlah titik tercapai.</p>
      <div class="flex flex-wrap gap-2">
        <button type="button" :disabled="vertices.length === 0" @click="$emit('update:vertices', vertices.slice(0, -1))"
                class="rounded-md bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 disabled:opacity-40">Urungkan</button>
        <button type="button" :disabled="vertices.length === 0" @click="$emit('update:vertices', [])"
                class="rounded-md bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 disabled:opacity-40">Kosongkan</button>
        <button type="button" data-testid="measure-calc" :disabled="!canCalc" @click="calculate"
                class="rounded-md bg-orange-600 px-2.5 py-1 text-xs font-semibold text-white disabled:opacity-40">
          {{ busy ? 'Menghitung…' : 'Hitung' }}
        </button>
        <button type="button" data-testid="measure-stop" @click="stop"
                class="rounded-md bg-white border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600">Selesai</button>
      </div>
    </template>

    <p v-for="msg in errors" :key="msg" class="text-[11px] text-red-600">{{ msg }}</p>

    <dl v-if="result" class="space-y-1 text-[11px]">
      <div class="flex justify-between"><dt class="text-slate-400">{{ result.area_m2 ? 'Panjang sisi (terbuka)' : 'Jarak' }}</dt>
        <dd class="font-mono text-slate-700" data-testid="measure-distance">{{ fmtLength(result.distance_m) }}</dd></div>
      <div v-if="result.area_m2" class="flex justify-between"><dt class="text-slate-400">Keliling</dt>
        <dd class="font-mono text-slate-700">{{ fmtLength(result.perimeter_m) }}</dd></div>
      <div v-if="result.area_m2" class="flex justify-between"><dt class="text-slate-400">Luas</dt>
        <dd class="font-mono text-slate-700" data-testid="measure-area">{{ fmtArea(result.area_m2) }}</dd></div>
    </dl>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

// Contract: docs/outdoor.md, 19.2. vertices are [lng, lat] (map order); the API takes [lat, lng].
const props = defineProps({
  vertices:    { type: Array,   default: () => [] },
  measuring:   { type: Boolean, default: false },
  maxVertices: { type: Number,  required: true },
})
const emit = defineEmits(['update:vertices', 'update:measuring'])

const mode   = ref('line')
const busy   = ref(false)
const result = ref(null)
const errors = ref([])

const minPoints = computed(() => (mode.value === 'area' ? 3 : 2))
const canCalc   = computed(() => props.vertices.length >= minPoints.value && !busy.value)

// Any change to the points or the mode invalidates the previous result.
watch(() => props.vertices, () => { result.value = null; errors.value = [] })

function setMode(m) { mode.value = m; result.value = null; errors.value = [] }
function start() { result.value = null; errors.value = []; emit('update:measuring', true) }
function stop()  { emit('update:measuring', false); emit('update:vertices', []); result.value = null; errors.value = [] }

async function calculate() {
  busy.value = true
  errors.value = []
  try {
    const { data } = await axios.post(route('outdoor.measure'), {
      mode: mode.value,
      points: props.vertices.map(([lng, lat]) => [lat, lng]),
    })
    result.value = data.data
  } catch (e) {
    result.value = null
    errors.value = Object.values(e.response?.data?.errors ?? { general: [e.response?.data?.message ?? 'Gagal menghitung.'] }).flat()
  } finally {
    busy.value = false
  }
}

function fmtLength(m) {
  const v = Number(m)
  return v >= 1000 ? `${(v / 1000).toFixed(3)} km` : `${v.toFixed(1)} m`
}
function fmtArea(m2) {
  const v = Number(m2)
  return `${v.toFixed(1)} m² (${(v / 10000).toFixed(4)} ha)`
}
</script>
