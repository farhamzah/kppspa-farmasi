<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioTemplateSection extends Model
{
    protected $fillable = [
        'pkpa_portfolio_template_id', 'code', 'title', 'source_type', 'reviewer_type',
        'is_required', 'minimum_items', 'sort_order', 'requirement_rules', 'content_schema',
        'static_content',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'minimum_items' => 'integer',
            'sort_order' => 'integer',
            'requirement_rules' => 'array',
            'content_schema' => 'array',
        ];
    }

    public function template(): BelongsTo { return $this->belongsTo(PkpaPortfolioTemplate::class, 'pkpa_portfolio_template_id'); }
}
