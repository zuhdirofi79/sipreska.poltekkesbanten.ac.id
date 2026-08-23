<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendancePublicConfirmationTest extends TestCase
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

    private function createActivity(): Activity
    {
        return Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Rapat Konfirmasi Presensi',
            'location' => 'Ruang Rapat Utama',
        ]);
    }

    private function formHtml(AttendanceForm $form): string
    {
        return (string) $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->getContent();
    }

    public function test_public_form_uses_attendance_confirmation_alpine_component(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->assertStringContainsString('attendanceConfirmation', $this->formHtml($form));
        $this->assertStringContainsString('data-public-attendance-form', $this->formHtml($form));
    }

    public function test_kirim_presensi_button_is_type_button_not_direct_submit(): void
    {
        $form = $this->openForm($this->createActivity());
        $html = $this->formHtml($form);

        $this->assertStringContainsString('x-ref="submitTrigger"', $html);
        $this->assertStringContainsString('@click="requestOpenConfirm()"', $html);
        $this->assertStringContainsString('x-text="isSubmitting ? \'Menyimpan...\' : \'Kirim Presensi\'"', $html);

        preg_match('/<button[^>]*x-ref="submitTrigger"[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('type="button"', $matches[0]);
        $this->assertStringNotContainsString('type="submit"', $matches[0]);
    }

    public function test_form_has_hidden_native_submitter_for_confirmed_submit(): void
    {
        $form = $this->openForm($this->createActivity());
        $html = $this->formHtml($form);

        $this->assertStringContainsString('x-ref="hiddenSubmit"', $html);
        $this->assertStringContainsString('@submit="handleFormSubmit($event)"', $html);
        $this->assertStringContainsString('confirmSubmit()', $html);
        $this->assertStringContainsString('requestOpenConfirm()', $html);
    }

    public function test_confirmation_modal_displays_required_title_and_message(): void
    {
        $form = $this->openForm($this->createActivity());
        $html = $this->formHtml($form);

        $this->assertStringContainsString('Konfirmasi Simpan Presensi Kehadiran', $html);
        $this->assertStringContainsString('Apakah Anda yakin menyimpan Presensi Kehadiran Anda?', $html);
        $this->assertStringContainsString('Ya, Simpan', $html);
        $this->assertStringContainsString('Tidak, Kembali', $html);
        $this->assertStringContainsString('data-attendance-confirm-modal', $html);
    }

    public function test_confirmation_modal_has_accessibility_attributes(): void
    {
        $form = $this->openForm($this->createActivity());
        $html = $this->formHtml($form);

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="attendance-confirm-title"', $html);
        $this->assertStringContainsString('aria-describedby="attendance-confirm-message"', $html);
        $this->assertStringContainsString('handleEscape()', $html);
        $this->assertStringContainsString('closeModal()', $html);
    }

    public function test_confirmation_modal_uses_is_submitting_guard(): void
    {
        $form = $this->openForm($this->createActivity());
        $html = $this->formHtml($form);

        $this->assertStringContainsString(':disabled="isSubmitting"', $html);
        $this->assertStringContainsString('!isSubmitting && closeModal()', $html);
        $this->assertStringContainsString('x-text="isSubmitting ? \'Menyimpan...\' : \'Ya, Simpan\'"', $html);
    }

    public function test_signature_required_form_keeps_signature_pad_without_browser_alert(): void
    {
        $form = $this->openForm($this->createActivity(), ['signature_required' => true]);
        $html = $this->formHtml($form);

        $this->assertStringContainsString('x-ref="canvas"', $html);
        $this->assertStringContainsString('signatureError', $html);
        $this->assertStringContainsString('requestOpenConfirm()', $html);
        $this->assertStringNotContainsString('alert(', $html);
    }

    public function test_valid_post_still_stores_response_after_confirmation_wiring(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload())
            ->assertStatus(302)
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'name' => 'Ahmad Fauzi',
        ]);
    }

    public function test_valid_post_still_creates_only_one_response(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->assertSame(1, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
    }

    public function test_success_page_still_appears_after_submit(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload());

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Absensi Berhasil Dikirim', false);
    }

    public function test_closed_form_does_not_render_confirmation_modal(): void
    {
        $form = $this->openForm($this->createActivity(), ['is_open' => false]);

        $html = $this->formHtml($form);

        $this->assertStringNotContainsString('data-attendance-confirm-modal', $html);
        $this->assertStringNotContainsString('Kirim Presensi', $html);
    }

    public function test_public_form_view_can_be_cached(): void
    {
        $this->artisan('view:cache')->assertSuccessful();
    }
}
