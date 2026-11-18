// IndexedDB queue for live recording (docs/outdoor.md, 17.3). No Vue, no dependencies.
// DB 'geolevel-outdoor' v1:
//   buffer          keyPath 'id' (autoIncrement), index 'track_id'  -> { id, track_id, point }
//   pending_batches keyPath 'client_batch_id',    index 'track_id'  -> { client_batch_id, track_id, created_at, points }
// A batch is created (with its UUID) and persisted BEFORE it is sent, so a retry
// always reuses the same client_batch_id and the server de-duplicates it.

const DB_NAME = 'geolevel-outdoor'
const DB_VERSION = 1
const STORE_BUFFER = 'buffer'
const STORE_PENDING = 'pending_batches'

let dbPromise = null

function openDb() {
  if (dbPromise) return dbPromise
  dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)
    request.onupgradeneeded = () => {
      const db = request.result
      const buffer = db.createObjectStore(STORE_BUFFER, { keyPath: 'id', autoIncrement: true })
      buffer.createIndex('track_id', 'track_id')
      const pending = db.createObjectStore(STORE_PENDING, { keyPath: 'client_batch_id' })
      pending.createIndex('track_id', 'track_id')
    }
    request.onsuccess = () => {
      const db = request.result
      db.onversionchange = () => {
        db.close()
        dbPromise = null
      }
      resolve(db)
    }
    request.onerror = () => {
      dbPromise = null
      reject(request.error)
    }
  })
  return dbPromise
}

function txDone(tx) {
  return new Promise((resolve, reject) => {
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
    tx.onabort = () => reject(tx.error ?? new Error('Transaksi IndexedDB dibatalkan.'))
  })
}

function requestDone(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

/** Store one accepted point (the payload sent to the server). Resolves with its buffer id. */
export async function addPoint(trackId, point) {
  const db = await openDb()
  const tx = db.transaction(STORE_BUFFER, 'readwrite')
  const request = tx.objectStore(STORE_BUFFER).add({ track_id: trackId, point })
  await txDone(tx)
  return request.result
}

/**
 * Move up to `size` oldest buffered points of a track into one pending batch, atomically.
 * Resolves with the batch, or null when the buffer is empty.
 */
export async function makeBatch(trackId, size) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction([STORE_BUFFER, STORE_PENDING], 'readwrite')
    const buffer = tx.objectStore(STORE_BUFFER)
    let batch = null

    const read = buffer.index('track_id').getAll(IDBKeyRange.only(trackId), size)
    read.onsuccess = () => {
      const rows = read.result
      if (rows.length === 0) return
      batch = {
        client_batch_id: crypto.randomUUID(),
        track_id: trackId,
        created_at: Date.now(),
        points: rows.map((row) => row.point),
      }
      tx.objectStore(STORE_PENDING).add(batch)
      rows.forEach((row) => buffer.delete(row.id))
    }

    tx.oncomplete = () => resolve(batch)
    tx.onerror = () => reject(tx.error)
    tx.onabort = () => reject(tx.error ?? new Error('Transaksi IndexedDB dibatalkan.'))
  })
}

/** Pending batches of a track in send order (created_at, then first point time as tiebreak). */
export async function listPending(trackId) {
  const db = await openDb()
  const tx = db.transaction(STORE_PENDING, 'readonly')
  const rows = await requestDone(tx.objectStore(STORE_PENDING).index('track_id').getAll(IDBKeyRange.only(trackId)))
  return rows.sort(
    (a, b) =>
      a.created_at - b.created_at ||
      String(a.points[0]?.recorded_at).localeCompare(String(b.points[0]?.recorded_at)),
  )
}

/** Remove a batch after the server confirmed it (or it must be discarded). */
export async function deletePending(clientBatchId) {
  const db = await openDb()
  const tx = db.transaction(STORE_PENDING, 'readwrite')
  tx.objectStore(STORE_PENDING).delete(clientBatchId)
  await txDone(tx)
}

/** Number of points not yet confirmed by the server: buffered + inside pending batches. */
export async function countQueued(trackId) {
  const db = await openDb()
  const tx = db.transaction([STORE_BUFFER, STORE_PENDING], 'readonly')
  const buffered = requestDone(tx.objectStore(STORE_BUFFER).index('track_id').count(IDBKeyRange.only(trackId)))
  const pending = requestDone(tx.objectStore(STORE_PENDING).index('track_id').getAll(IDBKeyRange.only(trackId)))
  const [bufferedCount, batches] = await Promise.all([buffered, pending])
  return bufferedCount + batches.reduce((sum, batch) => sum + batch.points.length, 0)
}
