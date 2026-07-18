<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'point_name',
        'lat',
        'lng',
        'point_type',
        'elevation_ref',
        'gps_accuracy_m',
        'source',
        'notes',
    ];

    protected $casts = [
        'lat'            => 'decimal:8',
        'lng'            => 'decimal:8',
        'elevation_ref'  => 'decimal:4',
        'gps_accuracy_m' => 'decimal:3',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
