<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\KpOrientationTest;
use App\Models\KpOrientationTestAttempt;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrientationTestController extends Controller
{
    public function index(Request $request)
    {
        $student = $this->student($request);
        $tests = KpOrientationTest::query()
            ->with(['attempts' => fn ($query) => $query->where('student_id', $student->id)])
            ->where('status', 'aktif')
            ->orderByRaw("case when type = 'pre' then 1 else 2 end")
            ->get();

        return view('student.orientation-tests.index', compact('tests', 'student'));
    }

    public function show(Request $request, KpOrientationTest $test)
    {
        $student = $this->student($request);
        $attempt = $test->attempts()->where('student_id', $student->id)->first();

        if ($attempt) {
            return redirect()->route('student.orientation-tests.result', $attempt);
        }

        abort_unless($test->status === 'aktif', 404);

        return view('student.orientation-tests.show', [
            'test' => $test->load('activeQuestions'),
            'student' => $student,
        ]);
    }

    public function submit(Request $request, KpOrientationTest $test)
    {
        $student = $this->student($request);
        abort_unless($test->status === 'aktif', 404);

        if ($existing = $test->attempts()->where('student_id', $student->id)->first()) {
            return redirect()->route('student.orientation-tests.result', $existing)
                ->with('status', 'Anda sudah mengerjakan '.$test->typeLabel().'.');
        }

        $questions = $test->activeQuestions()->get();
        $answers = $request->input('answers', []);

        $missing = $questions->first(fn ($question) => ! array_key_exists((string) $question->id, $answers));

        if ($missing) {
            throw ValidationException::withMessages(['answers' => 'Semua soal wajib dijawab sebelum submit.']);
        }

        $attempt = DB::transaction(function () use ($test, $student, $request, $questions, $answers): KpOrientationTestAttempt {
            $score = 0;
            $maxScore = (int) $questions->sum('points');

            $attempt = KpOrientationTestAttempt::create([
                'kp_orientation_test_id' => $test->id,
                'student_id' => $student->id,
                'user_id' => $request->user()->id,
                'status' => 'submitted',
                'score' => 0,
                'max_score' => $maxScore,
                'percentage' => 0,
                'submitted_at' => now(),
            ]);

            foreach ($questions as $question) {
                $selected = (int) $answers[$question->id];
                $isCorrect = $selected === (int) $question->correct_choice_index;
                $points = $isCorrect ? (int) $question->points : 0;
                $score += $points;

                $attempt->answers()->create([
                    'kp_orientation_test_question_id' => $question->id,
                    'selected_choice_index' => $selected,
                    'is_correct' => $isCorrect,
                    'points_awarded' => $points,
                ]);
            }

            $attempt->update([
                'score' => $score,
                'percentage' => $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0,
            ]);

            return $attempt;
        });

        return redirect()->route('student.orientation-tests.result', $attempt)
            ->with('status', $test->typeLabel().' berhasil dikirim.');
    }

    public function result(Request $request, KpOrientationTestAttempt $attempt)
    {
        $student = $this->student($request);
        abort_unless((int) $attempt->student_id === (int) $student->id, 403);

        return view('student.orientation-tests.result', [
            'attempt' => $attempt->load(['test', 'answers.question']),
        ]);
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;

        abort_unless($student, 403);

        return $student;
    }
}
