<?php

namespace Tests\Feature\Sipreska;

use App\Models\Activity;
use App\Models\AttendanceForm;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceSharePanelTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    private function activityWithForm(array $formAttributes = []): array
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Workshop Kesehatan Masyarakat',
        ]);

        $form = AttendanceForm::query()->create(array_merge([
            'activity_id' => $activity->id,
            'public_token' => 'share-panel-token-abc123',
            'is_open' => true,
            'signature_required' => true,
        ], $formAttributes));

        return [$admin, $activity, $form];
    }

    public function test_attendance_tab_renders_share_section(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Bagikan Form Absensi')
            ->assertSee('Tautan Publik Absensi')
            ->assertSee('Peserta dapat membuka tautan ini tanpa login dan mengisi daftar hadir seperti Google Form.');
    }

    public function test_public_url_uses_named_attendance_route_and_token(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        $expectedUrl = route('attendance.public.show', ['public_token' => $form->public_token]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee($expectedUrl, false)
            ->assertSee('/absensi/share-panel-token-abc123', false);
    }

    public function test_copy_button_is_type_button_not_submit(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $response = $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']));

        $response->assertOk();
        $this->assertStringContainsString('type="button"', (string) $response->getContent());
        $this->assertStringContainsString('Salin Link', (string) $response->getContent());
        $this->assertStringContainsString('attendanceSharePanel', (string) $response->getContent());
        $this->assertStringNotContainsString('onclick="navigator.clipboard', (string) $response->getContent());
    }

    public function test_active_form_enables_share_buttons(): void
    {
        [$admin, $activity] = $this->activityWithForm(['is_open' => true]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Form aktif')
            ->assertSee(':disabled="copyDisabled"', false);
    }

    public function test_inactive_form_disables_share_buttons_and_shows_helper(): void
    {
        [$admin, $activity] = $this->activityWithForm(['is_open' => false]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Form absensi belum aktif. Aktifkan form terlebih dahulu sebelum membagikannya kepada peserta.')
            ->assertSee('Aktifkan form terlebih dahulu.', false)
            ->assertSee('Belum aktif');
    }

    public function test_preview_link_opens_public_route_in_new_tab(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        $publicUrl = route('attendance.public.show', ['public_token' => $form->public_token]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Pratinjau Form')
            ->assertSee('href="'.$publicUrl.'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_whatsapp_link_is_url_encoded(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        $publicUrl = route('attendance.public.show', ['public_token' => $form->public_token]);
        $message = 'Silakan isi daftar hadir kegiatan '.$activity->title.' melalui tautan berikut: '.$publicUrl;
        $whatsappUrl = 'https://wa.me/?text='.rawurlencode($message);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Bagikan ke WhatsApp')
            ->assertSee('data-whatsapp-url="'.$whatsappUrl.'"', false);
    }

    public function test_qr_code_uses_public_attendance_url_not_admin_url(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        $publicUrl = route('attendance.public.show', ['public_token' => $form->public_token]);
        $qrImageUrl = route('activities.daftar-hadir.qr', $activity);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('data-qr-image-url="'.$qrImageUrl.'"', false)
            ->assertSee($publicUrl, false)
            ->assertSee('Download PNG')
            ->assertSee('Cetak');

        $this->actingAs($admin)
            ->get($qrImageUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_share_panel_avoids_placeholder_links_and_hostinger_urls(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $content = (string) $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('href="#"', $content);
        $this->assertStringNotContainsString('javascript:void(0)', $content);
        $this->assertStringNotContainsString('hostinger', strtolower($content));
        $this->assertStringNotContainsString('filemanager', strtolower($content));
    }

    public function test_share_panel_has_responsive_layout_classes(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('w-full sm:w-auto', false)
            ->assertSee('min-w-0 truncate', false)
            ->assertSee('flex-col gap-2 sm:flex-row', false);
    }

    public function test_closed_form_by_time_still_allows_copy_with_badge(): void
    {
        [$admin, $activity] = $this->activityWithForm([
            'is_open' => true,
            'closes_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Form ditutup')
            ->assertSee('Form absensi sudah ditutup. Tautan masih dapat disalin untuk arsip atau pratinjau.');
    }
}
