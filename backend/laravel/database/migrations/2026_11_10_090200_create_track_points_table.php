<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable, insert-only (docs/outdoor.md aturan emas #2): no updated_at.
        Schema::create('track_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->integer('seq');
            $table->decimal('lat', 12, 8);
            $table->decimal('lng', 12, 8);
            $table->decimal('ele', 12, 4)->nullable();
            $table->decimal('accuracy_m', 6, 3)->nullable();
            $table->decimal('speed_mps', 8, 3)->nullable();
            $table->decimal('heading_deg', 6, 3)->nullable();
            $table->timestampTz('recorded_at', 3);

            // UNIQUE index also serves lookups by (track_id, seq).
            $table->unique(['track_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_points');
    }
};
