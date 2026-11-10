<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Raw GPS point. Immutable: insert-only (docs/outdoor.md, golden rule #2).
 */
class TrackPoint extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'track_id',
        'seq',
        'lat',
        'lng',
        'ele',
        'accuracy_m',
        'speed_mps',
        'heading_deg',
        'recorded_at',
    ];

    protected $casts = [
        'seq'         => 'integer',
        'lat'         => 'decimal:8',
        'lng'         => 'decimal:8',
        'ele'         => 'decimal:4',
        'accuracy_m'  => 'decimal:3',
        'speed_mps'   => 'decimal:3',
        'heading_deg' => 'decimal:3',
        'recorded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('track_points are immutable (insert-only).');
        });
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
