<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaSupervisorUnavailabilityPeriod extends Model
{
    use SoftDeletes;

    public const TYPES = ['internal', 'field'];
    public const STATUSES = ['active', 'cancelled', 'expired'];

    protected $fillable = [
        'supervisor_type',
        'internal_supervisor_eligibility_id',
        'site_field_supervisor_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function internalSupervisorEligibility(): BelongsTo
    {
        return $this->belongsTo(PkpaInternalSupervisorEligibility::class, 'internal_supervisor_eligibility_id');
    }

    public function siteFieldSupervisor(): BelongsTo
    {
        return $this->belongsTo(PkpaSiteFieldSupervisor::class, 'site_field_supervisor_id');
    }
}
