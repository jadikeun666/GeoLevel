<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waypoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'survey_point_id',
        'name',
        'lat',
        'lng',
        'elevation_ref',
        'gps_accuracy_m',
        'category',
        'source',
        'notes',
    ];

    protected $casts = [
        'lat'            => 'decimal:8',
        'lng'            => 'decimal:8',
        'elevation_ref'  => 'decimal:4',
        'gps_accuracy_m' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function surveyPoint(): BelongsTo
    {
        return $this->belongsTo(SurveyPoint::class);
    }
}
