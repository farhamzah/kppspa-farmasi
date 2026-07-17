<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationStatusHistory extends Model
{
    protected $fillable = ['pkpa_rotation_run_id', 'from_status', 'to_status', 'reason', 'metadata', 'changed_by_core_user_id', 'changed_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'changed_at' => 'datetime'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }
}
