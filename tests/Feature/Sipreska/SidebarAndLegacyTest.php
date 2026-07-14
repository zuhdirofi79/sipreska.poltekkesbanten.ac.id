<?php

namespace Tests\Feature\Sipreska;

use Illuminate\Support\Facades\Route;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class SidebarAndLegacyTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    public function test_sidebar_menus_have_real_routes_without_placeholders(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(route('activities.index'), false);
        $response->assertSee(route('calendar.index'), false);
        $response->assertSee(route('profile.edit'), false);
        $response->assertDontSee('href="#"', false);
        $response->assertDontSee('javascript:void(0)', false);
    }

    public function test_admin_unit_sidebar_hides_superadmin_menus(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertOk();
        $response->assertDontSee(route('master.categories.index'), false);
        $response->assertDontSee(route('users.index'), false);
    }

    public function test_filament_admin_route_is_registered(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('filament.admin.pages.dashboard'));
    }

    public function test_canonical_form_route_works(): void
    {
        $this->get('/form/nonexistent-slug-xyz')->assertNotFound();
    }

    public function test_legacy_slug_redirects_with_302(): void
    {
        \App\Models\EventForm::query()->create([
            'title' => 'Legacy Form',
            'slug' => 'legacy-slug-test',
            'is_active' => true,
        ]);

        $this->get('/legacy-slug-test')
            ->assertRedirect('/form/legacy-slug-test')
            ->assertStatus(302);
    }
}
