<?php

namespace Tests\Feature\Phase1A;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UnitSeeder::class);
    }

    public function test_admin_unit_cannot_access_user_management(): void
    {
        $unit = Unit::query()->where('slug', 'd3-kebidanan')->first();

        $admin = User::factory()->create([
            'role' => UserRole::AdminUnit,
            'unit_id' => $unit->id,
            'password' => 'password-123',
        ]);

        $this->actingAs($admin)
            ->get('/manajemen-pengguna')
            ->assertForbidden();
    }

    public function test_superadmin_can_access_user_management(): void
    {
        $superadmin = User::factory()->superadmin()->create([
            'password' => 'password-123',
        ]);

        $this->actingAs($superadmin)
            ->get('/manajemen-pengguna')
            ->assertOk();
    }

    public function test_superadmin_cannot_deactivate_self(): void
    {
        $superadmin = User::factory()->superadmin()->create([
            'password' => 'password-123',
        ]);

        $this->actingAs($superadmin)
            ->post(route('users.toggle-status', $superadmin))
            ->assertForbidden();
    }

    public function test_cannot_deactivate_last_active_superadmin(): void
    {
        $superadmin = User::factory()->superadmin()->create([
            'password' => 'password-123',
        ]);

        $this->actingAs($superadmin)
            ->put(route('users.update', $superadmin), [
                'name' => $superadmin->name,
                'email' => $superadmin->email,
                'role' => UserRole::Superadmin->value,
                'unit_id' => '',
                'is_active' => '0',
            ])
            ->assertSessionHasErrors('is_active');
    }

    public function test_superadmin_must_have_null_unit_on_store(): void
    {
        $superadmin = User::factory()->superadmin()->create(['password' => 'password-123']);
        $unit = Unit::query()->first();

        $this->actingAs($superadmin)
            ->post('/manajemen-pengguna', [
                'name' => 'New Super',
                'email' => 'newsuper@example.com',
                'role' => UserRole::Superadmin->value,
                'unit_id' => $unit->id,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('unit_id');
    }

    public function test_admin_unit_requires_unit_on_store(): void
    {
        $superadmin = User::factory()->superadmin()->create(['password' => 'password-123']);

        $this->actingAs($superadmin)
            ->post('/manajemen-pengguna', [
                'name' => 'Admin Unit Baru',
                'email' => 'adminunit@example.com',
                'role' => UserRole::AdminUnit->value,
                'unit_id' => '',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('unit_id');
    }
}
