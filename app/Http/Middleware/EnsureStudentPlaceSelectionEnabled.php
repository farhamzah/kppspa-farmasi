<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentPlaceSelectionEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('my_pkpa.student_place_selection_enabled'), 403, 'Pemilihan tempat oleh mahasiswa dinonaktifkan. Penempatan PKPA ditetapkan oleh Koordinator PKPA.');

        return $next($request);
    }
}
