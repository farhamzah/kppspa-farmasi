<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioSectionRecord extends Model
{
    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'pkpa_portfolio_template_section_id',
        'section_code', 'source_type', 'status', 'auto_source_refs', 'manual_payload',
        'completion_snapshot', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'auto_source_refs' => 'array',
            'manual_payload' => 'array',
            'completion_snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
    public function templateSection(): BelongsTo { return $this->belongsTo(PkpaPortfolioTemplateSection::class, 'pkpa_portfolio_template_section_id'); }
}
