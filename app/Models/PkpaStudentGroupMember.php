<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaStudentGroupMember extends Model
{
    protected $fillable = [
        'pkpa_student_group_id',
        'pkpa_enrollment_id',
        'joined_at',
        'left_at',
        'status',
        'notes',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PkpaStudentGroup::class, 'pkpa_student_group_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id');
    }
}
