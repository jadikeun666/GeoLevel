# Outdoor Fase 3: catatan serah-terima (2026-11-24)

Sumber kebenaran tetap `docs/outdoor.md` (bagian 17 sampai 20 untuk Fase 2 dan 3). Dokumen ini hanya status kerja agar sesi baru bisa lanjut tanpa membaca riwayat.

## Cara kerja (tetap)
Satu sub-langkah per giliran, patch Python idempoten dengan anchor yang dicek unik + backup ke /tmp, verifikasi grep, `npm run build`, commit granular dengan atribusi Claude. Docs di-gitignore: `git add -f`. Jawaban bahasa Indonesia, kode bahasa Inggris. Mode ponytail aktif (perubahan sekecil mungkin, balasan ringkas, sebut apa yang dilewati).

## Selesai (semua ter-commit dan ter-push di main, commit terakhir e250823)
- Fase 2: perekam live (`TrackRecorder.vue`, `recorderFilter.js`, `recorderQueue.js`), perencana rute (`RoutePlanner.vue`), `OutdoorMap` + layer live/rute. Uji HP nyata lewat tunnel Cloudflare dilaporkan berhasil (2026-11-22).
- Fase 3: `GeoMathService` toUtm/fromUtm/toMgrs/toDms/measure; endpoint `outdoor.convert`, `outdoor.measure`, `outdoor.tracks.profile`; `CoordinateConverter.vue`, `MeasureTool.vue`, `NavigationHud.vue`, `ElevationProfileChart.vue`; fungsi `bearingDeg`, `movingSpeedMps`, `etaSeconds`.

## Belum selesai
- **Penutup Fase 3:** pengembang membandingkan konverter (Jakarta -6.2088, 106.8456 harus 48S 704207.169 9313358.339 dan MGRS 48MYU0420713358) dan alat Ukur dengan alat lain, lalu melihat grafik profil track 23 di browser. Hasilnya belum dikirim.
- Utang teknis lain ada di `docs/outdoor.md` 18.3 dan 20.3 (HUD belum diuji GPS nyata, belum ada garis ke tujuan, galat `/login`, sidebar panjang, batch yatim IndexedDB, tes di SQLite saja).

## Pelajaran teknis (jangan diulang)
- Jangan pakai `| tail` di rantai `&&` yang memuat test: status keluar pipe menutupi kegagalan. Pakai redirect ke berkas lalu `grep`.
- `node --test` harus memakai glob (`tests/js/*.test.mjs`), bukan direktori. Skrip: `npm run test:js`.
- Di test PHP, jangan beri nama helper `post()` (bentrok dengan `TestCase::post`).
- Di e2e, sidebar `/outdoor` panjang: panggil `canvas.scrollIntoViewIfNeeded()` sebelum `boundingBox()` dan klik kanvas, atau klik jatuh di luar viewport.
- Galat halaman `Cannot read properties of null (reading 'component')` berasal dari `/login` (Blade Breeze memuat `app.js` Inertia); pasang `pageerror` listener sesudah `loginAs`.
- JSON membuang `.0`: banding angka di test dengan `assertEquals`, bukan `assertSame`.
- Nilai acuan UTM/MGRS dari PROJ/GeoTrans (venv sementara `/tmp/projenv`, hilang saat WSL restart; tidak masuk composer/npm).
- Event discovery dimatikan; listener didaftar manual di `EventServiceProvider::$listen`. Test jalan di SQLite (queue sync); jangan pakai sintaks khusus PostgreSQL di migrasi.
- Tile hanya CARTO (butuh key `VITE_CARTO_KEY`, jangan tulis ke kode) atau Esri. Dilarang mengunduh tile massal (relevan untuk Fase 4).
- Tunnel Cloudflare: hentikan dengan Ctrl+C bila masih jalan.

## Keputusan terbuka untuk Fase 4 (tanyakan sebelum desain, jangan putuskan sendiri)
- Peta offline sungguhan via PMTiles milik sendiri atau hanya cache tile yang pernah dilihat (docs 9, keputusan #3).
- Batas cache (`tile_cache_max_mb` 200, `tile_cache_ttl_days` 30) dan cakupan Service Worker.
