<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\KpOrientationTest;
use App\Models\KpOrientationTestAttempt;
use Illuminate\Http\Request;

class OrientationTestResultController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'type']);
        $attempts = KpOrientationTestAttempt::query()
            ->with(['test', 'student.user'])
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas('test', fn ($query) => $query->where('type', $type)))
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('student.user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('student', fn ($query) => $query->where('nim', 'like', "%{$search}%"));
                });
            })
            ->latest('submitted_at')
            ->paginate(15)
            ->withQueryString();

        return view('management.orientation-tests.index', [
            'attempts' => $attempts,
            'filters' => $filters,
            'tests' => KpOrientationTest::query()->orderBy('type')->get(),
        ]);
    }

    public function show(KpOrientationTestAttempt $attempt)
    {
        return view('management.orientation-tests.show', [
            'attempt' => $attempt->load(['test', 'student.user', 'answers.question']),
        ]);
    }
}
