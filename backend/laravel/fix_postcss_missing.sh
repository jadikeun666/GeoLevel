#!/bin/bash
set -e

echo "=========================================="
echo "FIX v3 (akar masalah): Buat postcss.config.js yang hilang"
echo "=========================================="

if [ ! -f "artisan" ]; then
    echo "ERROR: jalankan dari root project Laravel"
    exit 1
fi

if [ -f "postcss.config.js" ]; then
    echo "postcss.config.js sudah ada, tidak menimpa. Isi saat ini:"
    cat postcss.config.js
    echo ""
    echo "Jika ini memang salah/kosong, hapus manual dulu baru jalankan script ini lagi."
    exit 0
fi

echo ""
echo "--- Menulis postcss.config.js ---"

cat > postcss.config.js << 'EOF'
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
EOF

echo "Berhasil ditulis:"
cat postcss.config.js

echo ""
echo "--- Cek apakah autoprefixer terpasang ---"
if ! npm list autoprefixer > /dev/null 2>&1; then
    echo "autoprefixer belum terpasang, menginstall..."
    npm install -D autoprefixer
else
    echo "autoprefixer sudah terpasang."
fi

echo ""
echo "--- Bersihkan cache build total & build ulang ---"
rm -rf public/build
rm -rf node_modules/.vite
npm run build

echo ""
echo "--- Verifikasi ukuran CSS hasil build ---"
ls -la public/build/assets/*.css

echo ""
echo "--- Clear cache Laravel ---"
php artisan view:clear
php artisan config:clear

echo ""
echo "=========================================="
echo "SELESAI. Cek log build di atas:"
echo "1. Warning '@tailwind unknown at rule' HARUS SUDAH HILANG"
echo "2. Ukuran app-*.css harus 60+ KB (bukan 0.05 kB lagi)"
echo "3. Hard refresh browser dan cek tampilan"
echo "=========================================="