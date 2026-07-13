<?php

namespace Tests\Feature\Phase1A;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UnitSeeder::class);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $unit = Unit::query()->where('slug', 'd3-kebidanan')->first();

        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password-123',
            'role' => UserRole::AdminUnit,
            'unit_id' => $unit->id,
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_must_change_password_blocks_dashboard(): void
    {
        $user = User::factory()->superadmin()->mustChangePassword()->create([
            'password' => 'password-123',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.change'));
    }

    public function test_password_change_clears_flag_and_allows_dashboard(): void
    {
        $user = User::factory()->superadmin()->mustChangePassword()->create([
            'password' => 'OldPassword1',
        ]);

        $this->actingAs($user)
            ->put('/ganti-password', [
                'current_password' => 'OldPassword1',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_login_records_server_timestamp(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);

        $user = User::factory()->superadmin()->create([
            'email' => 'login@example.com',
            'password' => 'password-123',
        ]);

        $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'password-123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('Asia/Jakarta', $user->last_login_at->timezone->getName());
    }

    public function test_logout_requires_post(): void
    {
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->get('/logout')->assertNotFound();
    }

    public function test_audit_log_does_not_store_password_on_reset(): void
    {
        $superadmin = User::factory()->superadmin()->create(['password' => 'password-123']);
        $target = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->post(route('users.reset-password', $target))
            ->assertRedirect();

        $log = ActivityLog::query()->where('action', 'password_reset')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }
}
