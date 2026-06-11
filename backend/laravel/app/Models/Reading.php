<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reading extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'sequence_no',
        'point_name',
        'reading_type',
        'ba',
        'bt',
        'bb',
        'distance_m',
        'notes',
    ];

    protected $casts = [
        'ba'                => 'decimal:4',
        'bt'                => 'decimal:4',
        'bb'                => 'decimal:4',
        'distance_m'        => 'decimal:3',
        'bt_check'          => 'decimal:4',
        'distance_computed' => 'decimal:3',
    ];

    protected $guarded = ['bt_check', 'distance_computed'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function computedElevation(): HasOne
    {
        return $this->hasOne(ComputedElevation::class);
    }

    public function effectiveDistance(): string
    {
        return (string) ($this->distance_m ?? $this->distance_computed ?? '0');
    }

    public function isBtValid(): bool
    {
        $limit     = (string) config('geolevel.bt_deviation_limit');
        $bt        = (string) $this->bt;
        $btCheck   = (string) $this->bt_check;
        $deviation = bcsub($bt, $btCheck, 6);

        if (bccomp($deviation, '0', 6) < 0) {
            $deviation = bcsub('0', $deviation, 6);
        }

        return bccomp($deviation, $limit, 6) <= 0;
    }

    public function isBacksight(): bool
    {
        return $this->reading_type === 'BS';
    }

    public function isForesight(): bool
    {
        return $this->reading_type === 'FS';
    }

    public function isIntermediate(): bool
    {
        return $this->reading_type === 'IS';
    }
}
