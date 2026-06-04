<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // sequence_no KRITIS — urutan tidak boleh dilewati atau dipakai ulang
            $table->integer('sequence_no');

            $table->string('point_name', 50);
            $table->string('reading_type', 10); // BS | IS | FS

            // Bacaan lapangan — NUMERIC(10,4), bukan FLOAT
            $table->decimal('ba', 10, 4); // Bacaan Atas
            $table->decimal('bt', 10, 4); // Bacaan Tengah (entry lapangan)
            $table->decimal('bb', 10, 4); // Bacaan Bawah

            // Override manual jarak; jika NULL maka pakai distance_computed
            $table->decimal('distance_m', 10, 3)->nullable();

            $table->string('notes', 255)->nullable();

            $table->timestamps();
        });

        // Generated Columns HARUS dibuat via raw SQL karena Laravel Blueprint
        // tidak mendukung GENERATED ALWAYS AS secara native untuk PostgreSQL
        DB::statement('
            ALTER TABLE readings
            ADD COLUMN bt_check NUMERIC(10,4)
                GENERATED ALWAYS AS ((ba + bb) / 2.0) STORED
        ');

        DB::statement('
            ALTER TABLE readings
            ADD COLUMN distance_computed NUMERIC(10,3)
                GENERATED ALWAYS AS ((ba - bb) * 100.0) STORED
        ');

        // Index untuk query urutan per proyek
        DB::statement('
            CREATE INDEX idx_readings_project_sequence
                ON readings (project_id, sequence_no ASC)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};