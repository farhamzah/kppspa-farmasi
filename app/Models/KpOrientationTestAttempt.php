<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpOrientationTestAttempt extends Model
{
    protected $fillable = ['kp_orientation_test_id', 'student_id', 'user_id', 'status', 'score', 'max_score', 'percentage', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function test()
    {
        return $this->belongsTo(KpOrientationTest::class, 'kp_orientation_test_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(KpOrientationTestAnswer::class)->with('question');
    }
}
