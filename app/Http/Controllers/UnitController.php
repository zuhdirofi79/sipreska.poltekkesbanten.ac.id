<?php

namespace App\Http\Controllers;

use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::query()
            ->withCount(['activities', 'users'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('short_name', 'like', $search)
                        ->orWhere('slug', 'like', $search);
                });
            })
            ->orderBy('name')
            ->get();

        return view('master.units.index', [
            'units' => $units,
        ]);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $slug = $this->resolveSlug(
                $request->input('slug'),
                $request->string('name')->toString(),
            );

            $unit = Unit::query()->create([
                'name' => $request->string('name')->toString(),
                'short_name' => $request->string('short_name')->toString(),
                'slug' => $slug,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->auditLog->logModelChange(
                'unit_created',
                $unit,
                null,
                $unit->only(['name', 'short_name', 'slug', 'is_active']),
                $request,
            );
        });

        return redirect()
            ->route('master.units.index')
            ->with('success', 'Unit berhasil dibuat.');
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $oldValues = $unit->only(['name', 'short_name', 'slug', 'is_active']);

        $unit->fill([
            'name' => $request->string('name')->toString(),
            'short_name' => $request->string('short_name')->toString(),
            'slug' => $this->resolveSlug(
                $request->input('slug'),
                $request->string('name')->toString(),
                $unit->id,
            ),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $this->auditLog->logModelChange(
            'unit_updated',
            $unit,
            $oldValues,
            $unit->only(['name', 'short_name', 'slug', 'is_active']),
            $request,
        );

        return redirect()
            ->route('master.units.index')
            ->with('success', 'Unit berhasil diperbarui.');
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        if ($unit->isInUse()) {
            return back()->with('error', 'Unit masih digunakan oleh kegiatan atau pengguna.');
        }

        DB::transaction(function () use ($unit, $request) {
            $oldValues = $unit->only(['name', 'short_name', 'slug', 'is_active']);
            $unit->delete();

            $this->auditLog->log(
                'unit_deleted',
                'unit',
                $unit->id,
                $oldValues,
                null,
                $request,
            );
        });

        return redirect()
            ->route('master.units.index')
            ->with('success', 'Unit berhasil dihapus.');
    }

    public function toggleStatus(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('toggle', $unit);

        $oldValues = $unit->only(['is_active']);
        $unit->is_active = ! $unit->is_active;
        $unit->save();

        $this->auditLog->logModelChange(
            $unit->is_active ? 'unit_activated' : 'unit_deactivated',
            $unit,
            $oldValues,
            $unit->only(['is_active']),
            $request,
        );

        return back()->with('success', 'Status unit berhasil diperbarui.');
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name) ?: 'unit';
        $candidate = $base;
        $counter = 1;

        while (Unit::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
