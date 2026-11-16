<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotency ledger for live-recorded point batches (docs/outdoor.md, bagian 5 & 8).
 * One row per accepted client batch; track_points itself stays insert-only.
 */
class TrackPointBatch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'track_id',
        'client_batch_id',
        'first_seq',
        'point_count',
        'received_at',
    ];

    protected $casts = [
        'first_seq'   => 'integer',
        'point_count' => 'integer',
        'received_at' => 'datetime',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
