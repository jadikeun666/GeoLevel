<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossSection extends Model
{
    protected $fillable = [
        'project_id',
        'station_name',
        'station_distance',
        'offsets',
    ];

    protected $casts = [
        'station_distance' => 'decimal:3',
        'offsets'          => 'array',
        // Format offsets:
        // [{"side": "L"|"R"|"C", "distance": 3.0, "elevation": 101.234}, ...]
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}