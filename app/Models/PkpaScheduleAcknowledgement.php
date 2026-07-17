<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaScheduleAcknowledgement extends Model
{
    protected $fillable = [
        'pkpa_placement_publication_id',
        'pkpa_published_assignment_id',
        'core_user_id',
        'audience_type',
        'acknowledgement_type',
        'acknowledged_at',
        'ip_address_hash',
        'user_agent_summary',
    ];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PkpaPlacementPublication::class, 'pkpa_placement_publication_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignment::class, 'pkpa_published_assignment_id');
    }
}
