<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaDocumentGenerationJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_formats' => 'array',
            'request_snapshot' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PkpaGeneratedDocument::class, 'pkpa_generated_document_id');
    }
}
