<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait SipreskaTestHelpers
{
    use RefreshDatabase;

    protected function seedSipreskaBasics(): void
    {
        $this->seed([UnitSeeder::class, CategorySeeder::class]);
    }

    protected function superadmin(array $attributes = []): User
    {
        return User::factory()->superadmin()->create(array_merge([
            'password' => 'password-123',
            'must_change_password' => false,
        ], $attributes));
    }

    protected function adminUnit(string $unitSlug, array $attributes = []): User
    {
        $unit = Unit::query()->where('slug', $unitSlug)->firstOrFail();

        return User::factory()->create(array_merge([
            'role' => UserRole::AdminUnit,
            'unit_id' => $unit->id,
            'password' => 'password-123',
            'must_change_password' => false,
        ], $attributes));
    }

    protected function legacyFilamentUser(): User
    {
        return User::factory()->create([
            'email' => config('sipreska.legacy_filament_email'),
            'role' => null,
            'unit_id' => null,
            'password' => 'password-123',
            'must_change_password' => false,
        ]);
    }

    protected function defaultCategory(): Category
    {
        return Category::query()->where('slug', 'rapat')->firstOrFail();
    }

    protected function unitBySlug(string $slug): Unit
    {
        return Unit::query()->where('slug', $slug)->firstOrFail();
    }

    protected function minimalPngBase64(): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
