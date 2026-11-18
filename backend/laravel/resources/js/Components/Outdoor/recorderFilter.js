// Pure helpers for live recording (docs/outdoor.md, 17.3 and 17.5). No DOM, no Vue.
// Float is used only inside these functions (same precision exception as GeoMathService);
// values sent to the server are rounded there to the column scale.

// Same value as config('geolevel.earth_radius_m'). Client-side estimate only.
export const EARTH_RADIUS_M = 6371008.8

export const INITIAL_AUTO_PAUSE = Object.freeze({ autoPaused: false, slowSinceMs: null })

const toRad = (deg) => (deg * Math.PI) / 180
const num = (v) => (typeof v === 'number' && Number.isFinite(v) ? v : null)

/** Great-circle distance in meters between {lat, lng} points. */
export function haversineM(a, b, radius = EARTH_RADIUS_M) {
  const dLat = toRad(b.lat - a.lat)
  const dLng = toRad(b.lng - a.lng)
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2
  return 2 * radius * Math.asin(Math.min(1, Math.sqrt(h)))
}

/** Speed in m/s between two fixes {lat, lng, t(ms)}; null when time does not advance. */
export function speedBetween(prev, cur) {
  const dt = (cur.t - prev.t) / 1000
  if (!(dt > 0)) return null
  return haversineM(prev, cur) / dt
}

/**
 * Decide whether a fix {lat, lng, accuracy_m, t(ms)} becomes a recorded point.
 * Order: accuracy, then (if a previous accepted point exists) interval, then distance.
 * Thresholds are inclusive: accuracy <= max, interval >= min, distance >= min.
 */
export function evaluateFix(fix, lastAccepted, cfg) {
  const acc = fix.accuracy_m
  if (typeof acc !== 'number' || !Number.isFinite(acc) || acc > cfg.record_max_accuracy_m) {
    return { accept: false, reason: 'accuracy' }
  }
  if (!lastAccepted) return { accept: true, reason: 'ok' }
  if ((fix.t - lastAccepted.t) / 1000 < cfg.record_min_interval_s) {
    return { accept: false, reason: 'interval' }
  }
  if (haversineM(lastAccepted, fix) < cfg.record_min_distance_m) {
    return { accept: false, reason: 'distance' }
  }
  return { accept: true, reason: 'ok' }
}

/**
 * Client-only auto-pause. state = { autoPaused, slowSinceMs }; returns a NEW state.
 * Unknown speed (null/NaN) leaves the state unchanged.
 */
export function nextAutoPause(state, speedMps, nowMs, cfg) {
  if (typeof speedMps !== 'number' || !Number.isFinite(speedMps)) return state
  if (speedMps >= cfg.record_auto_pause_speed_mps) {
    return { autoPaused: false, slowSinceMs: null }
  }
  const slowSinceMs = state.slowSinceMs ?? nowMs
  const autoPaused = state.autoPaused || nowMs - slowSinceMs >= cfg.record_auto_pause_after_s * 1000
  return { autoPaused, slowSinceMs }
}

/** GeolocationPosition-like -> fix used by evaluateFix/speedBetween. */
export function toFix(position) {
  return {
    lat: position.coords.latitude,
    lng: position.coords.longitude,
    accuracy_m: num(position.coords.accuracy),
    t: position.timestamp,
  }
}

/** GeolocationPosition-like -> point payload for the batch endpoint (null, never 0, for unknowns). */
export function toPayloadPoint(position) {
  const c = position.coords
  return {
    lat: c.latitude,
    lng: c.longitude,
    ele: num(c.altitude),
    accuracy_m: num(c.accuracy),
    speed_mps: num(c.speed),
    heading_deg: num(c.heading),
    recorded_at: new Date(position.timestamp).toISOString(),
  }
}
