<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpOrientationTestAnswer extends Model
{
    protected $fillable = ['kp_orientation_test_attempt_id', 'kp_orientation_test_question_id', 'selected_choice_index', 'is_correct', 'points_awarded'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function attempt()
    {
        return $this->belongsTo(KpOrientationTestAttempt::class, 'kp_orientation_test_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(KpOrientationTestQuestion::class, 'kp_orientation_test_question_id');
    }

    public function selectedChoice(): string
    {
        return (string) ($this->question?->choices[$this->selected_choice_index] ?? '-');
    }
}
