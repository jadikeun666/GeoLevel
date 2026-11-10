<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('source', 20);   // recorded | gpx
            $table->string('status', 20);   // recording | paused | finished
            $table->timestampTz('started_at', 3)->nullable();
            $table->timestampTz('ended_at', 3)->nullable();
            $table->integer('point_count')->default(0);
            $table->decimal('distance_m', 12, 3)->nullable();
            $table->integer('duration_s')->nullable();
            $table->integer('moving_time_s')->nullable();
            $table->decimal('elevation_gain_m', 10, 3)->nullable();
            $table->decimal('elevation_loss_m', 10, 3)->nullable();
            $table->decimal('min_elevation', 12, 4)->nullable();
            $table->decimal('max_elevation', 12, 4)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index('user_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
