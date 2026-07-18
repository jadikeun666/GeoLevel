#!/bin/bash
set -e

echo "=========================================="
echo "FIX v2: Muat CSS MapLibre via CDN, bukan lewat Vite"
echo "=========================================="

if [ ! -f "artisan" ]; then
    echo "ERROR: jalankan dari root project Laravel"
    exit 1
fi

echo ""
echo "--- Step 1: Cek versi maplibre-gl yang terpasang ---"
MAPLIBRE_VERSION=$(node -p "require('./node_modules/maplibre-gl/package.json').version")
echo "Versi terpasang: $MAPLIBRE_VERSION"

echo ""
echo "--- Step 2: Kembalikan resources/css/app.css ke semula (hapus @import maplibre) ---"

python3 << 'PYEOF'
path = "resources/css/app.css"

with open(path, "rb") as f:
    raw = f.read()
raw = raw.replace(b"\r\n", b"\n")
content = raw.decode("utf-8")

old_line = "@import 'maplibre-gl/dist/maplibre-gl.css';\n"

if old_line in content:
    content = content.replace(old_line, "", 1)
    with open(path, "w", newline="\n", encoding="utf-8") as f:
        f.write(content)
    print("  Baris @import dihapus dari app.css.")
else:
    print("  Tidak ditemukan baris @import, dilewati (mungkin sudah bersih).")
PYEOF

echo ""
echo "--- Verifikasi app.css sudah bersih ---"
head -n 5 resources/css/app.css

echo ""
echo "--- Step 3: Cari file blade layout utama (tempat @vite dipanggil) ---"
BLADE_FILE="resources/views/app.blade.php"

if [ ! -f "$BLADE_FILE" ]; then
    echo "  GAGAL: $BLADE_FILE tidak ditemukan. Cek manual nama file layout."
    exit 1
fi

echo "  Ditemukan: $BLADE_FILE"

echo ""
echo "--- Step 4: Tambah <link> CDN MapLibre CSS di blade layout ---"

python3 << PYEOF
path = "$BLADE_FILE"
version = "$MAPLIBRE_VERSION"

with open(path, "rb") as f:
    raw = f.read()
raw = raw.replace(b"\r\n", b"\n")
content = raw.decode("utf-8")

cdn_link = f'    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@{version}/dist/maplibre-gl.css" />\n'

if "maplibre-gl@" in content:
    print("  Sudah ada link CDN maplibre-gl, dilewati.")
else:
    if "</head>" not in content:
        print("  GAGAL: tag </head> tidak ditemukan di file blade.")
        raise SystemExit(1)
    content = content.replace("</head>", cdn_link + "</head>", 1)
    with open(path, "w", newline="\n", encoding="utf-8") as f:
        f.write(content)
    print(f"  Berhasil ditambahkan link CDN versi {version} sebelum </head>.")
PYEOF

echo ""
echo "--- Verifikasi isi blade (cari baris maplibre) ---"
grep -n "maplibre-gl@" "$BLADE_FILE"

echo ""
echo "--- Step 5: Bersihkan cache build total & build ulang ---"
rm -rf public/build
rm -rf node_modules/.vite
npm run build

echo ""
echo "--- Step 6: Verifikasi ukuran CSS hasil build ---"
ls -la public/build/assets/*.css

echo ""
echo "--- Step 7: Clear cache Laravel juga (view cache blade) ---"
php artisan view:clear
php artisan config:clear

echo ""
echo "=========================================="
echo "SELESAI. Cek:"
echo "1. Apakah warning '@tailwind unknown at rule' SUDAH HILANG dari log build di atas?"
echo "2. Ukuran app-*.css harus di kisaran 60-70 KB (Tailwind saja, tanpa maplibre)"
echo "3. Hard refresh browser (Ctrl+Shift+R) dan cek tampilan"
echo "=========================================="