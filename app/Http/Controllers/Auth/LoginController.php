<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Terlalu banyak percobaan login. Coba lagi nanti.']);
        }

        $credentials = $request->only('email', 'password');
        $user = \App\Models\User::query()->where('email', $credentials['email'])->first();

        if ($user !== null && ! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);
            $this->auditLog->log('login_rejected_inactive', 'user', $user->id, null, null, $request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Kredensial tidak valid.']);
        }

        if ($user !== null && ! $user->hasValidCustomRole()) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Kredensial tidak valid.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Kredensial tidak valid.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->auditLog->log('login_success', 'user', $user->id, null, null, $request);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('dashboard'));
    }
}
