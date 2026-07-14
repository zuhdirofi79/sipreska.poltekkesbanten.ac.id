<?php

namespace Tests\Feature\Sipreska;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class CustomAppAccessTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    public function test_legacy_role_null_is_denied_custom_app(): void
    {
        $legacy = $this->legacyFilamentUser();

        $this->actingAs($legacy)
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_legacy_cannot_access_custom_password_change(): void
    {
        $legacy = $this->legacyFilamentUser();

        $this->actingAs($legacy)
            ->get('/ganti-password')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_user_is_denied_custom_app(): void
    {
        $user = $this->adminUnit('d3-kebidanan', ['is_active' => false]);

        $this->actingAs($user)
            ->get('/kegiatan')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_superadmin_can_access_all_units_data_routes(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)
            ->get('/kegiatan')
            ->assertOk();

        $this->actingAs($superadmin)
            ->get('/kalender')
            ->assertOk();

        $this->actingAs($superadmin)
            ->get('/master-kategori')
            ->assertOk();
    }

    public function test_admin_unit_cannot_access_superadmin_routes(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        $this->actingAs($admin)->get('/master-kategori')->assertForbidden();
        $this->actingAs($admin)->get('/master-unit')->assertForbidden();
        $this->actingAs($admin)->get('/log-aktivitas')->assertForbidden();
    }

    public function test_legacy_user_can_still_access_filament_panel_gate(): void
    {
        $legacy = $this->legacyFilamentUser();

        $this->assertTrue($legacy->canAccessPanel(Filament::getPanel('admin')));
    }
}
