<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403);

        if ($request->user()->role === 'PETUGAS') {
            abort_unless(
                $request->user()->puskesmas_id
                && $request->user()->puskesmas()->where('status', 'AKTIF')->exists(),
                403,
                'Akun petugas belum terhubung dengan puskesmas aktif.'
            );
        }

        return $next($request);
    }
}
