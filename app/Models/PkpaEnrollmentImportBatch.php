<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaEnrollmentImportBatch extends Model
{
    public const STATUSES = ['uploaded', 'validating', 'ready', 'importing', 'completed', 'failed', 'cancelled'];

    protected $fillable = [
        'pkpa_program_id',
        'original_filename',
        'stored_filename',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'skipped_rows',
        'started_at',
        'completed_at',
        'created_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PkpaEnrollmentImportRow::class, 'import_batch_id')->orderBy('row_number');
    }
}
