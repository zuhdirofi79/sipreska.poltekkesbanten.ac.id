<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password) {
            if (! $request->routeIs('password.change', 'password.update', 'logout')) {
                return redirect()
                    ->route('password.change')
                    ->with('warning', 'Anda wajib mengganti password sebelum melanjutkan.');
            }
        }

        return $next($request);
    }
}
