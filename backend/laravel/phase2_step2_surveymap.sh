#!/bin/bash
set -e

echo "=========================================="
echo "Fase 2 — Step 2: Komponen SurveyMap.vue + integrasi minimal ke Show.vue"
echo "=========================================="

if [ ! -f "artisan" ]; then
    echo "ERROR: jalankan dari root project Laravel (~/workspace/geolevel/backend/laravel)"
    exit 1
fi

mkdir -p resources/js/Components/Map

echo ""
echo "--- Menulis SurveyMap.vue ---"

cat > resources/js/Components/Map/SurveyMap.vue << 'VUEEOF'
<template>
  <div class="relative">
    <div ref="mapContainer" class="w-full h-[500px] rounded-lg border border-slate-200 bg-slate-100"></div>

    <div v-if="points.length === 0"
         class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-lg pointer-events-none">
      <p class="text-xs text-slate-400 font-mono">Belum ada titik dengan koordinat</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import maplibregl from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'

const props = defineProps({
  points: { type: Array, default: () => [] },
  elevations:    { type: Array, default: () => [] },
  networkLegs:   { type: Array, default: () => [] },
  projectStatus: { type: String, default: 'draft' },
  canEdit:       { type: Boolean, default: false },
})

const emit = defineEmits(['point-clicked', 'request-add-point'])

const mapContainer = ref(null)
let map = null
let markers = []

const MARKER_STYLE = {
  BM: { color: '#F59E0B', size: 30 },
  TP: { color: '#3B82F6', size: 24 },
  IS: { color: '#10B981', size: 20 },
  CP: { color: '#8B5CF6', size: 24 },
}

const INDONESIA_CENTER = [118.0, -2.5]

function clearMarkers() {
  markers.forEach(m => m.remove())
  markers = []
}

function renderMarkers() {
  clearMarkers()

  props.points.forEach(point => {
    const style = MARKER_STYLE[point.point_type] || MARKER_STYLE.TP

    const el = document.createElement('div')
    el.style.width = `${style.size}px`
    el.style.height = `${style.size}px`
    el.style.borderRadius = '50%'
    el.style.background = style.color
    el.style.border = '2px solid white'
    el.style.boxShadow = '0 1px 4px rgba(0,0,0,0.3)'
    el.style.cursor = 'pointer'

    const marker = new maplibregl.Marker({ element: el })
      .setLngLat([Number(point.lng), Number(point.lat)])
      .addTo(map)

    el.addEventListener('click', () => {
      emit('point-clicked', { point_name: point.point_name })
    })

    markers.push(marker)
  })

  fitToMarkers()
}

function fitToMarkers() {
  if (!map) return

  if (props.points.length === 0) {
    map.jumpTo({ center: INDONESIA_CENTER, zoom: 4 })
    return
  }

  if (props.points.length === 1) {
    const p = props.points[0]
    map.jumpTo({ center: [Number(p.lng), Number(p.lat)], zoom: 15 })
    return
  }

  const bounds = new maplibregl.LngLatBounds()
  props.points.forEach(p => bounds.extend([Number(p.lng), Number(p.lat)]))
  map.fitBounds(bounds, { padding: 60, maxZoom: 17 })
}

onMounted(() => {
  map = new maplibregl.Map({
    container: mapContainer.value,
    style: {
      version: 8,
      sources: {
        osm: {
          type: 'raster',
          tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
          tileSize: 256,
          attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        },
      },
      layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
    },
    center: INDONESIA_CENTER,
    zoom: 4,
  })

  map.addControl(new maplibregl.NavigationControl(), 'top-right')

  map.on('load', () => {
    renderMarkers()
  })

  if (props.canEdit) {
    map.on('click', (e) => {
      emit('request-add-point', { lat: e.lngLat.lat, lng: e.lngLat.lng })
    })
  }
})

onBeforeUnmount(() => {
  clearMarkers()
  if (map) {
    map.remove()
    map = null
  }
})

watch(() => props.points, () => {
  if (map && map.loaded()) {
    renderMarkers()
  }
}, { deep: true })
</script>
VUEEOF

echo "SurveyMap.vue berhasil ditulis."

echo ""
echo "--- Patch Show.vue: import SurveyMap + prop surveyPoints + tab 'peta' ---"

python3 << 'PYEOF'
path = "resources/js/Pages/Projects/Show.vue"

with open(path, "rb") as f:
    raw = f.read()
raw = raw.replace(b"\r\n", b"\n")
content = raw.decode("utf-8")

changed = False

# 1. Tambah import SurveyMap setelah import Field
old_import = "import Field from '@/Components/Field.vue'"
if "SurveyMap" not in content:
    new_import = old_import + "\nimport SurveyMap from '@/Components/Map/SurveyMap.vue'"
    if old_import not in content:
        print("GAGAL: baris import Field tidak ditemukan.")
        raise SystemExit(1)
    content = content.replace(old_import, new_import, 1)
    changed = True
    print("  Import SurveyMap ditambahkan.")
else:
    print("  Import SurveyMap sudah ada, dilewati.")

# 2. Tambah surveyPoints ke defineProps
old_props = """const props = defineProps({
  project:      { type: Object, required: true },
  readings:     { type: Array,  default: () => [] },
  elevations:   { type: Array,  default: () => [] },
  activityLogs: { type: Array,  default: () => [] },
  networkLegs:  { type: Array,  default: () => [] },
})"""

new_props = """const props = defineProps({
  project:      { type: Object, required: true },
  readings:     { type: Array,  default: () => [] },
  elevations:   { type: Array,  default: () => [] },
  activityLogs: { type: Array,  default: () => [] },
  networkLegs:  { type: Array,  default: () => [] },
  surveyPoints: { type: Array,  default: () => [] },
})"""

if "surveyPoints:" not in content:
    if old_props not in content:
        print("GAGAL: pola defineProps tidak ditemukan persis.")
        raise SystemExit(1)
    content = content.replace(old_props, new_props, 1)
    changed = True
    print("  Prop surveyPoints ditambahkan ke defineProps.")
else:
    print("  Prop surveyPoints sudah ada, dilewati.")

# 3. Tambah tab 'peta' ke array tabs
old_tabs = """const tabs = [
  { id: 'bacaan',    label: 'Bacaan' },
  { id: 'elevasi',   label: 'Elevasi' },
  { id: 'jaring',    label: 'Jaring' },
  { id: 'grafik',    label: 'Grafik' },
  { id: 'aktivitas', label: 'Aktivitas' },
]"""

new_tabs = """const tabs = [
  { id: 'bacaan',    label: 'Bacaan' },
  { id: 'elevasi',   label: 'Elevasi' },
  { id: 'jaring',    label: 'Jaring' },
  { id: 'peta',      label: 'Peta' },
  { id: 'grafik',    label: 'Grafik' },
  { id: 'aktivitas', label: 'Aktivitas' },
]"""

if "id: 'peta'" not in content:
    if old_tabs not in content:
        print("GAGAL: pola array tabs tidak ditemukan persis.")
        raise SystemExit(1)
    content = content.replace(old_tabs, new_tabs, 1)
    changed = True
    print("  Tab 'peta' ditambahkan ke array tabs.")
else:
    print("  Tab 'peta' sudah ada, dilewati.")

# 4. Tambah blok konten tab Peta — versi minimal untuk uji visual dulu.
#    Disisipkan tepat sebelum blok tab 'grafik' agar urutan DOM konsisten
#    dengan urutan tombol tab.
anchor = '''      <!-- ── Tab: Grafik ──────────────────────────────────────── -->
      <div v-show="tab === 'grafik'" class="px-6 py-5 space-y-4">'''

peta_block = '''      <!-- ── Tab: Peta ────────────────────────────────────────── -->
      <div v-show="tab === 'peta'" class="px-6 py-5">
        <div class="mb-4">
          <h2 class="text-sm font-bold text-slate-700">Peta Survei</h2>
          <p class="text-xs text-slate-400 font-mono mt-0.5">
            {{ surveyPoints.length }} dari {{ uniquePointNames.length }} titik punya koordinat
          </p>
        </div>

        <SurveyMap
          :points="surveyPoints"
          :elevations="elevations"
          :network-legs="networkLegs"
          :project-status="project.status"
          :can-edit="false"
        />
      </div>

'''

if 'tab === \'peta\'' not in content or 'SurveyMap' not in content.split('<script')[0]:
    if anchor not in content:
        print("GAGAL: anchor blok tab Grafik tidak ditemukan persis.")
        raise SystemExit(1)
    # Hanya sisipkan sekali — cek dulu apakah blok konten peta sudah ada
    if '<!-- ── Tab: Peta' not in content:
        content = content.replace(anchor, peta_block + anchor, 1)
        changed = True
        print("  Blok konten tab Peta ditambahkan.")
    else:
        print("  Blok konten tab Peta sudah ada, dilewati.")
else:
    print("  Blok konten tab Peta sudah ada, dilewati.")

if changed:
    with open(path, "w", newline="\n", encoding="utf-8") as f:
        f.write(content)
    print("File Show.vue berhasil ditulis ulang.")
else:
    print("Tidak ada perubahan (semua sudah terpatch sebelumnya).")
PYEOF

echo ""
echo "--- Build ulang untuk cek error ---"
npm run build

echo ""
echo "=========================================="
echo "SELESAI. Buka tab 'Peta' di salah satu project di browser."
echo "Jika peta muncul (walau kosong tanpa titik), Step 2 berhasil."
echo "Tempel hasil build + screenshot/deskripsi tampilan ke Claude."
echo "=========================================="