<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPortfolioTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_program_id', 'pkpa_program_domain_id', 'practice_domain_id', 'code', 'name',
        'version_number', 'status', 'is_current', 'current_key', 'export_configuration',
        'integrity_pact', 'created_by_core_user_id', 'updated_by_core_user_id',
        'activated_at', 'activated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_current' => 'boolean',
            'export_configuration' => 'array',
            'integrity_pact' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo { return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id'); }
    public function programDomain(): BelongsTo { return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id'); }
    public function practiceDomain(): BelongsTo { return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id'); }
    public function sections(): HasMany { return $this->hasMany(PkpaPortfolioTemplateSection::class)->orderBy('sort_order'); }
    public function portfolios(): HasMany { return $this->hasMany(PkpaRotationPortfolio::class); }
}
