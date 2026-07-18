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
        'network_variance',
        'network_std_deviation',
        'network_degrees_of_freedom',
    ];

protected $casts = [
        'survey_date'                 => 'date',
        'benchmark_elevation'         => 'decimal:4',
        'closure_error'               => 'float',
        'total_distance_km'           => 'float',
        'allowed_tolerance'           => 'float',
        'metadata'                    => 'array',
        'network_variance'            => 'decimal:12',
        'network_std_deviation'       => 'decimal:6',
        'network_degrees_of_freedom'  => 'integer',
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

    public function networkLegs(): HasMany
    {
        return $this->hasMany(NetworkLeg::class);
    }

    public function hasNetworkLegs(): bool
    {
        return $this->networkLegs()->exists();
    }

    public function hasRedundantNetworkObservations(): bool
    {
        $legs = $this->networkLegs;

        if ($legs->count() < 2) {
            return false;
        }

        $points = collect();
        foreach ($legs as $leg) {
            $points->push($leg->from_point);
            $points->push($leg->to_point);
        }

        $uniquePoints = $points->unique()->count();

        return $legs->count() >= $uniquePoints;
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
