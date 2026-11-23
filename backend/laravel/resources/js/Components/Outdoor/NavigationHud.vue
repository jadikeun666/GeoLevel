<template>
  <div class="space-y-3" data-testid="nav-hud">
    <h2 class="text-sm font-bold text-slate-700">Navigasi</h2>

    <select v-model="targetId" :disabled="active" data-testid="nav-target"
            class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700">
      <option :value="null">Pilih waypoint tujuan</option>
      <option v-for="w in waypoints" :key="w.id" :value="w.id">{{ w.name }}</option>
    </select>

    <button v-if="!active" type="button" data-testid="nav-start" :disabled="!target" @click="start"
            class="w-full rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600 disabled:opacity-40">Mulai Navigasi</button>
    <button v-else type="button" data-testid="nav-stop" @click="stop"
            class="w-full rounded-md bg-white border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600">Berhenti</button>

    <p v-if="geoError" class="text-[11px] text-amber-700">{{ geoError }}</p>

    <dl v-if="active" class="space-y-1 text-[11px]">
      <div class="flex justify-between"><dt class="text-slate-400">Jarak</dt>
        <dd class="font-mono text-slate-700" data-testid="nav-distance">{{ distanceM === null ? '—' : fmtLength(distanceM) }}</dd></div>
      <div class="flex justify-between"><dt class="text-slate-400">Arah</dt>
        <dd class="font-mono text-slate-700" data-testid="nav-bearing">{{ bearing === null ? '—' : `${Math.round(bearing)}° ${compass(bearing)}` }}</dd></div>
      <div class="flex justify-between"><dt class="text-slate-400">Kecepatan</dt>
        <dd class="font-mono text-slate-700" data-testid="nav-speed">{{ speed === null ? '—' : `${speed.toFixed(1)} m/s` }}</dd></div>
      <div class="flex justify-between"><dt class="text-slate-400">ETA</dt>
        <dd class="font-mono text-slate-700" data-testid="nav-eta">{{ eta === null ? '—' : fmtDuration(eta) }}</dd></div>
    </dl>
    <p class="text-[11px] text-slate-400">Arah dari utara geografis; utara grid dan magnetis tidak dikoreksi.</p>
  </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { haversineM, bearingDeg, movingSpeedMps, etaSeconds } from './recorderFilter'

// Contract: docs/outdoor.md, 19.3. Own watchPosition, active only between Mulai and Berhenti.
const props = defineProps({
  waypoints:   { type: Array,  default: () => [] },
  minSpeedMps: { type: Number, required: true },
})

const targetId  = ref(null)
const active    = ref(false)
const geoError  = ref('')
const distanceM = ref(null)
const bearing   = ref(null)
const speed     = ref(null)
const eta       = ref(null)

const target = computed(() => {
  const w = props.waypoints.find((x) => x.id === targetId.value)
  return w ? { lat: Number(w.lat), lng: Number(w.lng) } : null
})

let watchId = null
let samples = []

function start() {
  if (!target.value || !('geolocation' in navigator)) {
    geoError.value = 'Perangkat tidak mendukung GPS.'
    return
  }
  geoError.value = ''
  samples = []
  active.value = true
  watchId = navigator.geolocation.watchPosition(onPosition, onError, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 })
}

function stop() {
  if (watchId !== null) navigator.geolocation.clearWatch(watchId)
  watchId = null
  active.value = false
  distanceM.value = bearing.value = speed.value = eta.value = null
}

function onError(err) {
  geoError.value = err.code === 1 ? 'Izin lokasi ditolak.' : 'Sinyal GPS belum tersedia, menunggu…'
}

function onPosition(pos) {
  geoError.value = ''
  const here = { lat: pos.coords.latitude, lng: pos.coords.longitude, t: pos.timestamp }
  samples = [...samples.filter((s) => s.t >= pos.timestamp - 60000), here]

  distanceM.value = haversineM(here, target.value)
  bearing.value = bearingDeg(here, target.value)
  speed.value = movingSpeedMps(samples, pos.timestamp)
  eta.value = etaSeconds(distanceM.value, speed.value, props.minSpeedMps)
}

onBeforeUnmount(() => { if (watchId !== null) navigator.geolocation.clearWatch(watchId) })

const POINTS = ['U', 'TL', 'T', 'TG', 'S', 'BD', 'B', 'BL']
function compass(deg) { return POINTS[Math.round(deg / 45) % 8] }
function fmtLength(m) { return m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${m.toFixed(0)} m` }
function fmtDuration(s) {
  const t = Math.round(s)
  const pad = (n) => String(n).padStart(2, '0')
  return t >= 3600 ? `${Math.floor(t / 3600)}:${pad(Math.floor((t % 3600) / 60))}:${pad(t % 60)}` : `${pad(Math.floor(t / 60))}:${pad(t % 60)}`
}
</script>
