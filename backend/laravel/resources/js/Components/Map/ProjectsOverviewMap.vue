<template>
  <div class="relative">
    <div ref="mapContainer" class="w-full h-[280px] rounded-lg border border-slate-200 bg-slate-100"></div>
    <div class="absolute top-3 left-3 z-10 flex rounded-lg overflow-hidden border border-slate-200 shadow-sm bg-white text-xs font-medium">
      <button @click="setMapStyle('street')" type="button"
        :class="mapStyleMode === 'street' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
        class="px-2.5 py-1.5">Peta</button>
      <button @click="setMapStyle('satellite')" type="button"
        :class="mapStyleMode === 'satellite' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
        class="px-2.5 py-1.5 border-l border-slate-200">Satelit</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import maplibregl from 'maplibre-gl'
import { MAP_STYLES, INDONESIA_CENTER } from './mapStyle'

const props = defineProps({
  mapPoints: { type: Array, default: () => [] },
})

const STATUS_COLOR = {
  draft:      '#9CA3AF',
  calculated: '#3B82F6',
  accepted:   '#10B981',
  rejected:   '#EF4444',
}

const mapContainer = ref(null)
const mapStyleMode = ref('street')
let map = null
let markers = []

function clearMarkers() {
  markers.forEach(m => m.remove())
  markers = []
}

function renderMarkers() {
  clearMarkers()
  props.mapPoints.forEach(p => {
    const color = STATUS_COLOR[p.status] || STATUS_COLOR.draft
    const el = document.createElement('div')
    el.style.width = '26px'
    el.style.height = '26px'
    el.style.borderRadius = '50%'
    el.style.background = color
    el.style.border = '2px solid white'
    el.style.boxShadow = '0 1px 4px rgba(0,0,0,0.3)'
    el.style.cursor = 'pointer'
    el.title = `${p.project_name} (${p.point_count} titik)`

    const marker = new maplibregl.Marker({ element: el })
      .setLngLat([Number(p.center_lng), Number(p.center_lat)])
      .addTo(map)

    el.addEventListener('click', () => {
      router.visit(route('projects.show', p.project_id))
    })

    markers.push(marker)
  })
  fitToMarkers()
}

function fitToMarkers() {
  if (!map) return
  if (props.mapPoints.length === 0) {
    map.jumpTo({ center: INDONESIA_CENTER, zoom: 4 })
    return
  }
  if (props.mapPoints.length === 1) {
    const p = props.mapPoints[0]
    map.jumpTo({ center: [Number(p.center_lng), Number(p.center_lat)], zoom: 13 })
    return
  }
  const bounds = new maplibregl.LngLatBounds()
  props.mapPoints.forEach(p => bounds.extend([Number(p.center_lng), Number(p.center_lat)]))
  map.fitBounds(bounds, { padding: 50, maxZoom: 15 })
}

function setMapStyle(mode) {
  if (mapStyleMode.value === mode || !map) return
  mapStyleMode.value = mode
  map.setStyle(MAP_STYLES[mode])
}

onMounted(() => {
  map = new maplibregl.Map({
    container: mapContainer.value,
    style: MAP_STYLES[mapStyleMode.value],
    center: INDONESIA_CENTER,
    zoom: 4,
  })
  map.addControl(new maplibregl.NavigationControl(), 'top-right')
  map.on('load', () => {
    renderMarkers()
  })
})

onBeforeUnmount(() => {
  clearMarkers()
  if (map) {
    map.remove()
    map = null
  }
})

watch(() => props.mapPoints, () => {
  if (map && map.loaded()) {
    renderMarkers()
  }
}, { deep: true })
</script>
