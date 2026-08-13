<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioSelfAssessment extends Model
{
    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'aspect', 'score', 'evidence_experience',
        'strength', 'weakness', 'improvement_plan', 'final_reflection', 'status',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
}
