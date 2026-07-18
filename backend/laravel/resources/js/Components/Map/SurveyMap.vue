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
