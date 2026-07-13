<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
        ])->save();

        $this->auditLog->log('password_changed', 'user', $user->id, null, null, $request);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password berhasil diperbarui.');
    }
}
