<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('station_name', 50);
            $table->decimal('station_distance', 10, 3);

            // Format JSONB:
            // [{"side": "L", "distance": 3.0, "elevation": 101.234}, ...]
            // side values: "L" | "R" | "C"
            $table->jsonb('offsets');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_sections');
    }
};