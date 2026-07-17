<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalAccountManagementEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('my_pspa.local_account_management_enabled'), 403, 'Manajemen akun lokal dinonaktifkan. Kelola akun, password, dan role melalui Core Farmasi.');

        return $next($request);
    }
}
