<?php

namespace Tests\Feature\Sipreska;

use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Support\AttendancePublicRoutes;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceQrPanelTest extends TestCase
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
            'public_token' => 'qr-panel-token-abc123',
            'is_open' => true,
            'signature_required' => true,
        ], $formAttributes));

        return [$admin, $activity, $form];
    }

    public function test_attendance_tab_renders_qr_panel_below_share_panel(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $content = (string) $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->getContent();

        $sharePos = strpos($content, 'Bagikan Form Absensi');
        $qrPos = strpos($content, 'QR Code Daftar Hadir');
        $settingsPos = strpos($content, 'Buka Form Presensi');

        $this->assertNotFalse($sharePos);
        $this->assertNotFalse($qrPos);
        $this->assertNotFalse($settingsPos);
        $this->assertLessThan($qrPos, $sharePos);
        $this->assertLessThan($settingsPos, $qrPos);
    }

    public function test_qr_panel_has_generate_download_and_share_buttons(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Generate QR')
            ->assertSee('Download QR')
            ->assertSee('Share QR')
            ->assertSee('attendanceQrPanel', false)
            ->assertSee('data-attendance-qr-panel', false);
    }

    public function test_qr_preview_endpoint_returns_png(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $qrImageUrl = route('activities.daftar-hadir.qr', $activity);

        $this->actingAs($admin)
            ->get($qrImageUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_qr_download_endpoint_returns_1024_square_png(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.qr', $activity).'?size=1024');

        $response->assertOk()->assertHeader('content-type', 'image/png');

        $info = @getimagesizefromstring((string) $response->getContent());

        $this->assertIsArray($info);
        $this->assertSame(1024, $info[0]);
        $this->assertSame(1024, $info[1]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }

    public function test_qr_uses_same_public_url_as_share_panel(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        $publicUrl = AttendancePublicRoutes::showUrl($form->public_token);
        $qrImageUrl = route('activities.daftar-hadir.qr', $activity);
        $qrDownloadUrl = $qrImageUrl.'?size=1024';

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('data-public-url="'.$publicUrl.'"', false)
            ->assertSee('data-qr-preview-url="'.$qrImageUrl.'"', false)
            ->assertSee('data-qr-download-url="'.$qrDownloadUrl.'"', false);
    }

    public function test_qr_download_filename_uses_activity_slug(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('activitySlug\\u0022:\\u0022workshop-kesehatan-masyarakat', false);
    }

    public function test_share_panel_remains_unmodified(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Bagikan Form Absensi')
            ->assertSee('Salin Link')
            ->assertSee('Pratinjau Form')
            ->assertSee('Bagikan ke WhatsApp')
            ->assertSee('Tautan Publik Absensi')
            ->assertSee('attendanceSharePanel', false);
    }
}
