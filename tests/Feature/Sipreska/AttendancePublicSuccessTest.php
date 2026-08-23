<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use Illuminate\Support\Facades\Route;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendancePublicSuccessTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    private function openForm(Activity $activity, array $overrides = []): AttendanceForm
    {
        return AttendanceForm::query()->create(array_merge([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => false,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ahmad Fauzi',
            'nip' => '198001012020031001',
            'position' => 'Staff',
            'work_unit' => AttendanceWorkUnit::Kebidanan->value,
        ], $overrides);
    }

    private function createActivity(string $title = 'Rapat Koordinasi Kewadiran 1'): Activity
    {
        return Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => $title,
            'location' => 'Ruang Rapat Utama',
        ]);
    }

    public function test_attendance_public_success_route_is_registered(): void
    {
        $this->assertTrue(Route::has('attendance.public.success'));
        $this->assertTrue(Route::has('attendance.public.show'));
        $this->assertTrue(Route::has('attendance.public.store'));
    }

    public function test_success_url_can_be_generated_with_public_token(): void
    {
        $form = $this->openForm($this->createActivity());

        $url = route('attendance.public.success', ['public_token' => $form->public_token]);

        $this->assertStringContainsString('/absensi/'.$form->public_token.'/berhasil', $url);
    }

    public function test_get_form_displays_input_fields(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Nama Lengkap', false)
            ->assertDontSee('name="nik"', false)
            ->assertSee('NIP', false)
            ->assertSee('Kirim Presensi', false);
    }

    public function test_valid_post_stores_response(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'name' => 'Ahmad Fauzi',
            'nip_normalized' => '198001012020031001',
            'nik' => null,
        ]);
    }

    public function test_valid_post_redirects_to_success_page_with_302(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload())
            ->assertStatus(302)
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));
    }

    public function test_success_page_does_not_display_form_fields(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertDontSee('Nama Lengkap', false)
            ->assertDontSee('signature_data', false)
            ->assertDontSee('Tanda Tangan', false);
    }

    public function test_success_page_does_not_display_submit_button(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertDontSee('Kirim Presensi', false)
            ->assertDontSee('type="submit"', false);
    }

    public function test_success_page_displays_activity_title_and_success_message(): void
    {
        $form = $this->openForm($this->createActivity('Rapat Koordinasi Kewadiran 1'));

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Rapat Koordinasi Kewadiran 1', false)
            ->assertSee('Absensi Berhasil Dikirim', false);
    }

    public function test_success_page_displays_submission_time(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Waktu Tercatat', false)
            ->assertSee('WIB', false);
    }

    public function test_refreshing_success_page_does_not_create_new_response(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))->assertOk();
        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))->assertOk();

        $this->assertSame(1, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
    }

    public function test_browser_back_after_success_does_not_create_duplicate(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload())
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))->assertOk();
        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))->assertOk();

        $this->assertSame(1, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
    }

    public function test_duplicate_submission_shows_friendly_message(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload())
            ->assertRedirect(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertSessionHas('attendance_duplicate', true);

        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Anda sudah mengisi daftar hadir untuk kegiatan ini.', false)
            ->assertDontSee('Kirim Presensi', false);

        $this->assertSame(1, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
    }

    public function test_direct_success_access_without_session_shows_neutral_page(): void
    {
        $form = $this->openForm($this->createActivity());

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Hidden Participant',
            'nip_nik' => '9999999999999999',
            'nip_nik_normalized' => '9999999999999999',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Tidak ada data pengiriman terbaru yang dapat ditampilkan.', false)
            ->assertDontSee('Hidden Participant', false)
            ->assertDontSee('9999999999999999', false);
    }

    public function test_success_page_masks_nip(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload([
            'nip' => '198001012020031001',
        ]));

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('**************1001', false)
            ->assertDontSee('198001012020031001', false)
            ->assertDontSee('>NIK</', false);
    }

    public function test_success_page_shows_reference_code_not_database_id(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $response = AttendanceResponse::query()->firstOrFail();

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Kode referensi:', false)
            ->assertDontSee('ID: '.$response->id, false);
    }

    public function test_invalid_public_token_on_success_returns_404(): void
    {
        $this->get('/absensi/invalid-token-that-does-not-exist/berhasil')->assertNotFound();
    }

    public function test_success_route_is_not_captured_by_legacy_slug_route(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->get('/absensi/'.$form->public_token.'/berhasil')
            ->assertOk()
            ->assertDontSee('Halaman tidak ditemukan', false);
    }
}
