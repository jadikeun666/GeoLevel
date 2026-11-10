<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'name',
        'source',
        'status',
        'started_at',
        'ended_at',
        'point_count',
        'distance_m',
        'duration_s',
        'moving_time_s',
        'elevation_gain_m',
        'elevation_loss_m',
        'min_elevation',
        'max_elevation',
        'metadata',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'point_count'      => 'integer',
        'distance_m'       => 'decimal:3',
        'duration_s'       => 'integer',
        'moving_time_s'    => 'integer',
        'elevation_gain_m' => 'decimal:3',
        'elevation_loss_m' => 'decimal:3',
        'min_elevation'    => 'decimal:4',
        'max_elevation'    => 'decimal:4',
        'metadata'         => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(TrackPoint::class)->orderBy('seq');
    }
}
