<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function public(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => 'MY PSPA',
        ]);
    }

    public function admin(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'koordinator_kp']), 403);

        return response()->json([
            'status' => 'ok',
            'app' => 'MY PSPA',
            'checks' => [
                'database' => $this->check(fn () => DB::select('select 1')),
                'cache' => $this->check(fn () => Cache::put('health:my-pspa', now()->toIso8601String(), 10)),
                'storage_private' => $this->check(fn () => Storage::disk('local')->exists('.')),
                'queue_connection' => config('queue.default'),
                'core_mode' => config('kp_auth.mode'),
            ],
        ]);
    }

    private function check(callable $callback): string
    {
        try {
            $callback();

            return 'ok';
        } catch (\Throwable) {
            return 'failed';
        }
    }
}
