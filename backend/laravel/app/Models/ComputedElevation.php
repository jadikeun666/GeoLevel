<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputedElevation extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_id',
        'reading_id',
        'sequence_no',
        'point_name',
        'hi',
        'raw_elevation',
        'correction',
        'adjusted_elevation',
        'cumulative_distance',
    ];

    protected $casts = [
        'hi'                  => 'decimal:4',
        'raw_elevation'       => 'decimal:4',
        'correction'          => 'decimal:6',
        'adjusted_elevation'  => 'decimal:6',
        'cumulative_distance' => 'decimal:3',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reading(): BelongsTo
    {
        return $this->belongsTo(Reading::class);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Apakah elevasi ini sudah diadjust?
     * Adjustment reversible — correction = 0 berarti belum/sudah di-reset.
     */
    public function isAdjusted(): bool
    {
        return bccomp((string) $this->correction, '0', 6) !== 0;
    }
}