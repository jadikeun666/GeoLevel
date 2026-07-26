<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 shrink-0">
        <h3 class="text-sm font-bold text-slate-700">Import Koordinat dari GPX</h3>
        <button @click="$emit('cancel')" class="text-slate-400 hover:text-slate-600 text-lg leading-none">×</button>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto">
        <!-- Upload -->
        <div v-if="!parsed">
          <label
            class="flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-lg py-10 cursor-pointer hover:border-blue-400 hover:bg-blue-50/40 transition"
          >
            <span class="text-2xl mb-1">📁</span>
            <span class="text-xs text-slate-500">Klik untuk pilih file .gpx</span>
            <span class="text-[10px] text-slate-400 mt-1">Maksimal 500 waypoints</span>
            <input ref="fileInput" type="file" accept=".gpx" class="hidden" @change="handleFileSelect" />
          </label>
          <p v-if="parseError" class="text-xs text-red-500 mt-2">{{ parseError }}</p>
        </div>

        <!-- Preview -->
        <div v-else class="space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">
              {{ waypoints.length }} waypoint ditemukan — {{ selectedCount }} dipilih untuk diimpor
            </p>
            <div class="flex gap-2">
              <button @click="selectAll(true)" type="button" class="text-xs text-blue-600 hover:underline">Pilih semua</button>
              <button @click="selectAll(false)" type="button" class="text-xs text-slate-400 hover:underline">Batal semua</button>
            </div>
          </div>

          <div class="border border-slate-200 rounded-lg overflow-hidden">
            <table class="w-full text-xs">
              <thead class="bg-slate-50 text-slate-500">
                <tr>
                  <th class="w-8 px-2 py-1.5"></th>
                  <th class="text-left px-2 py-1.5">Nama Waypoint</th>
                  <th class="text-left px-2 py-1.5">Koordinat</th>
                  <th class="text-left px-2 py-1.5">Elevasi</th>
                  <th class="text-left px-2 py-1.5">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(wp, idx) in waypoints" :key="idx" class="border-t border-slate-100">
                  <td class="px-2 py-1.5">
                    <input type="checkbox" v-model="wp.selected" />
                  </td>
                  <td class="px-2 py-1.5 font-mono text-slate-700">{{ wp.name }}</td>
                  <td class="px-2 py-1.5 font-mono text-slate-500">{{ wp.lat.toFixed(6) }}, {{ wp.lon.toFixed(6) }}</td>
                  <td class="px-2 py-1.5 font-mono text-slate-500">{{ wp.ele != null ? wp.ele.toFixed(2) + ' m' : '—' }}</td>
                  <td class="px-2 py-1.5">
                    <span v-if="wp.matched" class="text-emerald-600">Terhubung ke bacaan</span>
                    <span v-else class="text-amber-500">Tidak terhubung</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-if="skippedCount > 0" class="text-xs text-slate-400">
            {{ skippedCount }} waypoint diabaikan (tidak punya name/lat/lon yang valid, atau di luar rentang koordinat).
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50 shrink-0">
        <button
          v-if="parsed"
          @click="resetImport"
          type="button"
          class="text-xs px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 mr-auto"
        >
          Ganti File
        </button>
        <button @click="$emit('cancel')" type="button" class="text-xs px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100">
          Batal
        </button>
        <button
          v-if="parsed"
          @click="confirmImport"
          :disabled="selectedCount === 0 || importing"
          type="button"
          class="text-xs px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          {{ importing ? 'Mengimpor...' : `Impor ${selectedCount} Titik` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import gpxParser from 'gpxparser'

const props = defineProps({
  projectId: { type: [Number, String], required: true },
  knownPointNames: { type: Array, default: () => [] }, // uniquePointNames dari readings, untuk auto-suggest matching
})

const emit = defineEmits(['imported', 'cancel'])

const fileInput = ref(null)
const parsed = ref(false)
const parseError = ref(null)
const waypoints = ref([])
const skippedCount = ref(0)
const importing = ref(false)

const MAX_WAYPOINTS = 500

const selectedCount = computed(() => waypoints.value.filter(w => w.selected).length)

function handleFileSelect(e) {
  const file = e.target.files?.[0]
  if (!file) return

  if (!file.name.toLowerCase().endsWith('.gpx')) {
    parseError.value = 'File harus berekstensi .gpx'
    return
  }

  parseError.value = null

  const reader = new FileReader()
  reader.onload = (ev) => {
    try {
      const gpx = new gpxParser()
      gpx.parse(ev.target.result)

      const raw = gpx.waypoints || []
      const known = new Set(props.knownPointNames)

      let skipped = 0
      const valid = []

      for (const wp of raw) {
        const lat = Number(wp.lat)
        const lon = Number(wp.lon)
        const name = (wp.name || '').trim()

        const isValid =
          name.length > 0 &&
          Number.isFinite(lat) && lat >= -90 && lat <= 90 &&
          Number.isFinite(lon) && lon >= -180 && lon <= 180

        if (!isValid) {
          skipped++
          continue
        }

        valid.push({
          name,
          lat,
          lon,
          ele: wp.ele != null && Number.isFinite(Number(wp.ele)) ? Number(wp.ele) : null,
          hdop: wp.hdop != null && Number.isFinite(Number(wp.hdop)) ? Number(wp.hdop) : null,
          matched: known.has(name),
          selected: true,
        })
      }

      if (valid.length > MAX_WAYPOINTS) {
        parseError.value = `File berisi ${valid.length} waypoint valid, melebihi batas maksimal ${MAX_WAYPOINTS}. Hanya ${MAX_WAYPOINTS} pertama yang akan diproses.`
        valid.length = MAX_WAYPOINTS
      }

      if (valid.length === 0) {
        parseError.value = 'Tidak ada waypoint valid ditemukan di file ini (butuh name, lat, lon).'
        return
      }

      waypoints.value = valid
      skippedCount.value = skipped
      parsed.value = true
    } catch (err) {
      parseError.value = 'Gagal memproses file GPX. Pastikan format file benar.'
    }
  }
  reader.onerror = () => {
    parseError.value = 'Gagal membaca file.'
  }
  reader.readAsText(file)
}

function selectAll(value) {
  waypoints.value.forEach(w => { w.selected = value })
}

function resetImport() {
  parsed.value = false
  waypoints.value = []
  skippedCount.value = 0
  parseError.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function guessPointType(name) {
  const upper = name.toUpperCase()
  if (upper.startsWith('BM')) return 'BM'
  if (upper.startsWith('CP')) return 'CP'
  if (upper.startsWith('IS')) return 'IS'
  return 'TP'
}

function confirmImport() {
  const selected = waypoints.value.filter(w => w.selected)
  if (selected.length === 0) return

  importing.value = true

  const payload = {
    points: selected.map(w => ({
      point_name: w.name,
      lat: w.lat,
      lng: w.lon,
      point_type: guessPointType(w.name),
      elevation_ref: w.ele,
      gps_accuracy_m: w.hdop,
    })),
  }

  router.post(route('survey-points.import', props.projectId), payload, {
    preserveScroll: true,
    onSuccess: () => {
      importing.value = false
      emit('imported')
    },
    onError: () => {
      importing.value = false
      parseError.value = 'Gagal mengimpor sebagian atau semua titik. Cek kembali data waypoint.'
    },
  })
}
</script>
