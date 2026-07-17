<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaEnrollmentImportRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'core_user_id',
        'student_number',
        'student_name',
        'email',
        'group_code',
        'raw_payload',
        'validation_status',
        'validation_messages',
        'resolved_core_user_id',
        'resolved_enrollment_id',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'validation_messages' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollmentImportBatch::class, 'import_batch_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PkpaEnrollment::class, 'resolved_enrollment_id');
    }
}
