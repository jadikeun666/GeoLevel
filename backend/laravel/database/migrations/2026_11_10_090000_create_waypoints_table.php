<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('survey_point_id')->nullable()->constrained('survey_points')->nullOnDelete();
            $table->string('name', 100);
            $table->decimal('lat', 12, 8);
            $table->decimal('lng', 12, 8);
            $table->decimal('elevation_ref', 12, 4)->nullable();
            $table->decimal('gps_accuracy_m', 6, 3)->nullable();
            // generic | survey_marker | camp | water | danger | summit | parking
            $table->string('category', 20)->default('generic');
            // manual | gpx | recorded | picker
            $table->string('source', 20)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waypoints');
    }
};
