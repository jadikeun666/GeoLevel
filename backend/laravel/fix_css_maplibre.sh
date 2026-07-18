#!/bin/bash
set -e

echo "=========================================="
echo "FIX: CSS Tailwind rusak akibat import maplibre-gl.css di SFC"
echo "=========================================="

if [ ! -f "artisan" ]; then
    echo "ERROR: jalankan dari root project Laravel"
    exit 1
fi

echo ""
echo "--- Step 1: Hapus import CSS dari SurveyMap.vue ---"

python3 << 'PYEOF'
path = "resources/js/Components/Map/SurveyMap.vue"

with open(path, "rb") as f:
    raw = f.read()
raw = raw.replace(b"\r\n", b"\n")
content = raw.decode("utf-8")

old = "import maplibregl from 'maplibre-gl'\nimport 'maplibre-gl/dist/maplibre-gl.css'\n"
new = "import maplibregl from 'maplibre-gl'\n"

if "import 'maplibre-gl/dist/maplibre-gl.css'" not in content:
    print("  Sudah tidak ada import CSS di sini, dilewati.")
else:
    if old not in content:
        print("  GAGAL: pola tidak ditemukan persis, cek manual.")
        raise SystemExit(1)
    content = content.replace(old, new, 1)
    with open(path, "w", newline="\n", encoding="utf-8") as f:
        f.write(content)
    print("  Berhasil dihapus dari SurveyMap.vue.")
PYEOF

echo ""
echo "--- Step 2: Tambahkan @import ke resources/css/app.css ---"

python3 << 'PYEOF'
path = "resources/css/app.css"

with open(path, "rb") as f:
    raw = f.read()
raw = raw.replace(b"\r\n", b"\n")
content = raw.decode("utf-8")

if "maplibre-gl.css" in content:
    print("  Sudah ada @import maplibre-gl di app.css, dilewati.")
else:
    # @import CSS wajib berada di baris paling atas file (sebelum aturan lain)
    new_content = "@import 'maplibre-gl/dist/maplibre-gl.css';\n" + content
    with open(path, "w", newline="\n", encoding="utf-8") as f:
        f.write(new_content)
    print("  Berhasil ditambahkan @import di baris pertama app.css.")
PYEOF

echo ""
echo "--- Verifikasi isi app.css (5 baris pertama) ---"
head -n 5 resources/css/app.css

echo ""
echo "--- Step 3: Bersihkan cache build lama total ---"
rm -rf public/build
rm -rf node_modules/.vite

echo ""
echo "--- Step 4: Build ulang ---"
npm run build

echo ""
echo "--- Step 5: Verifikasi ukuran CSS ---"
ls -la public/build/assets/*.css

echo ""
echo "=========================================="
echo "Cek di atas: apakah ada file CSS berukuran besar (puluhan-ratusan KB)?"
echo "Apakah warning '@tailwind unknown at rule' sudah HILANG dari log build?"
echo "Jika ya, hard refresh browser dan cek tampilan."
echo "=========================================="