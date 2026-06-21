<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom tambahan untuk menyimpan hasil global dari least squares
     * network adjustment (lihat LeastSquaresAdjustmentService).
     *
     * - network_variance: σ₀² = VᵀPV / (n−u) — variansi baku aposteriori
     * - network_std_deviation: σ₀ = √(network_variance)
     * - network_degrees_of_freedom: n−u, jumlah observasi lebih
     *
     * Null jika project tidak memakai network leg (traverse linear biasa).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('network_variance', 18, 12)->nullable()->after('allowed_tolerance');
            $table->decimal('network_std_deviation', 12, 6)->nullable()->after('network_variance');
            $table->integer('network_degrees_of_freedom')->nullable()->after('network_std_deviation');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'network_variance',
                'network_std_deviation',
                'network_degrees_of_freedom',
            ]);
        });
    }
};
