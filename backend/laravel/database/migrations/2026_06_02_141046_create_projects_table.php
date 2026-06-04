<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name');
            $table->string('location');
            $table->text('description')->nullable();
            $table->date('survey_date');
            $table->string('benchmark_name', 100);
            $table->decimal('benchmark_elevation', 12, 4);

            // Tidak pakai ENUM agar mudah diperluas
            $table->string('tolerance_class', 10);   // LAA | LA | LB | LC
            $table->string('adjustment_method', 20);  // equal | bowditch | least_squares

            // Nilai hasil kalkulasi — nullable sampai survey selesai dihitung
            $table->decimal('closure_error', 10, 6)->nullable();
            $table->decimal('total_distance_km', 10, 4)->nullable();
            $table->decimal('allowed_tolerance', 10, 6)->nullable();

            $table->string('status', 20)->default('draft'); // draft | calculated | accepted | rejected

            // JSONB untuk metadata fleksibel
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};