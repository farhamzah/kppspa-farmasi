<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaGeneratedDocument extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'generation_context' => 'array',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PkpaDocumentType::class, 'pkpa_document_type_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PkpaDocumentTemplate::class, 'pkpa_document_template_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PkpaGeneratedDocumentVersion::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(PkpaDocumentRecipient::class);
    }

    public function distributionLogs(): HasMany
    {
        return $this->hasMany(PkpaDocumentDistributionLog::class);
    }
}
