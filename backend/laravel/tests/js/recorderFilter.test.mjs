// Run: node --test tests/js/   (node:test is built in, no dependencies)
// Expected values are hand-computable: 1 degree of latitude at R = 6371008.8 m is
// R * pi / 180 = 111195.080 m, so 0.00005 deg = 5.5598 m and 0.00004 deg = 4.4478 m.
import test from 'node:test'
import assert from 'node:assert/strict'
import {
  haversineM, speedBetween, evaluateFix, nextAutoPause, toFix, toPayloadPoint, INITIAL_AUTO_PAUSE,
} from '../../resources/js/Components/Outdoor/recorderFilter.js'
import * as queue from '../../resources/js/Components/Outdoor/recorderQueue.js'

const cfg = {
  record_min_distance_m: 5,
  record_min_interval_s: 2,
  record_max_accuracy_m: 30,
  record_auto_pause_speed_mps: 0.3,
  record_auto_pause_after_s: 30,
}
const near = (actual, expected, delta) =>
  assert.ok(Math.abs(actual - expected) <= delta, `expected ${expected} +/- ${delta}, got ${actual}`)

test('haversineM: one degree of latitude and of longitude at the equator', () => {
  near(haversineM({ lat: 0, lng: 0 }, { lat: 1, lng: 0 }), 111195.080, 0.001)
  near(haversineM({ lat: 0, lng: 0 }, { lat: 0, lng: 1 }), 111195.080, 0.001)
  assert.equal(haversineM({ lat: -6.2, lng: 106.8 }, { lat: -6.2, lng: 106.8 }), 0)
})

test('evaluateFix: accuracy threshold is inclusive; missing accuracy is rejected', () => {
  const at = (accuracy_m) => evaluateFix({ lat: 0, lng: 0, accuracy_m, t: 0 }, null, cfg)
  assert.deepEqual(at(30), { accept: true, reason: 'ok' })
  assert.deepEqual(at(30.1), { accept: false, reason: 'accuracy' })
  assert.deepEqual(at(null), { accept: false, reason: 'accuracy' })
})

test('evaluateFix: first fix of a session passes when accuracy is good', () => {
  assert.equal(evaluateFix({ lat: 1, lng: 1, accuracy_m: 5, t: 123 }, null, cfg).accept, true)
})

test('evaluateFix: interval is inclusive at 2.000 s, rejected at 1.999 s', () => {
  const last = { lat: 0, lng: 0, accuracy_m: 5, t: 1_000_000 }
  const moved = { lat: 0.0001, lng: 0, accuracy_m: 5 }
  assert.deepEqual(evaluateFix({ ...moved, t: 1_001_999 }, last, cfg), { accept: false, reason: 'interval' })
  assert.deepEqual(evaluateFix({ ...moved, t: 1_002_000 }, last, cfg), { accept: true, reason: 'ok' })
})

test('evaluateFix: 5.56 m passes, 4.45 m is rejected as distance', () => {
  const last = { lat: 0, lng: 0, accuracy_m: 5, t: 0 }
  assert.deepEqual(evaluateFix({ lat: 0.00005, lng: 0, accuracy_m: 5, t: 5000 }, last, cfg), { accept: true, reason: 'ok' })
  assert.deepEqual(evaluateFix({ lat: 0.00004, lng: 0, accuracy_m: 5, t: 5000 }, last, cfg), { accept: false, reason: 'distance' })
})

test('speedBetween: 5.5598 m in 2 s is 2.7799 m/s; non-advancing time gives null', () => {
  near(speedBetween({ lat: 0, lng: 0, t: 0 }, { lat: 0.00005, lng: 0, t: 2000 }), 2.77988, 0.001)
  assert.equal(speedBetween({ lat: 0, lng: 0, t: 5 }, { lat: 1, lng: 1, t: 5 }), null)
})

test('nextAutoPause: pauses at exactly 30 s below 0.3 m/s, resumes at >= 0.3', () => {
  let s = INITIAL_AUTO_PAUSE
  s = nextAutoPause(s, 0.1, 0, cfg)
  assert.deepEqual(s, { autoPaused: false, slowSinceMs: 0 })
  s = nextAutoPause(s, 0.1, 29_999, cfg)
  assert.equal(s.autoPaused, false)
  s = nextAutoPause(s, 0.1, 30_000, cfg)
  assert.equal(s.autoPaused, true)
  s = nextAutoPause(s, 0.3, 31_000, cfg)
  assert.deepEqual(s, { autoPaused: false, slowSinceMs: null })
})

test('nextAutoPause: a fast reading resets the slow timer; unknown speed changes nothing', () => {
  let s = nextAutoPause(INITIAL_AUTO_PAUSE, 0.1, 0, cfg)
  s = nextAutoPause(s, 0.5, 20_000, cfg)
  s = nextAutoPause(s, 0.1, 25_000, cfg)
  assert.deepEqual(s, { autoPaused: false, slowSinceMs: 25_000 })
  assert.equal(nextAutoPause(s, null, 99_000, cfg), s)
  assert.equal(nextAutoPause(s, NaN, 99_000, cfg), s)
})

test('toPayloadPoint / toFix: unknowns become null, a real 0 stays 0, time is ISO 8601 UTC', () => {
  const position = {
    coords: { latitude: -6.2, longitude: 106.8, altitude: null, accuracy: 12.5, speed: NaN, heading: NaN },
    timestamp: 1_700_000_000_000,
  }
  assert.deepEqual(toPayloadPoint(position), {
    lat: -6.2, lng: 106.8, ele: null, accuracy_m: 12.5, speed_mps: null, heading_deg: null,
    recorded_at: '2023-11-14T22:13:20.000Z',
  })
  const still = { ...position, coords: { ...position.coords, speed: 0, heading: 0, altitude: 0 } }
  const p = toPayloadPoint(still)
  assert.equal(p.speed_mps, 0)
  assert.equal(p.heading_deg, 0)
  assert.equal(p.ele, 0)
  assert.deepEqual(toFix(position), { lat: -6.2, lng: 106.8, accuracy_m: 12.5, t: 1_700_000_000_000 })
})

test('recorderQueue exports the documented API (IndexedDB itself is exercised by the e2e)', () => {
  for (const name of ['addPoint', 'makeBatch', 'listPending', 'deletePending', 'countQueued']) {
    assert.equal(typeof queue[name], 'function', name)
  }
})
