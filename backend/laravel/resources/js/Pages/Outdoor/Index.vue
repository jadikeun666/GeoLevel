<template>
  <AppLayout title="Outdoor">
    <header class="relative overflow-hidden bg-[#1A1A2E] px-6 py-6">
      <div class="pointer-events-none absolute inset-0 opacity-[0.06]"
           style="background-image: linear-gradient(#60A5FA 1px, transparent 1px), linear-gradient(90deg, #60A5FA 1px, transparent 1px); background-size: 32px 32px;" />
      <div class="relative">
        <p class="text-[10px] font-mono tracking-[0.25em] text-blue-400 uppercase mb-1">GeoLevel · Navigasi Lapangan</p>
        <h1 class="text-2xl font-bold text-white tracking-tight">Outdoor</h1>
        <p class="mt-1 text-sm text-slate-400">Track GPS dan waypoint. Elevasi GPS bukan hasil leveling.</p>
      </div>
    </header>

    <div class="px-6 py-5 grid gap-5 lg:grid-cols-[22rem_1fr]">
      <aside class="space-y-5 min-w-0">
        <!-- Perekam track live (docs/outdoor.md, 17) -->
        <section class="bg-white rounded-xl border border-slate-200 p-4">
          <TrackRecorder :config="recordingConfig" :projects="projects"
                         @position="onRecorderPosition" @trace="onRecorderTrace"
                         @state="onRecorderState" @finished="onRecorderFinished" />
        </section>

        <!-- ── Impor GPX ───────────────────────────────────────── -->
        <section class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
          <h2 class="text-sm font-bold text-slate-700">Impor GPX</h2>
          <input type="file" accept=".gpx" @change="onFile"
                 class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium hover:file:bg-slate-200" />
          <select v-model="importProjectId"
                  class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700">
            <option :value="null">Tanpa proyek</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <div class="flex gap-2">
            <button type="button" :disabled="!file || importing" @click="importGpx('tracks')"
                    class="flex-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500 disabled:opacity-40">
              Impor Track
            </button>
            <button type="button" :disabled="!file || importing" @click="importGpx('waypoints')"
                    class="flex-1 rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600 disabled:opacity-40">
              Impor Waypoint
            </button>
          </div>
          <p v-if="importing" class="text-xs text-slate-500">Mengimpor…</p>
          <p v-if="importMessage" class="text-xs"
             :class="importMessage.type === 'success' ? 'text-emerald-700' : 'text-red-600'">{{ importMessage.text }}</p>
        </section>

        <!-- ── Track ───────────────────────────────────────────── -->
        <section class="bg-white rounded-xl border border-slate-200 p-4">
          <h2 class="text-sm font-bold text-slate-700 mb-2">Track ({{ tracks.length }})</h2>
          <p v-if="tracks.length === 0" class="text-xs text-slate-400">Belum ada track. Impor file GPX untuk memulai.</p>
          <p v-if="loadError" class="text-xs text-red-600 mb-2">{{ loadError }}</p>
          <ul class="divide-y divide-slate-100 max-h-72 overflow-auto">
            <li v-for="t in tracks" :key="t.id" class="py-2 flex items-start gap-2"
                :class="selectedTrackId === t.id ? 'bg-sky-50 -mx-2 px-2 rounded' : ''">
              <input type="checkbox" class="mt-1" :checked="visibleIds.includes(t.id)" @change="toggleTrack(t)" />
              <div class="min-w-0 flex-1">
                <button type="button" class="block max-w-full truncate text-left text-xs font-medium text-slate-700 hover:text-blue-600"
                        @click="selectTrack(t.id)">{{ t.name }}</button>
                <p class="font-mono text-[11px] text-slate-400">{{ formatDistance(t.distance_m) }} · {{ t.point_count }} titik</p>
              </div>
              <Link :href="route('outdoor.tracks.show', t.id)" class="text-[11px] text-blue-600 hover:underline shrink-0">Detail</Link>
            </li>
          </ul>
        </section>

        <!-- ── Waypoint ────────────────────────────────────────── -->
        <section class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-bold text-slate-700">Waypoint ({{ waypoints.length }})</h2>
            <button type="button" class="text-[11px] font-semibold text-blue-600 hover:underline" @click="openCreate">+ Tambah</button>
          </div>
          <WaypointForm v-if="formOpen" :key="editingWaypoint?.id ?? 'new'" :waypoint="editingWaypoint" :projects="projects"
                        class="mb-3" @saved="onWaypointSaved" @cancel="closeForm" />
          <p v-if="waypoints.length === 0" class="text-xs text-slate-400">Belum ada waypoint.</p>
          <ul class="divide-y divide-slate-100 max-h-72 overflow-auto">
            <li v-for="w in waypoints" :key="w.id" class="py-2 flex items-center gap-2"
                :class="selectedWaypointId === w.id ? 'bg-blue-50 -mx-2 px-2 rounded' : ''">
              <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: categoryColor(w.category) }"></span>
              <button type="button" class="min-w-0 flex-1 text-left" @click="selectedWaypointId = w.id">
                <span class="block truncate text-xs font-medium text-slate-700">{{ w.name }}</span>
                <span class="font-mono text-[11px] text-slate-400">{{ categoryLabel(w.category) }}</span>
              </button>
              <button type="button" class="text-[11px] text-blue-600 hover:underline shrink-0" @click="openEdit(w)">Ubah</button>
              <button type="button" class="text-[11px] text-red-500 hover:underline shrink-0" @click="deleteWaypoint(w)">Hapus</button>
            </li>
          </ul>
        </section>

        <!-- Perencana rute (docs/outdoor.md, 17.9) -->
        <section class="bg-white rounded-xl border border-slate-200 p-4">
          <RoutePlanner :routes="routes" :projects="projects" :max-vertices="routeMaxVertices"
                        v-model:vertices="routeVertices" v-model:drawing="routeDrawing"
                        @visible-change="ids => visibleRouteIds = ids"
                        @saved="onRouteSaved" />
        </section>
      </aside>

      <!-- ── Peta ───────────────────────────────────────────────── -->
      <section class="min-w-0">
        <OutdoorMap
          :tracks="mapTracks"
          :waypoints="waypoints"
          :selected-track-id="selectedTrackId"
          :live-trace="liveTrace"
          :live-position="livePosition"
          :routes="mapRoutes"
          :route-draft="routeVertices"
          :draw-route="routeDrawing"
          height="640px"
          @select-track="selectTrack"
          @add-route-vertex="onAddRouteVertex"
          @select-waypoint="id => selectedWaypointId = id"
        />
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { categoryColor, categoryLabel } from '@/Components/Outdoor/outdoorCategories'
import WaypointForm from '@/Components/Outdoor/WaypointForm.vue'

const OutdoorMap = defineAsyncComponent(() => import('@/Components/Outdoor/OutdoorMap.vue'))
const TrackRecorder = defineAsyncComponent(() => import('@/Components/Outdoor/TrackRecorder.vue'))
const RoutePlanner = defineAsyncComponent(() => import('@/Components/Outdoor/RoutePlanner.vue'))

const props = defineProps({
  tracks:    { type: Array, default: () => [] },
  waypoints: { type: Array, default: () => [] },
  projects:  { type: Array, default: () => [] },
  recordingConfig: { type: Object, required: true },
  routes:          { type: Array,  default: () => [] },
  routeMaxVertices: { type: Number, required: true },
})

const geometries         = ref({})
const visibleIds         = ref([])
const selectedTrackId    = ref(null)
const selectedWaypointId = ref(null)
const loadError          = ref('')

const file            = ref(null)
const importProjectId = ref(null)
const importing       = ref(false)
const importMessage   = ref(null)

const mapTracks = computed(() =>
  props.tracks
    .filter(t => visibleIds.value.includes(t.id) && geometries.value[t.id])
    .map(t => ({ id: t.id, name: t.name, coordinates: geometries.value[t.id] }))
)

async function loadGeometry(id) {
  if (geometries.value[id]) return
  try {
    const { data } = await axios.get(route('outdoor.tracks.geometry', id))
    geometries.value = { ...geometries.value, [id]: data.data.coordinates }
  } catch (e) {
    loadError.value = 'Gagal memuat geometri track.'
  }
}

async function showTrack(id) {
  if (!visibleIds.value.includes(id)) visibleIds.value = [...visibleIds.value, id]
  await loadGeometry(id)
}

function hideTrack(id) {
  visibleIds.value = visibleIds.value.filter(v => v !== id)
  if (selectedTrackId.value === id) selectedTrackId.value = null
}

function toggleTrack(track) {
  if (visibleIds.value.includes(track.id)) hideTrack(track.id)
  else showTrack(track.id)
}

function selectTrack(id) {
  selectedTrackId.value = id
  showTrack(id)
}

function onFile(event) {
  file.value = event.target.files[0] ?? null
  importMessage.value = null
}

async function importGpx(kind) {
  if (!file.value || importing.value) return
  importing.value = true
  importMessage.value = null

  const form = new FormData()
  form.append('file', file.value)
  if (importProjectId.value) form.append('project_id', importProjectId.value)

  try {
    if (kind === 'tracks') {
      const { data } = await axios.post(route('outdoor.tracks.import'), form)
      router.reload({ only: ['tracks'] })
      data.data.forEach(t => showTrack(t.id))
      importMessage.value = { type: 'success', text: `${data.data.length} track berhasil diimpor.` }
    } else {
      const { data } = await axios.post(route('outdoor.waypoints.import'), form)
      router.reload({ only: ['waypoints'] })
      importMessage.value = { type: 'success', text: `${data.imported} waypoint berhasil diimpor.` }
    }
  } catch (e) {
    const errors = e.response?.data?.errors
    const text = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Gagal mengimpor file.')
    importMessage.value = { type: 'error', text }
  } finally {
    importing.value = false
  }
}

const formOpen        = ref(false)
const editingWaypoint = ref(null)

function openCreate() {
  editingWaypoint.value = null
  formOpen.value = true
}

function openEdit(waypoint) {
  editingWaypoint.value = waypoint
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
  editingWaypoint.value = null
}

function onWaypointSaved() {
  closeForm()
  router.reload({ only: ['waypoints'] })
}

async function deleteWaypoint(waypoint) {
  if (!confirm(`Hapus waypoint "${waypoint.name}"?`)) return
  try {
    await axios.delete(route('outdoor.waypoints.destroy', waypoint.id))
    if (selectedWaypointId.value === waypoint.id) selectedWaypointId.value = null
    router.reload({ only: ['waypoints'] })
  } catch (e) {
    importMessage.value = { type: 'error', text: 'Gagal menghapus waypoint.' }
  }
}

const liveTrace    = ref([])
const livePosition = ref(null)

function onRecorderPosition(p) { livePosition.value = { lat: p.lat, lng: p.lng } }
function onRecorderTrace(coords) { liveTrace.value = coords }
function onRecorderState(s) {
  if (s === 'idle') {
    liveTrace.value = []
    livePosition.value = null
  }
}
function onRecorderFinished() { router.reload({ only: ['tracks'] }) }

// Routes: server geometry is [lat, lng]; map props are [lng, lat] (docs 17.9). Converted only here.
const routeVertices   = ref([])
const routeDrawing    = ref(false)
const visibleRouteIds = ref([])

const mapRoutes = computed(() =>
  props.routes
    .filter(r => visibleRouteIds.value.includes(r.id))
    .map(r => ({ id: r.id, name: r.name, coordinates: r.geometry.map(([lat, lng]) => [lng, lat]) }))
)

function onAddRouteVertex({ lat, lng }) {
  if (routeVertices.value.length >= props.routeMaxVertices) return
  routeVertices.value = [...routeVertices.value, [lng, lat]]
}
function onRouteSaved() { router.reload({ only: ['routes'] }) }

function formatDistance(meters) {
  if (meters === null || meters === undefined) return '—'
  const m = Number(meters)
  return m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${m.toFixed(0)} m`
}

onMounted(() => {
  props.tracks.slice(0, 3).forEach(t => showTrack(t.id))
})
</script>
