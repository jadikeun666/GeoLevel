<template>
  <div class="space-y-3" data-testid="recorder">
    <h2 class="text-sm font-bold text-slate-700">Rekam Track</h2>

    <!-- Pemulihan: ada rekaman aktif dari sesi sebelumnya -->
    <div v-if="activeTrack && !track" class="rounded-md border border-amber-300 bg-amber-50 p-3 space-y-2"
         data-testid="recorder-recover">
      <p class="text-xs text-amber-800">
        Ada rekaman aktif: <strong>{{ activeTrack.name }}</strong> ({{ activeTrack.point_count }} titik di server).
      </p>
      <div class="flex gap-2">
        <button type="button" :disabled="busy" data-testid="recorder-continue" @click="continueActive"
                class="flex-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500 disabled:opacity-40">
          Lanjutkan
        </button>
        <button type="button" :disabled="busy" data-testid="recorder-finish-active" @click="finishActive"
                class="flex-1 rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600 disabled:opacity-40">
          Selesaikan
        </button>
      </div>
    </div>

    <!-- Mulai rekaman -->
    <div v-if="!track && !activeTrack" class="space-y-2">
      <input v-model="name" type="text" maxlength="150" placeholder="Nama rekaman (opsional)"
             data-testid="recorder-name"
             class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700" />
      <select v-model="projectId" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700">
        <option :value="null">Tanpa proyek</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <button type="button" :disabled="busy" data-testid="recorder-start" @click="startRecording"
              class="w-full rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-40">
        {{ state === 'starting' ? 'Memulai…' : 'Mulai Rekam' }}
      </button>
    </div>

    <!-- Sesi berjalan -->
    <div v-if="track" class="space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-700 truncate">{{ track.name }}</span>
        <span class="text-[11px] font-mono rounded px-1.5 py-0.5"
              :class="state === 'recording' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
              data-testid="recorder-state">{{ STATE_LABEL[state] }}</span>
      </div>

      <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-[11px]">
        <dt class="text-slate-400">Titik sesi ini</dt>
        <dd class="font-mono text-slate-700 text-right" data-testid="recorder-count">{{ acceptedCount }}</dd>
        <dt class="text-slate-400">Jarak (perkiraan)</dt>
        <dd class="font-mono text-slate-700 text-right">{{ formatDistance(distanceM) }}</dd>
        <dt class="text-slate-400">Waktu</dt>
        <dd class="font-mono text-slate-700 text-right">{{ formatElapsed(elapsedS) }}</dd>
        <dt class="text-slate-400">Terkirim ke server</dt>
        <dd class="font-mono text-slate-700 text-right" data-testid="recorder-server-count">{{ serverCount }}</dd>
        <dt class="text-slate-400">Menunggu kirim</dt>
        <dd class="font-mono text-slate-700 text-right" data-testid="recorder-queued">{{ queuedPoints }}</dd>
        <dt class="text-slate-400">Koneksi</dt>
        <dd class="font-mono text-right" :class="online ? 'text-emerald-600' : 'text-red-600'">{{ online ? 'Online' : 'Offline' }}</dd>
      </dl>

      <div class="flex gap-2">
        <button v-if="state === 'recording' || state === 'autopaused'" type="button" data-testid="recorder-pause"
                @click="pauseRecording"
                class="flex-1 rounded-md bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400">Jeda</button>
        <button v-if="state === 'paused'" type="button" data-testid="recorder-resume" @click="resumeRecording"
                class="flex-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500">Lanjut</button>
        <button type="button" :disabled="state === 'finishing'" data-testid="recorder-finish" @click="finishRecording"
                class="flex-1 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500 disabled:opacity-40">
          {{ state === 'finishing' ? 'Menyelesaikan…' : 'Selesai' }}
        </button>
      </div>

      <p class="text-[11px] text-slate-400">Biarkan layar menyala dan tab tetap terbuka; perekaman di latar belakang tidak andal.</p>
    </div>

    <p v-if="hiddenNotice" class="text-xs text-amber-700" data-testid="recorder-hidden-notice">{{ hiddenNotice }}</p>
    <p v-if="geoError" class="text-xs text-amber-700" data-testid="recorder-geo-error">{{ geoError }}</p>
    <p v-if="message" class="text-xs" data-testid="recorder-message"
       :class="message.type === 'error' ? 'text-red-600' : message.type === 'success' ? 'text-emerald-700' : 'text-slate-600'">{{ message.text }}</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import {
  evaluateFix, nextAutoPause, speedBetween, haversineM, toFix, toPayloadPoint, INITIAL_AUTO_PAUSE,
} from './recorderFilter'
import * as queue from './recorderQueue'

// Contract: docs/outdoor.md, bagian 17.4. All thresholds come from props.config (17.2).
const props = defineProps({
  config:   { type: Object, required: true },
  projects: { type: Array,  default: () => [] },
})
const emit = defineEmits(['position', 'trace', 'state', 'finished'])

const STATE_LABEL = {
  idle: 'Siap', starting: 'Memulai…', recording: 'Merekam', paused: 'Dijeda',
  autopaused: 'Berhenti otomatis (diam)', finishing: 'Menyelesaikan…',
}

const state        = ref('idle')
const track        = ref(null)      // session track
const activeTrack  = ref(null)      // recoverable track found at load
const name         = ref('')
const projectId    = ref(null)
const message      = ref(null)
const geoError     = ref('')
const hiddenNotice = ref('')
const limitReached = ref(false)
const online       = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
const acceptedCount = ref(0)
const distanceM    = ref(0)
const elapsedS     = ref(0)
const serverCount  = ref(0)
const queuedPoints = ref(0)

const busy = computed(() => state.value === 'starting' || state.value === 'finishing')

// Non-reactive session internals
let watchId = null
let tickTimer = null
let retryTimer = null
let retryDelayS = 2
let lastAccepted = null
let lastRaw = null
let autoPause = INITIAL_AUTO_PAUSE
let trace = []
let traceDirty = false
let bufferedCount = 0
let lastFlushMs = 0
let flushChain = Promise.resolve()
let sending = null
let sendAgain = false
let sessionTrackId = null
let wakeLock = null
let hiddenAt = null

function setState(s) {
  if (state.value !== s) {
    state.value = s
    emit('state', s)
  }
}

function say(type, text) { message.value = { type, text } }
function statusOf(e) { return e.response?.status ?? 0 }
function apiMessage(e, fallback) {
  const errors = e.response?.data?.errors
  if (errors) return Object.values(errors).flat().join(' ')
  return e.response?.data?.message ?? fallback
}

// ── Session lifecycle ────────────────────────────────────────────────
async function startRecording() {
  if (state.value !== 'idle') return
  setState('starting')
  message.value = null
  try {
    const { data } = await axios.post(route('outdoor.tracks.store'), {
      name: name.value || null,
      project_id: projectId.value || null,
    })
    beginSession(data.data)
  } catch (e) {
    setState('idle')
    if (statusOf(e) === 409 && e.response.data?.reason === 'active_exists') {
      activeTrack.value = e.response.data.track
      say('info', 'Sudah ada rekaman aktif. Lanjutkan atau selesaikan dulu.')
    } else {
      say('error', apiMessage(e, 'Gagal memulai rekaman.'))
    }
  }
}

function beginSession(t) {
  track.value = t
  sessionTrackId = t.id
  activeTrack.value = null
  serverCount.value = t.point_count ?? 0
  acceptedCount.value = 0
  distanceM.value = 0
  elapsedS.value = 0
  queuedPoints.value = 0
  lastAccepted = null
  lastRaw = null
  autoPause = INITIAL_AUTO_PAUSE
  trace = []
  traceDirty = false
  bufferedCount = 0
  lastFlushMs = Date.now()
  limitReached.value = false
  retryDelayS = 2
  setState(t.status === 'paused' ? 'paused' : 'recording')
  startWatching()
  tickTimer = setInterval(tick, 1000)
  requestWakeLock()
  refreshQueued(t.id)
  flushBuffer(t.id, true) // moves leftovers from an earlier page session into batches, then sends
}

function endSession() {
  if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null }
  clearInterval(tickTimer); tickTimer = null
  clearTimeout(retryTimer); retryTimer = null
  releaseWakeLock()
  track.value = null
  sessionTrackId = null
  trace = []
  traceDirty = false
  emit('trace', [])
  acceptedCount.value = 0
  distanceM.value = 0
  elapsedS.value = 0
  queuedPoints.value = 0
  geoError.value = ''
  hiddenNotice.value = ''
  setState('idle')
}

async function continueActive() {
  const t = activeTrack.value
  if (!t || busy.value) return
  setState('starting')
  message.value = null
  try {
    let current = t
    if (t.status === 'paused') {
      const { data } = await axios.post(route('outdoor.tracks.resume', t.id))
      current = data.data
    }
    beginSession(current)
  } catch (e) {
    setState('idle')
    say('error', apiMessage(e, 'Gagal melanjutkan rekaman.'))
  }
}

async function finishActive() {
  const t = activeTrack.value
  if (!t || busy.value) return
  setState('finishing')
  message.value = null
  sessionTrackId = t.id
  await flushBuffer(t.id, true)
  const result = await sendLoop(t.id)
  if (!result.ok) {
    setState('idle')
    say('error', 'Belum semua titik terkirim. Rekaman tetap aktif; coba lagi saat online.')
    return
  }
  try {
    const { data } = await axios.post(route('outdoor.tracks.finish', t.id))
    activeTrack.value = null
    setState('idle')
    say('success', 'Rekaman diselesaikan.')
    emit('finished', data.data)
  } catch (e) {
    setState('idle')
    say('error', apiMessage(e, 'Gagal menyelesaikan rekaman.'))
  }
}

async function pauseRecording() {
  if (state.value !== 'recording' && state.value !== 'autopaused') return
  try {
    await axios.post(route('outdoor.tracks.pause', track.value.id))
    setState('paused')
    flushBuffer(track.value.id, true)
  } catch (e) {
    say('error', apiMessage(e, 'Gagal menjeda rekaman.'))
  }
}

async function resumeRecording() {
  if (state.value !== 'paused') return
  try {
    await axios.post(route('outdoor.tracks.resume', track.value.id))
    autoPause = INITIAL_AUTO_PAUSE
    setState('recording')
  } catch (e) {
    say('error', apiMessage(e, 'Gagal melanjutkan rekaman.'))
  }
}

async function finishRecording() {
  if (!track.value || state.value === 'finishing') return
  const previous = state.value
  const id = track.value.id
  setState('finishing')
  message.value = null
  await flushBuffer(id, true)
  const result = await sendLoop(id)
  if (!result.ok) {
    say('error', result.fatal
      ? 'Sesi login berakhir. Muat ulang halaman; data lokal tetap aman.'
      : 'Belum semua titik terkirim (koneksi?). Rekaman tetap aktif; coba Selesai lagi saat online.')
    setState(previous)
    return
  }
  try {
    const { data } = await axios.post(route('outdoor.tracks.finish', id))
    endSession()
    say('success', 'Rekaman diselesaikan.')
    emit('finished', data.data)
  } catch (e) {
    say('error', apiMessage(e, 'Gagal menyelesaikan rekaman.'))
    setState(previous)
  }
}

// ── Position handling ────────────────────────────────────────────────
function startWatching() {
  if (!('geolocation' in navigator)) {
    geoError.value = 'Perangkat tidak mendukung GPS.'
    return
  }
  watchId = navigator.geolocation.watchPosition(onPosition, onGeoError, {
    enableHighAccuracy: true, maximumAge: 0, timeout: 20000,
  })
}

function onGeoError(err) {
  geoError.value = err.code === 1
    ? 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini.'
    : 'Sinyal GPS belum tersedia, menunggu…'
}

function onPosition(pos) {
  const cfg = props.config
  const fix = toFix(pos)
  geoError.value = ''
  emit('position', { lat: fix.lat, lng: fix.lng, accuracy_m: fix.accuracy_m })

  let speed = Number.isFinite(pos.coords.speed) ? pos.coords.speed : null
  if (speed === null && lastRaw) speed = speedBetween(lastRaw, fix)
  lastRaw = fix

  if (state.value === 'recording' || state.value === 'autopaused') {
    autoPause = nextAutoPause(autoPause, speed, pos.timestamp, cfg)
    setState(autoPause.autoPaused ? 'autopaused' : 'recording')
  }
  if (state.value !== 'recording' || limitReached.value || !track.value) return

  if (serverCount.value + queuedPoints.value >= cfg.track_max_points) { onLimit(); return }
  if (queuedPoints.value >= cfg.offline_queue_max_points) {
    say('error', 'Antrean lokal penuh. Sambungkan internet agar titik terkirim.')
    return
  }

  if (!evaluateFix(fix, lastAccepted, cfg).accept) return

  if (lastAccepted) distanceM.value += haversineM(lastAccepted, fix)
  lastAccepted = fix
  acceptedCount.value++
  queuedPoints.value++
  bufferedCount++
  trace.push([fix.lng, fix.lat])
  traceDirty = true

  const trackId = track.value.id
  queue.addPoint(trackId, toPayloadPoint(pos))
    .then(() => {
      if (bufferedCount >= cfg.record_batch_size) flushBuffer(trackId, false)
    })
    .catch(() => say('error', 'Gagal menyimpan titik ke penyimpanan lokal.'))
}

function tick() {
  if (state.value === 'recording' || state.value === 'autopaused') elapsedS.value++
  if (traceDirty) {
    traceDirty = false
    emit('trace', [...trace]) // new array each time: parent watches shallowly
  }
  if (track.value && bufferedCount > 0
      && Date.now() - lastFlushMs >= props.config.record_batch_interval_s * 1000) {
    flushBuffer(track.value.id, true)
  }
}

function onLimit() {
  limitReached.value = true
  say('error', 'Batas titik track tercapai. Perekaman dihentikan; tekan Selesai.')
}

// ── Local queue and sending ──────────────────────────────────────────
function flushBuffer(trackId, all) {
  lastFlushMs = Date.now()
  flushChain = flushChain.then(() => doFlush(trackId, all))
  return flushChain
}

async function doFlush(trackId, all) {
  try {
    do {
      const batch = await queue.makeBatch(trackId, props.config.record_batch_size)
      if (!batch) break
      bufferedCount = Math.max(0, bufferedCount - batch.points.length)
    } while (all)
  } catch (e) {
    say('error', 'Gagal menyimpan antrean lokal.')
    return
  }
  sendLoop(trackId)
}

function sendLoop(trackId) {
  if (sending) { sendAgain = true; return sending }
  sending = (async () => {
    let result = { ok: true }
    try {
      do {
        sendAgain = false
        result = await sendPending(trackId)
      } while (sendAgain && result.ok)
    } catch (e) {
      say('error', 'Gagal membaca antrean lokal.')
      result = { ok: false }
    } finally {
      sending = null
    }
    return result
  })()
  return sending
}

async function sendPending(trackId) {
  const batches = await queue.listPending(trackId)
  for (const batch of batches) {
    try {
      const { data } = await axios.post(route('outdoor.tracks.points', trackId), {
        client_batch_id: batch.client_batch_id,
        points: batch.points,
      })
      await queue.deletePending(batch.client_batch_id)
      retryDelayS = 2
      serverCount.value = data.data.point_count
      if (data.data.limit_reached) onLimit()
    } catch (e) {
      const status = statusOf(e)
      const reason = e.response?.data?.reason
      if (status === 409 && reason === 'limit_reached') {
        await queue.deletePending(batch.client_batch_id)
        onLimit()
        continue
      }
      if (status === 409) {
        await queue.deletePending(batch.client_batch_id)
        say('error', e.response.data?.message ?? 'Rekaman tidak aktif; batch dibuang.')
        continue
      }
      if (status === 422) {
        await queue.deletePending(batch.client_batch_id)
        say('error', 'Batch ditolak server: ' + apiMessage(e, 'data tidak valid.'))
        continue
      }
      if (status === 401 || status === 419) {
        say('error', 'Sesi login berakhir. Muat ulang halaman; data lokal tetap aman.')
        return { ok: false, fatal: true }
      }
      scheduleRetry(trackId) // network error, 5xx, 429
      await refreshQueued(trackId)
      return { ok: false, retry: true }
    }
  }
  await refreshQueued(trackId)
  return { ok: true }
}

function scheduleRetry(trackId) {
  if (retryTimer) return
  const delay = retryDelayS
  retryDelayS = Math.min(30, retryDelayS * 2)
  retryTimer = setTimeout(() => { retryTimer = null; sendLoop(trackId) }, delay * 1000)
}

async function refreshQueued(trackId) {
  try { queuedPoints.value = await queue.countQueued(trackId) } catch (e) { /* display only */ }
}

// ── Wake Lock, visibility, connectivity ──────────────────────────────
async function requestWakeLock() {
  if (!props.config.record_use_wake_lock || !('wakeLock' in navigator) || !track.value) return
  try { wakeLock = await navigator.wakeLock.request('screen') } catch (e) { wakeLock = null }
}

function releaseWakeLock() {
  if (wakeLock) wakeLock.release().catch(() => {})
  wakeLock = null
}

function onVisibility() {
  if (document.hidden) {
    if (track.value) hiddenAt = Date.now()
    return
  }
  if (track.value) {
    if (hiddenAt && Date.now() - hiddenAt > 5000) {
      hiddenNotice.value = `Tab sempat disembunyikan ${Math.round((Date.now() - hiddenAt) / 1000)} detik. `
        + 'GPS di latar belakang tidak andal; titik pada periode itu mungkin hilang.'
    }
    if (!wakeLock || wakeLock.released) requestWakeLock()
  }
  hiddenAt = null
}

function onOnline() {
  online.value = true
  clearTimeout(retryTimer); retryTimer = null
  retryDelayS = 2
  if (sessionTrackId) sendLoop(sessionTrackId)
}
function onOffline() { online.value = false }

onMounted(async () => {
  document.addEventListener('visibilitychange', onVisibility)
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)
  try {
    const { data } = await axios.get(route('outdoor.tracks.active'))
    if (data.data && !track.value) activeTrack.value = data.data
  } catch (e) { /* recovery is best-effort */ }
})

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisibility)
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
  if (watchId !== null) navigator.geolocation.clearWatch(watchId)
  clearInterval(tickTimer)
  clearTimeout(retryTimer)
  releaseWakeLock()
})

// ── Formatting ───────────────────────────────────────────────────────
function formatDistance(m) {
  return m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${m.toFixed(0)} m`
}
function formatElapsed(s) {
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const sec = s % 60
  const pad = (n) => String(n).padStart(2, '0')
  return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`
}
</script>
