<template>
  <div class="relative" :style="{ height }">
    <div ref="mapContainer" class="w-full h-full rounded-lg border border-slate-200 bg-slate-100"></div>

    <div class="absolute top-3 left-3 z-10 flex rounded-lg overflow-hidden border border-slate-200 shadow-sm bg-white text-xs font-medium">
      <button @click="setMapStyle('street')" type="button"
        :class="mapStyleMode === 'street' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
        class="px-2.5 py-1.5">Peta</button>
      <button @click="setMapStyle('satellite')" type="button"
        :class="mapStyleMode === 'satellite' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
        class="px-2.5 py-1.5 border-l border-slate-200">Satelit</button>
    </div>

    <div v-if="tracks.length || routes.length || legendItems.length"
         class="absolute bottom-6 left-3 z-10 bg-white/95 border border-slate-200 rounded-lg shadow-sm px-2.5 py-2 text-[11px] text-slate-600 space-y-1">
      <div v-if="tracks.length" class="flex items-center gap-2">
        <span class="inline-block w-5 rounded" :style="{ background: TRACK_COLOR, height: '3px' }"></span>
        Track
      </div>
      <div v-if="routes.length" class="flex items-center gap-2">
        <span class="inline-block w-5 rounded" :style="{ background: ROUTE_COLOR, height: '3px' }"></span>
        Rute
      </div>
      <div v-for="item in legendItems" :key="item.category" class="flex items-center gap-2">
        <span class="inline-block w-2.5 h-2.5 rounded-full border border-white shadow"
              :style="{ background: item.color }"></span>
        {{ item.label }}
      </div>
    </div>

    <div v-if="tracks.length === 0 && waypoints.length === 0 && liveTrace.length === 0 && !livePosition && routes.length === 0 && routeDraft.length === 0"
         class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-lg pointer-events-none">
      <p class="text-xs text-slate-400 font-mono">Belum ada track atau waypoint</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import maplibregl from 'maplibre-gl'
import { MAP_STYLES, INDONESIA_CENTER } from '@/Components/Map/mapStyle'
import { TRACK_COLOR, ROUTE_COLOR, categoryColor, categoryLabel } from './outdoorCategories'

// Contract (docs/outdoor.md, bagian 7):
//   props : tracks    Array<{ id, name, coordinates: [[lng, lat], ...] }>
//           waypoints Array<{ id, name, lat, lng, category }>  (lat/lng NUMERIC strings)
//           selectedTrackId Number|null, fitToData Boolean, height String
//   emits : select-track (id), select-waypoint (id)
//   live  : liveTrace Array<[lng, lat]>, livePosition { lat, lng } | null (docs 17.6).
//           Parent must pass a NEW array per update (shallow watch); no auto fitBounds.
const props = defineProps({
  tracks:          { type: Array,   default: () => [] },
  waypoints:       { type: Array,   default: () => [] },
  selectedTrackId: { type: Number,  default: null },
  fitToData:       { type: Boolean, default: true },
  height:          { type: String,  default: '500px' },
  liveTrace:       { type: Array,   default: () => [] },
  livePosition:    { type: Object,  default: null },
  routes:          { type: Array,   default: () => [] },
  routeDraft:      { type: Array,   default: () => [] },
  drawRoute:       { type: Boolean, default: false },
})

const emit = defineEmits(['select-track', 'select-waypoint', 'add-route-vertex'])

const SOURCE_TRACKS   = 'outdoor-tracks'
const LAYER_TRACKS    = 'outdoor-tracks-line'
const LAYER_TRACKS_HIT = 'outdoor-tracks-hit'
const SOURCE_WAYPOINTS = 'outdoor-waypoints'
const LAYER_WAYPOINTS  = 'outdoor-waypoints-circle'
const SOURCE_LIVE_TRACK = 'outdoor-live-track'
const LAYER_LIVE_TRACK  = 'outdoor-live-track-line'
const SOURCE_LIVE_POS   = 'outdoor-live-position'
const LAYER_LIVE_POS    = 'outdoor-live-position-circle'
const SOURCE_ROUTES      = 'outdoor-routes'
const LAYER_ROUTES       = 'outdoor-routes-line'
const SOURCE_ROUTE_DRAFT = 'outdoor-route-draft'
const LAYER_ROUTE_DRAFT  = 'outdoor-route-draft-line'
const SOURCE_ROUTE_VERTS = 'outdoor-route-draft-vertices'
const LAYER_ROUTE_VERTS  = 'outdoor-route-draft-vertices-circle'

const mapContainer = ref(null)
const mapStyleMode = ref('street')
let map = null

const legendItems = computed(() => {
  const seen = new Set(props.waypoints.map(w => w.category))
  return [...seen].map(category => ({
    category,
    label: categoryLabel(category),
    color: categoryColor(category),
  }))
})

function trackFeatures() {
  return props.tracks
    .filter(t => Array.isArray(t.coordinates) && t.coordinates.length >= 2)
    .map(t => ({
      type: 'Feature',
      properties: { id: t.id, name: t.name, selected: t.id === props.selectedTrackId ? 1 : 0 },
      geometry: { type: 'LineString', coordinates: t.coordinates },
    }))
}

function waypointFeatures() {
  const features = []
  for (const w of props.waypoints) {
    // lat/lng arrive as NUMERIC strings; converted only here, for rendering.
    const lng = Number(w.lng)
    const lat = Number(w.lat)
    if (!Number.isFinite(lng) || !Number.isFinite(lat)) continue
    features.push({
      type: 'Feature',
      properties: { id: w.id, name: w.name, category: w.category, color: categoryColor(w.category) },
      geometry: { type: 'Point', coordinates: [lng, lat] },
    })
  }
  return features
}

function renderAll() {
  if (!map) return

  const trackData = { type: 'FeatureCollection', features: trackFeatures() }
  const waypointData = { type: 'FeatureCollection', features: waypointFeatures() }

  if (map.getSource(SOURCE_TRACKS)) {
    map.getSource(SOURCE_TRACKS).setData(trackData)
  } else {
    map.addSource(SOURCE_TRACKS, { type: 'geojson', data: trackData })
    // Wide transparent layer = easier click target on thin lines.
    map.addLayer({
      id: LAYER_TRACKS_HIT,
      type: 'line',
      source: SOURCE_TRACKS,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': TRACK_COLOR, 'line-width': 16, 'line-opacity': 0 },
    })
    map.addLayer({
      id: LAYER_TRACKS,
      type: 'line',
      source: SOURCE_TRACKS,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': TRACK_COLOR,
        'line-width': ['case', ['==', ['get', 'selected'], 1], 5, 3],
      },
    })
  }

  if (map.getSource(SOURCE_WAYPOINTS)) {
    map.getSource(SOURCE_WAYPOINTS).setData(waypointData)
  } else {
    map.addSource(SOURCE_WAYPOINTS, { type: 'geojson', data: waypointData })
    map.addLayer({
      id: LAYER_WAYPOINTS,
      type: 'circle',
      source: SOURCE_WAYPOINTS,
      paint: {
        'circle-radius': 6,
        'circle-color': ['get', 'color'],
        'circle-stroke-width': 2,
        'circle-stroke-color': '#FFFFFF',
      },
    })
  }

  renderLive()
  renderRoutes()
}

// Live recording overlay (docs 17.6): separate sources/layers from saved tracks.
function renderLive() {
  const trackData = {
    type: 'FeatureCollection',
    features: props.liveTrace.length >= 2
      ? [{ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: props.liveTrace } }]
      : [],
  }
  const posValid = props.livePosition
    && Number.isFinite(Number(props.livePosition.lng))
    && Number.isFinite(Number(props.livePosition.lat))
  const posData = {
    type: 'FeatureCollection',
    features: posValid
      ? [{ type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: [Number(props.livePosition.lng), Number(props.livePosition.lat)] } }]
      : [],
  }

  if (map.getSource(SOURCE_LIVE_TRACK)) {
    map.getSource(SOURCE_LIVE_TRACK).setData(trackData)
  } else {
    map.addSource(SOURCE_LIVE_TRACK, { type: 'geojson', data: trackData })
    map.addLayer({
      id: LAYER_LIVE_TRACK,
      type: 'line',
      source: SOURCE_LIVE_TRACK,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': TRACK_COLOR, 'line-width': 4, 'line-dasharray': [2, 1.5] },
    })
  }

  if (map.getSource(SOURCE_LIVE_POS)) {
    map.getSource(SOURCE_LIVE_POS).setData(posData)
  } else {
    map.addSource(SOURCE_LIVE_POS, { type: 'geojson', data: posData })
    map.addLayer({
      id: LAYER_LIVE_POS,
      type: 'circle',
      source: SOURCE_LIVE_POS,
      paint: {
        'circle-radius': 8,
        'circle-color': TRACK_COLOR,
        'circle-stroke-width': 3,
        'circle-stroke-color': '#FFFFFF',
      },
    })
  }
}

function renderRoutes() {
  const lines = {
    type: 'FeatureCollection',
    features: props.routes
      .filter(r => Array.isArray(r.coordinates) && r.coordinates.length >= 2)
      .map(r => ({
        type: 'Feature',
        properties: { id: r.id, name: r.name },
        geometry: { type: 'LineString', coordinates: r.coordinates },
      })),
  }
  const draftLine = {
    type: 'FeatureCollection',
    features: props.routeDraft.length >= 2
      ? [{ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: props.routeDraft } }]
      : [],
  }
  const draftPoints = {
    type: 'FeatureCollection',
    features: props.routeDraft.map(c => ({
      type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: c },
    })),
  }

  if (map.getSource(SOURCE_ROUTES)) {
    map.getSource(SOURCE_ROUTES).setData(lines)
  } else {
    map.addSource(SOURCE_ROUTES, { type: 'geojson', data: lines })
    map.addLayer({
      id: LAYER_ROUTES, type: 'line', source: SOURCE_ROUTES,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': ROUTE_COLOR, 'line-width': 3 },
    })
  }

  if (map.getSource(SOURCE_ROUTE_DRAFT)) {
    map.getSource(SOURCE_ROUTE_DRAFT).setData(draftLine)
  } else {
    map.addSource(SOURCE_ROUTE_DRAFT, { type: 'geojson', data: draftLine })
    map.addLayer({
      id: LAYER_ROUTE_DRAFT, type: 'line', source: SOURCE_ROUTE_DRAFT,
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: { 'line-color': ROUTE_COLOR, 'line-width': 4, 'line-dasharray': [2, 1.5] },
    })
  }

  if (map.getSource(SOURCE_ROUTE_VERTS)) {
    map.getSource(SOURCE_ROUTE_VERTS).setData(draftPoints)
  } else {
    map.addSource(SOURCE_ROUTE_VERTS, { type: 'geojson', data: draftPoints })
    map.addLayer({
      id: LAYER_ROUTE_VERTS, type: 'circle', source: SOURCE_ROUTE_VERTS,
      paint: {
        'circle-radius': 5,
        'circle-color': ROUTE_COLOR,
        'circle-stroke-width': 2,
        'circle-stroke-color': '#FFFFFF',
      },
    })
  }
}

function refresh() {
  waitForStyleReady(renderAll)
}

function fitTo(coordinates) {
  if (!map || coordinates.length === 0) return
  const bounds = new maplibregl.LngLatBounds(coordinates[0], coordinates[0])
  for (const c of coordinates) bounds.extend(c)
  map.fitBounds(bounds, { padding: 48, maxZoom: 16, duration: 0 })
}

function allCoordinates() {
  const coords = []
  for (const t of props.tracks) {
    if (Array.isArray(t.coordinates)) coords.push(...t.coordinates)
  }
  for (const f of waypointFeatures()) coords.push(f.geometry.coordinates)
  return coords
}

function fitToSelectedTrack() {
  const track = props.tracks.find(t => t.id === props.selectedTrackId)
  if (track && Array.isArray(track.coordinates) && track.coordinates.length) {
    fitTo(track.coordinates)
  }
}

function onMapClick(e) {
  if (props.drawRoute) {
    emit('add-route-vertex', { lat: e.lngLat.lat, lng: e.lngLat.lng })
    return
  }
  const layers = [LAYER_WAYPOINTS, LAYER_TRACKS_HIT].filter(id => map.getLayer(id))
  if (layers.length === 0) return
  const features = map.queryRenderedFeatures(e.point, { layers })
  if (features.length === 0) return

  const waypoint = features.find(f => f.layer.id === LAYER_WAYPOINTS)
  if (waypoint) {
    emit('select-waypoint', waypoint.properties.id)
    return
  }
  emit('select-track', features[0].properties.id)
}

function onMapMove(e) {
  if (props.drawRoute) {
    map.getCanvas().style.cursor = 'crosshair'
    return
  }
  const layers = [LAYER_WAYPOINTS, LAYER_TRACKS_HIT].filter(id => map.getLayer(id))
  const hit = layers.length > 0 && map.queryRenderedFeatures(e.point, { layers }).length > 0
  map.getCanvas().style.cursor = hit ? 'pointer' : ''
}

function waitForStyleReady(callback, deadline = performance.now() + 10000) {
  // Polling, bukan mengandalkan satu event 'style.load' (lihat docs/claude.md → Known Fixes).
  if (!map) return
  if (map.isStyleLoaded()) {
    callback()
    return
  }
  if (performance.now() >= deadline) {
    console.warn('Style belum sepenuhnya siap setelah 10 detik; mencoba render layer tetap.')
    try {
      callback()
    } catch (err) {
      console.warn('Gagal render layer setelah timeout style:', err)
    }
    return
  }
  requestAnimationFrame(() => waitForStyleReady(callback, deadline))
}

function setMapStyle(mode) {
  if (mapStyleMode.value === mode || !map) return
  mapStyleMode.value = mode
  map.setStyle(MAP_STYLES[mode])
  refresh()
}

// Re-fit only when the SET of items changes, not when selection/names change.
const dataSignature = computed(() =>
  props.tracks.map(t => `t${t.id}:${t.coordinates?.length ?? 0}`).join('|')
  + '#' + props.waypoints.map(w => `w${w.id}`).join('|')
)

watch(() => props.tracks, refresh)
watch(() => props.waypoints, refresh, { deep: true })
watch(() => props.liveTrace, refresh)
watch(() => props.routes, refresh)
watch(() => props.routeDraft, refresh)
watch(() => props.drawRoute, (on) => { if (map && !on) map.getCanvas().style.cursor = '' })
watch(() => props.livePosition, refresh, { deep: true })
watch(() => props.selectedTrackId, () => {
  refresh()
  fitToSelectedTrack()
})
watch(dataSignature, () => {
  if (props.fitToData && !props.selectedTrackId) fitTo(allCoordinates())
  else if (props.selectedTrackId) fitToSelectedTrack()
})

onMounted(() => {
  map = new maplibregl.Map({
    container: mapContainer.value,
    style: MAP_STYLES[mapStyleMode.value],
    center: INDONESIA_CENTER,
    zoom: 4,
  })

  map.addControl(new maplibregl.NavigationControl(), 'top-right')

  map.on('load', () => {
    renderAll()
    if (props.selectedTrackId) fitToSelectedTrack()
    else if (props.fitToData) fitTo(allCoordinates())
  })
  map.on('click', onMapClick)
  map.on('mousemove', onMapMove)
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
  }
})
</script>
