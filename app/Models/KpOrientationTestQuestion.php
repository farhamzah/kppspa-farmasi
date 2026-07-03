<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpOrientationTestQuestion extends Model
{
    protected $fillable = ['kp_orientation_test_id', 'question_text', 'choices', 'correct_choice_index', 'explanation', 'points', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'choices' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function test()
    {
        return $this->belongsTo(KpOrientationTest::class, 'kp_orientation_test_id');
    }

    public function correctChoice(): string
    {
        return (string) ($this->choices[$this->correct_choice_index] ?? '-');
    }
}
