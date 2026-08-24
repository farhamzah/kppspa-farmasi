<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\PkpaProgram;
use App\Services\PkpaCoreDirectorySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PkpaCoreDirectoryController extends Controller
{
    public function __construct(private readonly PkpaCoreDirectorySearchService $searchService)
    {
    }

    public function lecturers(Request $request): JsonResponse
    {
        $this->authorizeDirectoryAccess($request);

        return response()->json([
            'data' => $this->searchService->searchInternalSupervisors($request->string('q')->toString(), (int) $request->integer('limit', 10)),
        ]);
    }

    public function fieldSupervisors(Request $request): JsonResponse
    {
        $this->authorizeDirectoryAccess($request);

        return response()->json([
            'data' => $this->searchService->searchFieldSupervisors($request->string('q')->toString(), (int) $request->integer('limit', 10)),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $this->authorizeDirectoryAccess($request);

        $program = $request->filled('program_id')
            ? PkpaProgram::find($request->integer('program_id'))
            : null;

        return response()->json([
            'data' => $this->searchService->searchStudents(
                $request->string('q')->toString(),
                (int) $request->integer('limit', 10),
                $program,
            ),
        ]);
    }

    private function authorizeDirectoryAccess(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'koordinator_kp']), 403);
    }
}
