// Run: npm run test:js. Expected values are hand-computable (see docs/outdoor.md 19.3).
import test from 'node:test'
import assert from 'node:assert/strict'
import { bearingDeg, movingSpeedMps, etaSeconds } from '../../resources/js/Components/Outdoor/recorderFilter.js'

const near = (actual, expected, delta) =>
  assert.ok(Math.abs(actual - expected) <= delta, `expected ${expected} +/- ${delta}, got ${actual}`)

test('bearingDeg: cardinal directions from the equator', () => {
  const o = { lat: 0, lng: 0 }
  near(bearingDeg(o, { lat: 1, lng: 0 }), 0, 1e-9)
  near(bearingDeg(o, { lat: 0, lng: 1 }), 90, 1e-9)
  near(bearingDeg(o, { lat: -1, lng: 0 }), 180, 1e-9)
  near(bearingDeg(o, { lat: 0, lng: -1 }), 270, 1e-9)
})

test('movingSpeedMps: 0.0002 deg of longitude at the equator in 60 s is 0.37065 m/s', () => {
  const samples = [
    { lat: 0, lng: 0, t: 0 },
    { lat: 0, lng: 0.0001, t: 30000 },
    { lat: 0, lng: 0.0002, t: 60000 },
  ]
  near(movingSpeedMps(samples, 60000), 0.37065, 0.0001)
})

test('movingSpeedMps: samples older than the window are ignored; <2 samples or no time gives null', () => {
  const samples = [
    { lat: 0, lng: -0.01, t: -10000 }, // outside the 60 s window
    { lat: 0, lng: 0, t: 0 },
    { lat: 0, lng: 0.0002, t: 60000 },
  ]
  near(movingSpeedMps(samples, 60000), 0.37065, 0.0001)
  assert.equal(movingSpeedMps([{ lat: 0, lng: 0, t: 0 }], 0), null)
  assert.equal(movingSpeedMps([{ lat: 0, lng: 0, t: 5 }, { lat: 1, lng: 1, t: 5 }], 5), null)
})

test('etaSeconds: 300 m at 1.5 m/s is 200 s; below the minimum speed or unknown speed is null', () => {
  near(etaSeconds(300, 1.5, 0.3), 200, 1e-9)
  near(etaSeconds(300, 0.3, 0.3), 1000, 1e-6) // threshold is inclusive
  assert.equal(etaSeconds(300, 0.2, 0.3), null)
  assert.equal(etaSeconds(300, null, 0.3), null)
  assert.equal(etaSeconds(300, NaN, 0.3), null)
})
