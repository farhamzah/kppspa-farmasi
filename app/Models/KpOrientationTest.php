<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpOrientationTest extends Model
{
    protected $fillable = ['title', 'type', 'description', 'status', 'total_points'];

    public function questions()
    {
        return $this->hasMany(KpOrientationTestQuestion::class)->orderBy('sort_order');
    }

    public function activeQuestions()
    {
        return $this->questions()->where('is_active', true);
    }

    public function attempts()
    {
        return $this->hasMany(KpOrientationTestAttempt::class);
    }

    public function typeLabel(): string
    {
        return $this->type === 'pre' ? 'Pre-Test' : 'Post-Test';
    }
}
