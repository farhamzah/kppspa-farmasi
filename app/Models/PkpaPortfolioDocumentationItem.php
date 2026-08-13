<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPortfolioDocumentationItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'category', 'activity_date', 'activity',
        'description', 'competency_label', 'disk', 'path', 'original_filename',
        'mime_type', 'file_size', 'anonymization_confirmed', 'consent_confirmed',
        'status', 'field_reviewed_by_core_user_id', 'field_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'file_size' => 'integer',
            'anonymization_confirmed' => 'boolean',
            'consent_confirmed' => 'boolean',
            'field_reviewed_at' => 'datetime',
        ];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
}
