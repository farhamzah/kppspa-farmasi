<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaPortfolioSignedDocument extends Model
{
    protected $fillable = ['pkpa_rotation_portfolio_id', 'version_number', 'disk', 'path', 'download_filename',
        'file_size', 'checksum', 'content_checksum', 'uploaded_by_core_user_id', 'signatures_confirmed_at'];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'file_size' => 'integer', 'signatures_confirmed_at' => 'datetime'];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id');
    }
}
