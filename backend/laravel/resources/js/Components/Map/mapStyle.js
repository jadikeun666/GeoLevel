// Shared MapLibre style configs for all map components in GeoLevel.
//
// STREET_STYLE uses CARTO Basemaps (Voyager) instead of tile.openstreetmap.org
// because the official OSM tile server does not reliably send the
// `Access-Control-Allow-Origin` CORS header, which MapLibre GL (WebGL-based)
// requires to load tiles as textures. CARTO Basemaps: free forever, no API
// key/signup required, explicit CORS support.
// https://github.com/CartoDB/basemap-styles
//
// SATELLITE_STYLE uses Esri World Imagery — free forever, no API key/signup
// required for standard usage, explicit CORS support. Real aerial/satellite
// photography (not a vector/street map).
// https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer

export const INDONESIA_CENTER = [118.0, -2.5]

const CARTO_ATTRIBUTION =
  '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors ' +
  '© <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>'

const ESRI_ATTRIBUTION =
  'Tiles © <a href="https://www.esri.com" target="_blank" rel="noopener">Esri</a> — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community'

export const STREET_STYLE = {
  version: 8,
  sources: {
    carto: {
      type: 'raster',
      tiles: [
        'https://a.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        'https://b.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        'https://c.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        'https://d.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
      ],
      tileSize: 256,
      attribution: CARTO_ATTRIBUTION,
    },
  },
  layers: [{ id: 'carto', type: 'raster', source: 'carto' }],
}

export const SATELLITE_STYLE = {
  version: 8,
  sources: {
    esri: {
      type: 'raster',
      tiles: [
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      ],
      tileSize: 256,
      attribution: ESRI_ATTRIBUTION,
    },
  },
  layers: [{ id: 'esri', type: 'raster', source: 'esri' }],
}

export const MAP_STYLES = {
  street: STREET_STYLE,
  satellite: SATELLITE_STYLE,
}
