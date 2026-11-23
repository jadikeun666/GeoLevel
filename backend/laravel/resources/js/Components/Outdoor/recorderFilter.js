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

const toDeg = (rad) => (rad * 180) / Math.PI

/** Initial great-circle bearing in degrees [0, 360) from `from` to `to`. */
export function bearingDeg(from, to) {
  const p1 = toRad(from.lat)
  const p2 = toRad(to.lat)
  const dl = toRad(to.lng - from.lng)
  const y = Math.sin(dl) * Math.cos(p2)
  const x = Math.cos(p1) * Math.sin(p2) - Math.sin(p1) * Math.cos(p2) * Math.cos(dl)
  return (toDeg(Math.atan2(y, x)) + 360) % 360
}

/**
 * Speed (m/s) from the NET displacement across the last `windowS` seconds.
 * shortcut: net displacement underestimates speed on winding paths; used because GPS jitter at rest would inflate a path-length speed.
 */
export function movingSpeedMps(samples, nowMs, windowS = 60) {
  const recent = samples.filter((s) => s.t >= nowMs - windowS * 1000 && s.t <= nowMs)
  if (recent.length < 2) return null
  const first = recent[0]
  const last = recent[recent.length - 1]
  const dt = (last.t - first.t) / 1000
  return dt > 0 ? haversineM(first, last) / dt : null
}

/** Seconds to cover distanceM at speedMps; null when speed is unknown or below minSpeedMps (inclusive). */
export function etaSeconds(distanceM, speedMps, minSpeedMps) {
  if (typeof speedMps !== 'number' || !Number.isFinite(speedMps) || speedMps < minSpeedMps) return null
  return distanceM / speedMps
}
