<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaLogbookReview extends Model
{
    protected $fillable = ['pkpa_logbook_entry_id', 'reviewer_type', 'reviewer_core_user_id', 'action', 'comments', 'reviewed_at', 'metadata'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(PkpaLogbookEntry::class, 'pkpa_logbook_entry_id');
    }
}
