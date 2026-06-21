<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * network_legs — merepresentasikan satu jalur pengukuran beda tinggi
     * antara dua titik (from_point → to_point) dalam sebuah jaring sipat
     * datar tertutup (closed leveling network) dengan kondisi geometrik
     * berlebih (redundant observations).
     *
     * Berbeda dari `readings` (yang merepresentasikan satu lintasan
     * sekuensial BS/IS/FS), tabel ini merepresentasikan EDGE dalam graf
     * jaring — titik yang sama bisa muncul di banyak leg (loop tumpang
     * tindih), sesuai topologi pada jurnal Agnes Sri Mulyani (2020) dan
     * Setiaji Nanang Handriyanto (Jurnal Geodesi Undip, 2013).
     *
     * Leg ini OPSIONAL — hanya diisi jika project punya >1 jalur ke titik
     * yang sama (network beneran, bukan traverse linear biasa). Jika
     * project tidak punya leg sama sekali, AdjustmentService fallback ke
     * formula Bowditch lama (lihat docs/formulas.md).
     */
    public function up(): void
    {
        Schema::create('network_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('from_point', 50);
            $table->string('to_point', 50);

            // Beda tinggi terukur untuk leg ini (ΔH = from → to), dari
            // lapangan — independen dari `readings`, walau secara konsep
            // bisa direkonsiliasi dengan ΣBS−ΣFS sepanjang leg tersebut.
            $table->decimal('observed_delta_h', 12, 6);

            // Jarak leg — dipakai sebagai bobot pengamatan (P = 1/d).
            // SNI 19-6988-2004 §6.3 — jalur pendek dapat bobot lebih tinggi.
            $table->decimal('distance_m', 10, 3);

            // Hasil hitung perataan least squares — null sebelum dihitung.
            $table->decimal('corrected_delta_h', 12, 6)->nullable();
            $table->decimal('residual', 12, 6)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'from_point']);
            $table->index(['project_id', 'to_point']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_legs');
    }
};
