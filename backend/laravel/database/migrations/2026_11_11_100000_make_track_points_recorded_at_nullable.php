<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // GPX <time> is optional (e.g. routes from trail planners), so imported
    // track points may legitimately have no timestamp.
    public function up(): void
    {
        Schema::table('track_points', function (Blueprint $table) {
            $table->timestampTz('recorded_at', 3)->nullable()->change();
        });
    }

    // Fails if rows with NULL recorded_at exist (intended: do not lose data silently).
    public function down(): void
    {
        Schema::table('track_points', function (Blueprint $table) {
            $table->timestampTz('recorded_at', 3)->nullable(false)->change();
        });
    }
};
