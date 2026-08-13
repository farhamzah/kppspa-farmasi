<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioReview extends Model
{
    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'pkpa_portfolio_section_record_id',
        'reviewer_type', 'reviewer_core_user_id', 'action', 'comments',
        'privacy_findings', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['privacy_findings' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
    public function sectionRecord(): BelongsTo { return $this->belongsTo(PkpaPortfolioSectionRecord::class, 'pkpa_portfolio_section_record_id'); }
}
