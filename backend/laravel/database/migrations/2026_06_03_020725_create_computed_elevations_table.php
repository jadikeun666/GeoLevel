<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computed_elevations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('reading_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->integer('sequence_no');
            $table->string('point_name', 50);

            // hi hanya diisi pada baris BS
            $table->decimal('hi', 12, 4)->nullable();

            // Elevasi sebelum adjustment
            $table->decimal('raw_elevation', 12, 4);

            // Koreksi yang diterapkan (0 jika belum diadjust) — 6 desimal
            $table->decimal('correction', 12, 6);

            // Elevasi final setelah adjustment
            $table->decimal('adjusted_elevation', 12, 4);

            $table->decimal('cumulative_distance', 12, 3);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computed_elevations');
    }
};