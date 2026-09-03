<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPortfolioCaseReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_rotation_portfolio_id', 'case_code', 'case_date', 'patient_initials',
        'gender', 'age', 'weight_kg', 'height_cm', 'complaint', 'diagnosis', 'history',
        'past_medical_history', 'family_history', 'allergy', 'medication_use', 'drug_data',
        'soap', 'drp', 'drp_items', 'intervention', 'monitoring', 'evaluation', 'education',
        'conclusion', 'references', 'anonymization_confirmed',
        'privacy_warnings', 'status', 'created_by_core_user_id', 'reviewed_by_core_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'case_date' => 'date',
            'age' => 'integer',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'drug_data' => 'array',
            'soap' => 'array',
            'drp_items' => 'array',
            'anonymization_confirmed' => 'boolean',
            'privacy_warnings' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PkpaRotationPortfolio::class, 'pkpa_rotation_portfolio_id'); }
}
