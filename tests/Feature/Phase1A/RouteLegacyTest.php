<?php

namespace Tests\Feature\Phase1A;

use App\Models\EventForm;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteLegacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UnitSeeder::class);
    }

    public function test_static_routes_are_not_captured_by_legacy_slug_route(): void
    {
        $this->get('/login')->assertOk();

        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->get('/manajemen-pengguna')->assertRedirect(route('login'));
    }

    public function test_canonical_form_route_opens_legacy_form(): void
    {
        EventForm::query()->create([
            'title' => 'Webinar Menulis Ebook',
            'slug' => 'registerEventMenulisEbook',
            'description' => 'Legacy form',
            'is_active' => true,
        ]);

        $this->get('/form/registerEventMenulisEbook')
            ->assertOk()
            ->assertSee('Webinar Menulis Ebook');
    }

    public function test_legacy_slug_redirects_to_canonical_route(): void
    {
        EventForm::query()->create([
            'title' => 'Webinar Menulis Ebook',
            'slug' => 'registerEventMenulisEbook',
            'description' => 'Legacy form',
            'is_active' => true,
        ]);

        $this->get('/registerEventMenulisEbook')
            ->assertRedirect(route('public-form.show', 'registerEventMenulisEbook'));
    }

    public function test_unknown_slug_returns_not_found(): void
    {
        $this->get('/slug-tidak-ada')->assertNotFound();
    }

    public function test_reserved_slug_returns_not_found_on_legacy_route(): void
    {
        $this->get('/kegiatan')->assertNotFound();
    }
}
