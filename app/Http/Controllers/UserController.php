<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly PasswordService $passwordService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('unit')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('unit_id'), fn ($query) => $query->where('unit_id', $request->integer('unit_id')))
            ->when($request->has('is_active') && $request->string('is_active') !== '', function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'units' => Unit::query()->active()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'units' => Unit::query()->active()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $role = UserRole::from($request->string('role')->toString());
        $temporaryPassword = $this->passwordService->generateTemporaryPassword();

        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($temporaryPassword),
            'role' => $role,
            'unit_id' => $role === UserRole::Superadmin ? null : $request->integer('unit_id'),
            'is_active' => $request->boolean('is_active'),
            'must_change_password' => true,
        ]);

        $this->auditLog->logModelChange(
            'user_created',
            $user,
            null,
            $user->only(['name', 'email', 'role', 'unit_id', 'is_active', 'must_change_password']),
            $request,
        );

        return redirect()
            ->route('users.edit', $user)
            ->with('success', 'Pengguna berhasil dibuat.')
            ->with('temporary_password', $temporaryPassword);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'units' => Unit::query()->active()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $oldValues = $user->only(['name', 'email', 'role', 'unit_id', 'is_active']);
        $role = UserRole::from($request->string('role')->toString());

        $user->fill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'role' => $role,
            'unit_id' => $role === UserRole::Superadmin ? null : $request->integer('unit_id'),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $this->auditLog->logModelChange(
            'user_updated',
            $user,
            $oldValues,
            $user->only(['name', 'email', 'role', 'unit_id', 'is_active']),
            $request,
        );

        return redirect()
            ->route('users.edit', $user)
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);

        $newActive = ! $user->is_active;

        if (! $request->user()?->can('demoteOrDeactivate', [$user, $user->role, $newActive])) {
            abort(403, 'Tidak dapat mengubah status superadmin terakhir.');
        }

        $oldValues = $user->only(['is_active']);
        $user->is_active = $newActive;
        $user->save();

        $this->auditLog->logModelChange(
            $newActive ? 'user_activated' : 'user_deactivated',
            $user,
            $oldValues,
            $user->only(['is_active']),
            $request,
        );

        if (! $newActive) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('success', 'Status pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $temporaryPassword = $this->passwordService->generateTemporaryPassword();

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->auditLog->log('password_reset', 'user', $user->id, null, null, $request);

        return back()
            ->with('success', 'Password sementara berhasil dibuat.')
            ->with('temporary_password', $temporaryPassword);
    }
}
