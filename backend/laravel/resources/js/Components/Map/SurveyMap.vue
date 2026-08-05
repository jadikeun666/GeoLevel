<template>
  <div class="relative">
    <div ref="mapContainer" class="w-full h-[500px] rounded-lg border border-slate-200 bg-slate-100"></div>
    <div class="absolute top-3 left-3 z-10 flex rounded-lg overflow-hidden border border-slate-200 shadow-sm bg-white text-xs font-medium">
      <button @click="setMapStyle('street')" type="button"
        :class="mapStyleMode === 'street' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
        class="px-2.5 py-1.5">Peta</button>
      <button @click="setMapStyle('satellite')" type="button"
        :class="mapStyleMode === 'satellite' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
        class="px-2.5 py-1.5 border-l border-slate-200">Satelit</button>
    </div>

    <div v-if="points.length === 0"
         class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-lg pointer-events-none">
      <p class="text-xs text-slate-400 font-mono">Belum ada titik dengan koordinat</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import maplibregl from 'maplibre-gl'
import { MAP_STYLES, INDONESIA_CENTER } from './mapStyle'

const props = defineProps({
  points: { type: Array, default: () => [] },
  elevations:    { type: Array, default: () => [] },
  networkLegs:   { type: Array, default: () => [] },
  projectStatus: { type: String, default: 'draft' },
  canEdit:       { type: Boolean, default: false },
})

const emit = defineEmits(['point-clicked', 'request-add-point', 'edit-point-requested'])

const mapContainer = ref(null)
const mapStyleMode = ref('street')
let map = null
let markers = []

const MARKER_STYLE = {
  BM: { color: '#F59E0B', size: 30 },
  TP: { color: '#3B82F6', size: 24 },
  IS: { color: '#10B981', size: 20 },
  CP: { color: '#8B5CF6', size: 24 },
}


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

    const popup = new maplibregl.Popup({ offset: 16, closeButton: true, maxWidth: '240px' })
      .setHTML(buildPopupHtml(point))

    popup.on('open', () => {
      const btn = popup.getElement()?.querySelector(`[data-popup-edit="${point.id}"]`)
      btn?.addEventListener('click', () => {
        emit('edit-point-requested', point)
        popup.remove()
      })
    })

    const marker = new maplibregl.Marker({ element: el })
      .setLngLat([Number(point.lng), Number(point.lat)])
      .setPopup(popup)
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

function findElevation(pointName) {
  const matches = props.elevations.filter(e => e.point_name === pointName)
  if (matches.length === 0) return null
  return matches.reduce((latest, e) =>
    (e.sequence_no ?? -1) > (latest.sequence_no ?? -1) ? e : latest
  )
}

function fmt(value, decimals = 4) {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  return Number.isFinite(n) ? n.toFixed(decimals) : '—'
}

function buildPopupHtml(point) {
  const elev = findElevation(point.point_name)
  const typeLabel = { BM: 'Benchmark', TP: 'Titik Ukur', IS: 'Titik Antara', CP: 'Titik Kontrol' }[point.point_type] || point.point_type

  return `
    <div style="font-family:inherit;font-size:12px;line-height:1.5;min-width:180px">
      <div style="font-weight:700;font-size:13px;margin-bottom:2px">${point.point_name}</div>
      <div style="color:#64748B;margin-bottom:6px">${typeLabel}</div>
      <table style="width:100%;border-collapse:collapse">
        <tr><td style="color:#64748B;padding:1px 8px 1px 0">Elevasi</td><td style="text-align:right;font-family:monospace">${elev ? fmt(elev.adjusted_elevation) + ' m' : '—'}</td></tr>
        <tr><td style="color:#64748B;padding:1px 8px 1px 0">Koreksi</td><td style="text-align:right;font-family:monospace">${elev ? fmt(elev.correction, 6) + ' m' : '—'}</td></tr>
        <tr><td style="color:#64748B;padding:1px 8px 1px 0">Jarak kumulatif</td><td style="text-align:right;font-family:monospace">${elev ? fmt(elev.cumulative_distance, 3) + ' m' : '—'}</td></tr>
        <tr><td style="color:#64748B;padding:1px 8px 1px 0">Koordinat</td><td style="text-align:right;font-family:monospace">${fmt(point.lat, 6)}, ${fmt(point.lng, 6)}</td></tr>
        <tr><td style="color:#64748B;padding:1px 8px 1px 0">Akurasi GPS</td><td style="text-align:right;font-family:monospace">${point.gps_accuracy_m != null ? fmt(point.gps_accuracy_m, 2) + ' m' : '—'}</td></tr>
      </table>
      <button type="button" data-popup-edit="${point.id}"
        style="margin-top:8px;width:100%;padding:5px 0;font-size:11px;font-weight:600;border-radius:6px;background:#F1F5F9;color:#334155;border:none;cursor:pointer">
        Edit Koordinat
      </button>
    </div>
  `
}

const NETWORK_LEG_COLOR = {
  draft: '#9CA3AF',
  calculated: '#3B82F6',
  accepted: '#10B981',
  rejected: '#EF4444',
}

function buildNetworkLegFeatures() {
  const coordByName = new Map()
  props.points.forEach(p => coordByName.set(p.point_name, [Number(p.lng), Number(p.lat)]))

  return props.networkLegs
    .map(leg => {
      const from = coordByName.get(leg.from_point)
      const to = coordByName.get(leg.to_point)
      if (!from || !to) return null
      return {
        type: 'Feature',
        properties: { from_point: leg.from_point, to_point: leg.to_point },
        geometry: { type: 'LineString', coordinates: [from, to] },
      }
    })
    .filter(Boolean)
}

function renderNetworkLegs() {
  if (!map) return

  const features = buildNetworkLegFeatures()
  const color = NETWORK_LEG_COLOR[props.projectStatus] || NETWORK_LEG_COLOR.draft

  try {
    if (map.getLayer('network-legs-line')) map.removeLayer('network-legs-line')
    if (map.getSource('network-legs')) map.removeSource('network-legs')
  } catch (err) {
    // aman diabaikan — source/layer memang belum ada
  }

  if (features.length === 0) return

  const addLayer = () => {
    if (!map) return
    try {
      if (!map.getSource('network-legs')) {
        map.addSource('network-legs', {
          type: 'geojson',
          data: { type: 'FeatureCollection', features },
        })
        map.addLayer({
          id: 'network-legs-line',
          type: 'line',
          source: 'network-legs',
          layout: { 'line-join': 'round', 'line-cap': 'round' },
          paint: {
            'line-color': color,
            'line-width': 2.5,
          },
        })
      }
    } catch (retryErr) {
      console.error('Gagal render network legs setelah retry:', retryErr)
    }
  }

  try {
    addLayer()
  } catch (err) {
    requestAnimationFrame(addLayer)
  }
}

function buildOrderedCoords() {
  const orderMap = new Map()
  props.elevations.forEach(e => {
    if (e.point_name != null && e.sequence_no != null) {
      orderMap.set(e.point_name, e.sequence_no)
    }
  })

  return props.points
    .filter(p => orderMap.has(p.point_name))
    .slice()
    .sort((a, b) => orderMap.get(a.point_name) - orderMap.get(b.point_name))
    .map(p => [Number(p.lng), Number(p.lat)])
}

function renderRoute() {
  if (!map) return

  const coords = buildOrderedCoords()

  // Selalu bersihkan source/layer lama lebih dulu, dibungkus try/catch —
  // ini menghindari kegagalan diam-diam saat toggle Peta/Satelit terjadi
  // di tengah proses loading style baru (state 'route-line' bisa jadi
  // tidak konsisten dengan map.getLayer() pada momen tertentu).
  try {
    if (map.getLayer('route-line')) map.removeLayer('route-line')
    if (map.getSource('route')) map.removeSource('route')
  } catch (err) {
    // aman diabaikan — source/layer memang belum ada
  }

  if (coords.length < 2) return

  try {
    map.addSource('route', {
      type: 'geojson',
      data: {
        type: 'Feature',
        geometry: { type: 'LineString', coordinates: coords },
      },
    })
    map.addLayer({
      id: 'route-line',
      type: 'line',
      source: 'route',
      layout: { 'line-join': 'round', 'line-cap': 'round' },
      paint: {
        'line-color': '#DC2626',
        'line-width': 3,
        'line-dasharray': [2, 1],
      },
    })
  } catch (err) {
    // Style belum sepenuhnya siap menerima source/layer baru — coba lagi
    // singkat setelah microtask berikutnya, alih-alih gagal permanen.
    requestAnimationFrame(() => {
      if (!map) return
      try {
        if (!map.getSource('route')) {
          map.addSource('route', {
            type: 'geojson',
            data: {
              type: 'Feature',
              geometry: { type: 'LineString', coordinates: coords },
            },
          })
          map.addLayer({
            id: 'route-line',
            type: 'line',
            source: 'route',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: {
              'line-color': '#DC2626',
              'line-width': 3,
              'line-dasharray': [2, 1],
            },
          })
        }
      } catch (retryErr) {
        console.error('Gagal render polyline setelah retry:', retryErr)
      }
    })
  }
}

function waitForStyleReady(callback, attemptsLeft = 40) {
  // Polling, bukan mengandalkan satu event 'style.load' — event itu
  // kadang sudah selesai fire sebelum listener sempat terpasang
  // (race condition nyata, terutama saat style sudah ter-cache browser
  // dan load-nya sangat cepat). Poll tiap frame sampai style benar-benar
  // siap menerima source/layer baru, dengan batas percobaan supaya
  // tidak infinite loop kalau map sudah di-unmount.
  if (!map) return
  if (map.isStyleLoaded()) {
    callback()
    return
  }
  if (attemptsLeft <= 0) {
    console.error('Timeout menunggu style siap — polyline/network legs mungkin tidak muncul.')
    return
  }
  requestAnimationFrame(() => waitForStyleReady(callback, attemptsLeft - 1))
}

function setMapStyle(mode) {
  if (mapStyleMode.value === mode || !map) return
  mapStyleMode.value = mode
  map.setStyle(MAP_STYLES[mode])
  waitForStyleReady(() => {
    renderRoute()
    renderNetworkLegs()
  })
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
    renderRoute()
    renderNetworkLegs()
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
    renderRoute()
    renderNetworkLegs()
  }
}, { deep: true })

watch(() => props.elevations, () => {
  if (map && map.loaded()) {
    renderRoute()
  }
}, { deep: true })

watch(() => props.networkLegs, () => {
  if (map && map.loaded()) {
    renderNetworkLegs()
  }
}, { deep: true })
</script>
