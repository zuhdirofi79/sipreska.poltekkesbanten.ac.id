<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function edit(): View
    {
        return view('profile.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldValues = $user->only(['name', 'email']);

        $user->fill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
        ])->save();

        $this->auditLog->logModelChange(
            'profile_updated',
            $user,
            $oldValues,
            $user->only(['name', 'email']),
            $request,
        );

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Profil berhasil diperbarui.');
    }
}
