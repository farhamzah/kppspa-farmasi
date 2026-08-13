<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioPublication extends Model
{
    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'publication_number', 'status',
        'publication_snapshot', 'published_at', 'published_by_core_user_id',
    ];

    protected function casts(): array
    {
        return ['publication_number' => 'integer', 'publication_snapshot' => 'array', 'published_at' => 'datetime'];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
}
