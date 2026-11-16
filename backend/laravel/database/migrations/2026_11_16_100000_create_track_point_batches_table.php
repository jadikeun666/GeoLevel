<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_point_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_batch_id');
            $table->unsignedInteger('first_seq');
            $table->unsignedInteger('point_count');
            $table->timestampTz('received_at', 3);

            // Idempotency: the same client batch for the same track is accepted once.
            $table->unique(['track_id', 'client_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_point_batches');
    }
};
