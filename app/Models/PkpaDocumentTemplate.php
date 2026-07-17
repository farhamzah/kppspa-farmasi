<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaDocumentTemplate extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'available_placeholders' => 'array',
            'margin_config' => 'array',
            'header_config' => 'array',
            'footer_config' => 'array',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'activated_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PkpaDocumentType::class, 'pkpa_document_type_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PkpaGeneratedDocument::class);
    }
}
