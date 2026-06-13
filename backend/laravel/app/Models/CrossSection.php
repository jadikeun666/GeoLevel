<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'station_name',
        'station_distance',
        'offsets',
    ];

    protected $casts = [
        'station_distance' => 'decimal:3',
        'offsets'          => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}