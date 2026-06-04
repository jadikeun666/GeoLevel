<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'location',
        'description',
        'survey_date',
        'benchmark_name',
        'benchmark_elevation',
        'tolerance_class',
        'adjustment_method',
        'closure_error',
        'total_distance_km',
        'allowed_tolerance',
        'status',
        'metadata',
    ];

    protected $casts = [
        'survey_date'         => 'date',
        'benchmark_elevation' => 'decimal:4',
        'closure_error'       => 'decimal:6',
        'total_distance_km'   => 'decimal:4',
        'allowed_tolerance'   => 'decimal:6',
        'metadata'            => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class)->orderBy('sequence_no');
    }

    public function computedElevations(): HasMany
    {
        return $this->hasMany(ComputedElevation::class)->orderBy('sequence_no');
    }

    public function crossSections(): HasMany
    {
        return $this->hasMany(CrossSection::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('created_at');
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
