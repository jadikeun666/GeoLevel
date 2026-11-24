<template>
  <AppLayout :title="track.name">
    <header class="bg-[#1A1A2E] px-6 py-6">
      <Link :href="route('outdoor.index')" class="text-xs font-mono text-blue-400 hover:text-blue-300">← Outdoor</Link>
      <h1 class="mt-2 text-2xl font-bold text-white tracking-tight break-words">{{ track.name }}</h1>
      <p class="mt-1 text-xs font-mono text-slate-400">
        Sumber: {{ track.source === 'gpx' ? 'Impor GPX' : 'Rekaman' }}
        <template v-if="track.started_at"> · {{ formatDateTime(track.started_at) }}</template>
      </p>
    </header>

    <div class="px-6 py-5 space-y-5">
      <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-xl border border-slate-200 px-4 py-3">
          <p class="text-[10px] font-mono tracking-wider text-slate-400 uppercase">{{ stat.label }}</p>
          <p class="mt-1 text-lg font-semibold text-slate-800 tabular-nums">{{ stat.value }}</p>
        </div>
      </section>
      <p class="text-[11px] text-slate-400">
        Elevasi di halaman ini adalah elevasi GPS, bukan hasil leveling.
        <template v-if="geometry">
          Peta menampilkan {{ geometry.simplified_count }} dari {{ geometry.point_count }} titik
          (disederhanakan ±{{ geometry.tolerance_m }} m; data mentah tidak diubah).
        </template>
      </p>

      <p v-if="loadError" class="text-sm text-red-600">{{ loadError }}</p>
      <p v-else-if="loading" class="text-sm text-slate-500">Memuat geometri track…</p>

      <OutdoorMap :tracks="mapTracks" :waypoints="[]" :selected-track-id="track.id" height="560px" />

      <ElevationProfileChart :track-id="track.id" />

      <div class="flex justify-end">
        <button type="button" @click="deleteTrack"
                class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
          Hapus Track
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const OutdoorMap = defineAsyncComponent(() => import('@/Components/Outdoor/OutdoorMap.vue'))
const ElevationProfileChart = defineAsyncComponent(() => import('@/Components/Outdoor/ElevationProfileChart.vue'))

const props = defineProps({
  track: { type: Object, required: true },
})

const geometry  = ref(null)
const loading   = ref(true)
const loadError = ref('')

const mapTracks = computed(() =>
  geometry.value
    ? [{ id: props.track.id, name: props.track.name, coordinates: geometry.value.coordinates }]
    : []
)

const stats = computed(() => {
  const t = props.track
  return [
    { label: 'Jarak',        value: formatDistance(t.distance_m) },
    { label: 'Durasi',       value: formatDuration(t.duration_s) },
    { label: 'Waktu gerak',  value: formatDuration(t.moving_time_s) },
    { label: 'Titik',        value: t.point_count },
    { label: 'Naik',         value: formatMeters(t.elevation_gain_m) },
    { label: 'Turun',        value: formatMeters(t.elevation_loss_m) },
    { label: 'Elevasi min',  value: formatMeters(t.min_elevation) },
    { label: 'Elevasi maks', value: formatMeters(t.max_elevation) },
  ]
})

function formatDistance(meters) {
  if (meters === null || meters === undefined) return '—'
  const m = Number(meters)
  return m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${m.toFixed(0)} m`
}

function formatMeters(value) {
  if (value === null || value === undefined) return '—'
  return `${Number(value).toFixed(1)} m`
}

function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) return '—'
  const s = Number(seconds)
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const r = s % 60
  return h > 0 ? `${h}j ${m}m ${r}d` : `${m}m ${r}d`
}

function formatDateTime(iso) {
  return new Date(iso).toLocaleString('id-ID')
}

async function deleteTrack() {
  if (!confirm(`Hapus track "${props.track.name}"? Titik GPS-nya ikut terhapus.`)) return
  try {
    await axios.delete(route('outdoor.tracks.destroy', props.track.id))
    router.visit(route('outdoor.index'))
  } catch (e) {
    loadError.value = 'Gagal menghapus track.'
  }
}

onMounted(async () => {
  try {
    const { data } = await axios.get(route('outdoor.tracks.geometry', props.track.id))
    geometry.value = data.data
  } catch (e) {
    loadError.value = 'Gagal memuat geometri track.'
  } finally {
    loading.value = false
  }
})
</script>
