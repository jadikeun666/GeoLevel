<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Planned route (table `routes`). Named OutdoorRoute to avoid clashing with
 * Illuminate\Support\Facades\Route.
 */
class OutdoorRoute extends Model
{
    use HasFactory;

    protected $table = 'routes';

    protected $fillable = [
        'user_id',
        'project_id',
        'name',
        'geometry',
        'distance_m',
        'notes',
    ];

    protected $casts = [
        'geometry'   => 'array',
        'distance_m' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
