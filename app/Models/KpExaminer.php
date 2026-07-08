<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpExaminer extends Model
{
    protected $table = 'kp_exam_examiners';

    protected $fillable = ['kp_exam_id', 'lecturer_id', 'sort_order'];

    public function exam()
    {
        return $this->belongsTo(KpExam::class, 'kp_exam_id');
    }

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class);
    }
}
