<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('point_name', 50);
            $table->decimal('lat', 12, 8);
            $table->decimal('lng', 12, 8);
            $table->string('point_type', 10)->default('TP'); // BM | TP | IS | CP
            $table->decimal('elevation_ref', 12, 4)->nullable();
            $table->decimal('gps_accuracy_m', 6, 3)->nullable();
            $table->string('source', 20)->default('manual'); // manual | gpx | picker
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'point_name']);
            $table->index('project_id');
            $table->index(['project_id', 'point_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_points');
    }
};
