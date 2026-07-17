<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaDocumentDistributionLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PkpaGeneratedDocument::class, 'pkpa_generated_document_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(PkpaDocumentRecipient::class, 'recipient_id');
    }
}
