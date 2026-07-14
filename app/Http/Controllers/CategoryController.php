<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('activities')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('slug', 'like', $search);
                });
            })
            ->orderBy('name')
            ->get();

        return view('master.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $slug = $this->resolveSlug(
                $request->input('slug'),
                $request->string('name')->toString(),
            );

            $category = Category::query()->create([
                'name' => $request->string('name')->toString(),
                'slug' => $slug,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->auditLog->logModelChange(
                'category_created',
                $category,
                null,
                $category->only(['name', 'slug', 'is_active']),
                $request,
            );
        });

        return redirect()
            ->route('master.categories.index')
            ->with('success', 'Kategori berhasil dibuat.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $oldValues = $category->only(['name', 'slug', 'is_active']);

        $category->fill([
            'name' => $request->string('name')->toString(),
            'slug' => $this->resolveSlug(
                $request->input('slug'),
                $request->string('name')->toString(),
                $category->id,
            ),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $this->auditLog->logModelChange(
            'category_updated',
            $category,
            $oldValues,
            $category->only(['name', 'slug', 'is_active']),
            $request,
        );

        return redirect()
            ->route('master.categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        if ($category->isInUse()) {
            return back()->with('error', 'Kategori masih digunakan oleh kegiatan.');
        }

        DB::transaction(function () use ($category, $request) {
            $oldValues = $category->only(['name', 'slug', 'is_active']);
            $category->delete();

            $this->auditLog->log(
                'category_deleted',
                'category',
                $category->id,
                $oldValues,
                null,
                $request,
            );
        });

        return redirect()
            ->route('master.categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }

    public function toggleStatus(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('toggle', $category);

        $oldValues = $category->only(['is_active']);
        $category->is_active = ! $category->is_active;
        $category->save();

        $this->auditLog->logModelChange(
            $category->is_active ? 'category_activated' : 'category_deactivated',
            $category,
            $oldValues,
            $category->only(['is_active']),
            $request,
        );

        return back()->with('success', 'Status kategori berhasil diperbarui.');
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name) ?: 'kategori';
        $candidate = $base;
        $counter = 1;

        while (Category::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
