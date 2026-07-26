<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200">
        <h3 class="text-sm font-bold text-slate-700">
          {{ initialLat != null ? 'Edit' : 'Tambah' }} Koordinat — {{ pointName }}
        </h3>
        <button @click="$emit('cancel')" class="text-slate-400 hover:text-slate-600 text-lg leading-none">×</button>
      </div>

      <div class="p-5 space-y-4">
        <!-- Search -->
        <div class="relative">
          <label class="block text-xs font-medium text-slate-500 mb-1">Cari lokasi</label>
          <div class="flex gap-2">
            <input
              v-model="searchQuery"
              @keyup.enter="searchLocation"
              type="text"
              placeholder="Contoh: Denpasar, Bali"
              class="flex-1 text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400"
            />
            <button
              @click="searchLocation"
              :disabled="searching"
              type="button"
              class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 disabled:opacity-50"
            >
              {{ searching ? '...' : 'Cari' }}
            </button>
          </div>
          <ul v-if="searchResults.length" class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
            <li
              v-for="(r, i) in searchResults"
              :key="i"
              @click="selectSearchResult(r)"
              class="px-3 py-2 text-xs text-slate-600 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0"
            >
              {{ r.display_name }}
            </li>
          </ul>
        </div>

        <!-- Mini map -->
        <div class="relative">
          <div ref="mapContainer" class="w-full h-64 rounded-lg border border-slate-200"></div>
          <div class="absolute top-2 left-2 z-10 flex rounded-lg overflow-hidden border border-slate-200 shadow-sm bg-white text-[10px] font-medium">
            <button @click="setMapStyle('street')" type="button"
              :class="mapStyleMode === 'street' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
              class="px-2 py-1">Peta</button>
            <button @click="setMapStyle('satellite')" type="button"
              :class="mapStyleMode === 'satellite' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
              class="px-2 py-1 border-l border-slate-200">Satelit</button>
          </div>
          <div v-if="hoverCoord" class="absolute bottom-2 left-2 bg-white/90 text-[10px] font-mono text-slate-500 px-2 py-1 rounded shadow">
            {{ hoverCoord.lat.toFixed(6) }}, {{ hoverCoord.lng.toFixed(6) }}
          </div>
        </div>

        <button
          @click="useMyLocation"
          type="button"
          :disabled="locating"
          class="text-xs px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 disabled:opacity-50"
        >
          {{ locating ? 'Mencari lokasi...' : '📍 Gunakan Lokasi Saya' }}
        </button>
        <p v-if="geoError" class="text-xs text-red-500">{{ geoError }}</p>

        <!-- Manual lat/lng -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Latitude</label>
            <input
              v-model.number="form.lat"
              @change="syncMarkerFromInputs"
              type="number" step="0.00000001"
              class="w-full text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Longitude</label>
            <input
              v-model.number="form.lng"
              @change="syncMarkerFromInputs"
              type="number" step="0.00000001"
              class="w-full text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Tipe Titik</label>
          <select
            v-model="form.point_type"
            class="w-full text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400"
          >
            <option value="BM">BM — Benchmark</option>
            <option value="TP">TP — Titik Ukur</option>
            <option value="IS">IS — Intermediate Sight</option>
            <option value="CP">CP — Control Point</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Catatan (opsional)</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="w-full text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400"
          ></textarea>
        </div>

        <p v-if="!hasCoordinate" class="text-xs text-amber-600">
          Klik peta, cari lokasi, atau isi lat/lng manual untuk menentukan koordinat.
        </p>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50">
        <button
          @click="$emit('cancel')"
          type="button"
          class="text-xs px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100"
        >
          Batal
        </button>
        <button
          @click="save"
          :disabled="!hasCoordinate"
          type="button"
          class="text-xs px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          Simpan
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import maplibregl from 'maplibre-gl'
import { MAP_STYLES, INDONESIA_CENTER } from './mapStyle'

const props = defineProps({
  pointName:  { type: String, required: true },
  initialLat: { type: Number, default: null },
  initialLng: { type: Number, default: null },
  pointType:  { type: String, default: 'TP' },
})

const emit = defineEmits(['save', 'cancel'])

const form = reactive({
  lat: props.initialLat,
  lng: props.initialLng,
  point_type: props.pointType || 'TP',
  notes: '',
})

const hasCoordinate = computed(() => form.lat != null && form.lng != null && !Number.isNaN(form.lat) && !Number.isNaN(form.lng))

const mapContainer = ref(null)
const mapStyleMode = ref('street')
const hoverCoord = ref(null)
let map = null
let marker = null

const DEFAULT_CENTER = INDONESIA_CENTER // [lng, lat]
const DEFAULT_ZOOM = 4

function placeMarker(lat, lng) {
  if (!map) return
  if (marker) marker.remove()
  marker = new maplibregl.Marker({ draggable: true, color: '#3B82F6' })
    .setLngLat([lng, lat])
    .addTo(map)
  marker.on('dragend', () => {
    const pos = marker.getLngLat()
    form.lat = Number(pos.lat.toFixed(8))
    form.lng = Number(pos.lng.toFixed(8))
  })
}

function syncMarkerFromInputs() {
  if (!hasCoordinate.value) return
  placeMarker(form.lat, form.lng)
  map?.flyTo({ center: [form.lng, form.lat], zoom: Math.max(map.getZoom(), 12) })
}

function setMapStyle(mode) {
  if (mapStyleMode.value === mode || !map) return
  mapStyleMode.value = mode
  map.setStyle(MAP_STYLES[mode])
}
onMounted(() => {
  const hasInitial = props.initialLat != null && props.initialLng != null
  map = new maplibregl.Map({
    container: mapContainer.value,
    style: MAP_STYLES[mapStyleMode.value],
    center: hasInitial ? [props.initialLng, props.initialLat] : DEFAULT_CENTER,
    zoom: hasInitial ? 14 : DEFAULT_ZOOM,
  })
  map.addControl(new maplibregl.NavigationControl(), 'top-right')

  map.on('load', () => {
    if (hasInitial) placeMarker(props.initialLat, props.initialLng)
  })

  map.on('click', (e) => {
    form.lat = Number(e.lngLat.lat.toFixed(8))
    form.lng = Number(e.lngLat.lng.toFixed(8))
    placeMarker(form.lat, form.lng)
  })

  map.on('mousemove', (e) => {
    hoverCoord.value = { lat: e.lngLat.lat, lng: e.lngLat.lng }
  })
  map.on('mouseout', () => { hoverCoord.value = null })
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})

// ── Nominatim search ─────────────────────────────────────────
const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)

async function searchLocation() {
  if (!searchQuery.value.trim()) return
  searching.value = true
  searchResults.value = []
  try {
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(searchQuery.value)}&format=json&limit=5&countrycodes=id`
    const res = await fetch(url, { headers: { 'Accept-Language': 'id' } })
    searchResults.value = await res.json()
  } catch (e) {
    searchResults.value = []
  } finally {
    searching.value = false
  }
}

function selectSearchResult(r) {
  form.lat = Number(parseFloat(r.lat).toFixed(8))
  form.lng = Number(parseFloat(r.lon).toFixed(8))
  placeMarker(form.lat, form.lng)
  map?.flyTo({ center: [form.lng, form.lat], zoom: 14 })
  searchResults.value = []
  searchQuery.value = r.display_name
}

// ── Browser Geolocation ──────────────────────────────────────
const locating = ref(false)
const geoError = ref(null)

function useMyLocation() {
  if (!navigator.geolocation) {
    geoError.value = 'Geolocation tidak didukung browser ini.'
    return
  }
  locating.value = true
  geoError.value = null
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      form.lat = Number(pos.coords.latitude.toFixed(8))
      form.lng = Number(pos.coords.longitude.toFixed(8))
      placeMarker(form.lat, form.lng)
      map?.flyTo({ center: [form.lng, form.lat], zoom: 15 })
      locating.value = false
    },
    (err) => {
      geoError.value = 'Gagal mendapatkan lokasi: ' + err.message
      locating.value = false
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

function save() {
  if (!hasCoordinate.value) return
  emit('save', {
    lat: form.lat,
    lng: form.lng,
    point_type: form.point_type,
    notes: form.notes,
  })
}
</script>
