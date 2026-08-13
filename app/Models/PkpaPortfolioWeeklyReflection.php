<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioWeeklyReflection extends Model
{
    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'week_number', 'period_start_date', 'period_end_date',
        'unit', 'target', 'achievement', 'obstacle', 'solution', 'reflection', 'next_plan', 'status',
    ];

    protected function casts(): array
    {
        return ['week_number' => 'integer', 'period_start_date' => 'date', 'period_end_date' => 'date'];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
}
