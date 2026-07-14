<?php

namespace Tests\Feature\Sipreska;

use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Support\SipreskaBranding;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    public function test_logo_helper_uses_asset_path_without_hardcoded_domain(): void
    {
        $url = SipreskaBranding::logoUrl();

        $this->assertStringContainsString('images/logo-poltekkes-banten.png', $url);
        $this->assertStringNotContainsString('hstgr.io', $url);
        $this->assertStringNotContainsString('localhost', $url);
    }

    public function test_login_page_renders_logo_asset(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee(SipreskaBranding::logoUrl(), false);
        $response->assertSee('sipreska-logo-login', false);
    }

    public function test_dashboard_renders_sidebar_and_header_logo(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/dashboard');

        $response->assertOk();
        $response->assertSee(SipreskaBranding::logoUrl(), false);
        $response->assertSee('sipreska-logo-sidebar', false);
        $response->assertSee('sipreska-logo-header', false);
    }

    public function test_public_attendance_page_renders_letterhead_not_small_logo(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $form = AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => false,
        ]);

        $response = $this->get(route('attendance.public.show', ['public_token' => $form->public_token]));

        $response->assertOk();
        $response->assertSee(asset('images/daftar-hadir-header.jpeg'), false);
        $response->assertSee('attendance-letterhead', false);
        $response->assertSee('Form Daftar Hadir Kegiatan', false);
        $response->assertDontSee('sipreska-logo-login', false);
        $response->assertDontSee(SipreskaBranding::logoUrl(), false);
    }
}
