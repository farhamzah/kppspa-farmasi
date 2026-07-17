<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaDocumentNumberingRule extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function type(): BelongsTo
    {
        return $this->belongsTo(PkpaDocumentType::class, 'pkpa_document_type_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }
}
