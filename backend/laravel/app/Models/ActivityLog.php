<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    // Log bersifat immutable — hanya created_at, tidak ada updated_at
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'user_id',
        'activity_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Activity Type Constants
    // Gunakan konstanta ini — jangan hardcode string di tempat lain
    // -----------------------------------------------------------------------

    const TYPE_READING_SAVED       = 'reading_saved';
    const TYPE_CLOSURE_CHECKED     = 'closure_checked';      // FIX: ditambahkan
    const TYPE_SURVEY_RECALCULATED = 'survey_recalculated';
    const TYPE_ADJUSTMENT_APPLIED  = 'adjustment_applied';
    const TYPE_ADJUSTMENT_RESET    = 'adjustment_reset';
    const TYPE_EXPORT_GENERATED    = 'export_generated';
    const TYPE_JOB_FAILED          = 'job_failed';

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}