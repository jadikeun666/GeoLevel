// Waypoint categories and colors for Outdoor features (docs/outdoor.md).
// Keep the keys in sync with config('geolevel.outdoor.waypoint_categories').
// Colors are deliberately distinct from survey point types
// (BM #F59E0B, TP #3B82F6, IS #10B981, CP #8B5CF6) and the survey polyline (#DC2626).

export const TRACK_COLOR = '#0EA5E9'
export const ROUTE_COLOR = '#F97316'

export const CATEGORY_META = {
  generic:       { label: 'Umum',       color: '#64748B' },
  survey_marker: { label: 'Titik Ukur', color: '#EA580C' },
  camp:          { label: 'Kemah',      color: '#65A30D' },
  water:         { label: 'Air',        color: '#0891B2' },
  danger:        { label: 'Bahaya',     color: '#E11D48' },
  summit:        { label: 'Puncak',     color: '#7C3AED' },
  parking:       { label: 'Parkir',     color: '#475569' },
}

export const CATEGORY_OPTIONS = Object.entries(CATEGORY_META).map(([value, meta]) => ({
  value,
  label: meta.label,
}))

export function categoryColor(category) {
  return (CATEGORY_META[category] ?? CATEGORY_META.generic).color
}

export function categoryLabel(category) {
  return (CATEGORY_META[category] ?? CATEGORY_META.generic).label
}
