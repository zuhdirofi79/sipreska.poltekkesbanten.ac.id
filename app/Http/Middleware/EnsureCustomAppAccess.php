<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomAppAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda tidak aktif.']);
        }

        if (! $user->role instanceof UserRole) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun ini tidak memiliki akses aplikasi SIPRESKA custom.']);
        }

        if ($user->role === UserRole::Superadmin && $user->unit_id !== null) {
            abort(403, 'Superadmin tidak boleh memiliki unit.');
        }

        if ($user->role === UserRole::AdminUnit && $user->unit_id === null) {
            abort(403, 'Admin unit wajib memiliki unit.');
        }

        return $next($request);
    }
}
