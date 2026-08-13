<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioExportVersion extends Model
{
    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'pkpa_portfolio_publication_id',
        'version_number', 'output_format', 'status', 'disk', 'path',
        'original_filename', 'stored_filename', 'mime_type', 'file_size',
        'checksum', 'metadata', 'generated_at', 'generated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'file_size' => 'integer', 'metadata' => 'array', 'generated_at' => 'datetime'];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
    public function publication(): BelongsTo { return $this->belongsTo(PkpaPortfolioPublication::class, 'pkpa_portfolio_publication_id'); }
}
