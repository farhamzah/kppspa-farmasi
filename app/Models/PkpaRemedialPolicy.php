<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRemedialPolicy extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_program_id', 'code', 'name', 'description', 'applies_to', 'trigger_type', 'maximum_attempts', 'score_replacement_policy', 'maximum_replacement_score', 'require_coordinator_approval', 'require_new_rotation', 'require_new_assessment', 'status', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['maximum_attempts' => 'integer', 'maximum_replacement_score' => 'decimal:4', 'require_coordinator_approval' => 'boolean', 'require_new_rotation' => 'boolean', 'require_new_assessment' => 'boolean'];
    }
}
