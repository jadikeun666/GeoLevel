<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkLeg extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'from_point',
        'to_point',
        'observed_delta_h',
        'distance_m',
        'corrected_delta_h',
        'residual',
        'notes',
    ];

    protected $casts = [
        'observed_delta_h'  => 'decimal:6',
        'distance_m'        => 'decimal:3',
        'corrected_delta_h' => 'decimal:6',
        'residual'          => 'decimal:6',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isAdjusted(): bool
    {
        return $this->corrected_delta_h !== null;
    }
}
