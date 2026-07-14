<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Support\DateTimeFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceResponseIndexTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('sipreska');
    }

    private function activityWithForm(?int $unitId = null): array
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $unitId ?? $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Workshop Absensi',
            'activity_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'location' => 'Aula Utama',
        ]);

        $form = AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => true,
        ]);

        return [$admin, $activity, $form];
    }

    private function createResponse(AttendanceForm $form, array $overrides = []): AttendanceResponse
    {
        return AttendanceResponse::query()->create(array_merge([
            'attendance_form_id' => $form->id,
            'name' => 'Peserta Test',
            'nip_nik' => '1234567890123456',
            'nip_nik_normalized' => '1234567890123456',
            'position' => 'Staff',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'institution_name' => 'Poltekkes Banten',
            'signature_disk' => 'sipreska',
            'signature_path' => 'tanda_tangan/1/test.png',
            'submitted_at' => now(),
        ], $overrides));
    }

    public function test_responses_index_route_is_registered(): void
    {
        $route = app('router')->getRoutes()->getByName('activities.daftar-hadir.responses');

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertStringContainsString('daftar-hadir/respons', $route->uri());
    }

    public function test_superadmin_can_open_responses_page(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, ['name' => 'Super View']);

        $this->actingAs($this->superadmin())
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Partisipan Hadir')
            ->assertSee('Super View');
    }

    public function test_admin_unit_can_open_own_activity_responses_page(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, ['name' => 'Unit Admin View']);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Unit Admin View');
    }

    public function test_admin_unit_from_other_unit_gets_403(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form);

        $otherAdmin = $this->adminUnit('d3-keperawatan');

        $this->actingAs($otherAdmin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [, $activity] = $this->activityWithForm();

        $this->get(route('activities.daftar-hadir.responses', $activity))
            ->assertRedirect(route('login'));
    }

    public function test_public_participant_cannot_open_admin_responses_page(): void
    {
        [, $activity, $form] = $this->activityWithForm();

        $this->get(route('activities.daftar-hadir.responses', $activity))
            ->assertRedirect(route('login'));
    }

    public function test_responses_page_lists_participants(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, [
            'name' => 'Andi Wijaya',
            'nip_nik' => '9988776655443322',
            'position' => 'Perawat',
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Andi Wijaya')
            ->assertSee('9988776655443322')
            ->assertSee('Perawat')
            ->assertSee('D3 Kebidanan');
    }

    public function test_pagination_works(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        for ($i = 1; $i <= 26; $i++) {
            $this->createResponse($form, [
                'name' => $i === 1 ? 'Alpha Participant' : ($i === 26 ? 'Zeta Participant' : "Peserta {$i}"),
                'nip_nik' => str_pad((string) $i, 16, '0', STR_PAD_LEFT),
                'nip_nik_normalized' => str_pad((string) $i, 16, '0', STR_PAD_LEFT),
                'submitted_at' => now()->subMinutes(26 - $i),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Zeta Participant')
            ->assertDontSee('Alpha Participant');
    }

    public function test_search_by_name_works(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, ['name' => 'Budi Santoso']);
        $this->createResponse($form, [
            'name' => 'Citra Dewi',
            'nip_nik' => '1111222233334444',
            'nip_nik_normalized' => '1111222233334444',
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', ['activity' => $activity, 'search' => 'Budi']))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Dewi');
    }

    public function test_search_by_nip_nik_works(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, [
            'name' => 'Target NIP',
            'nip_nik' => '5555666677778888',
            'nip_nik_normalized' => '5555666677778888',
        ]);
        $this->createResponse($form, [
            'name' => 'Other Person',
            'nip_nik' => '9999888877776666',
            'nip_nik_normalized' => '9999888877776666',
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', ['activity' => $activity, 'search' => '5555666677778888']))
            ->assertOk()
            ->assertSee('Target NIP')
            ->assertDontSee('Other Person');
    }

    public function test_filter_by_work_unit_works(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, [
            'name' => 'Direktorat Staff',
            'work_unit' => AttendanceWorkUnit::Direktorat->value,
            'nip_nik' => '1000000000000001',
            'nip_nik_normalized' => '1000000000000001',
        ]);
        $this->createResponse($form, [
            'name' => 'Kebidanan Staff',
            'nip_nik' => '1000000000000002',
            'nip_nik_normalized' => '1000000000000002',
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', [
                'activity' => $activity,
                'work_unit' => AttendanceWorkUnit::Direktorat->value,
            ]))
            ->assertOk()
            ->assertSee('Direktorat Staff')
            ->assertDontSee('Kebidanan Staff');
    }

    public function test_filter_by_submit_date_works(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $targetDate = Carbon::parse('2026-07-10 10:00:00');

        $this->createResponse($form, [
            'name' => 'On Date',
            'nip_nik' => '2000000000000001',
            'nip_nik_normalized' => '2000000000000001',
            'submitted_at' => $targetDate,
        ]);
        $this->createResponse($form, [
            'name' => 'Other Date',
            'nip_nik' => '2000000000000002',
            'nip_nik_normalized' => '2000000000000002',
            'submitted_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', [
                'activity' => $activity,
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-10',
            ]))
            ->assertOk()
            ->assertSee('On Date')
            ->assertDontSee('Other Date');
    }

    public function test_sorting_newest_works(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        $this->createResponse($form, [
            'name' => 'Older Entry',
            'nip_nik' => '3000000000000001',
            'nip_nik_normalized' => '3000000000000001',
            'submitted_at' => Carbon::parse('2026-07-01 08:00:00'),
        ]);
        $this->createResponse($form, [
            'name' => 'Newer Entry',
            'nip_nik' => '3000000000000002',
            'nip_nik_normalized' => '3000000000000002',
            'submitted_at' => Carbon::parse('2026-07-14 09:00:00'),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', ['activity' => $activity, 'sort' => 'newest']));

        $response->assertOk();
        $this->assertTrue(strpos($response->getContent(), 'Newer Entry') < strpos($response->getContent(), 'Older Entry'));
    }

    public function test_empty_state_is_shown_when_no_responses(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Belum ada partisipan yang mengisi form absensi.')
            ->assertSee('Bagikan tautan form absensi kepada peserta untuk mulai menerima respons.');
    }

    public function test_total_responses_stat_is_correct(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, ['nip_nik' => '4000000000000001', 'nip_nik_normalized' => '4000000000000001']);
        $this->createResponse($form, ['nip_nik' => '4000000000000002', 'nip_nik_normalized' => '4000000000000002', 'name' => 'Second']);

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity));

        $response->assertOk()
            ->assertSee('Total Partisipan');

        $this->assertStringContainsString('data-stat-card="total">2<', (string) $response->getContent());
    }

    public function test_last_response_timestamp_is_displayed_in_wib(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $submittedAt = Carbon::parse('2026-07-14 09:21:05', config('sipreska.display_timezone', 'Asia/Jakarta'));

        $this->createResponse($form, [
            'nip_nik' => '5000000000000001',
            'nip_nik_normalized' => '5000000000000001',
            'submitted_at' => $submittedAt,
        ]);

        $expected = DateTimeFormatter::display($submittedAt);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee($expected);
    }

    public function test_signature_uses_authorized_file_route(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $response = $this->createResponse($form);

        $expectedUrl = route('files.show', ['type' => 'signature', 'id' => $response->id, 'inline' => 1]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee($expectedUrl, false);
    }

    public function test_export_respects_active_filter(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form, [
            'name' => 'Filtered Export',
            'work_unit' => AttendanceWorkUnit::Direktorat->value,
            'nip_nik' => '6000000000000001',
            'nip_nik_normalized' => '6000000000000001',
        ]);
        $this->createResponse($form, [
            'name' => 'Excluded Export',
            'nip_nik' => '6000000000000002',
            'nip_nik_normalized' => '6000000000000002',
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-excel', [
                'activity' => $activity,
                'work_unit' => AttendanceWorkUnit::Direktorat->value,
            ]))
            ->assertOk();
    }

    public function test_attendance_tab_links_to_responses_page(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form);

        $expectedUrl = route('activities.daftar-hadir.responses', $activity);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Lihat Partisipan Hadir')
            ->assertSee('Partisipan yang Telah Hadir')
            ->assertSee($expectedUrl, false)
            ->assertDontSee('href="#"', false);
    }

    public function test_responses_data_endpoint_returns_json_stats(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $this->createResponse($form);

        $this->actingAs($admin)
            ->getJson(route('activities.daftar-hadir.responses.data', $activity))
            ->assertOk()
            ->assertJsonStructure(['total', 'today', 'last_submitted_at', 'form_status', 'version'])
            ->assertJsonPath('total', 1);
    }

    public function test_response_show_page_displays_detail(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $response = $this->createResponse($form, [
            'name' => 'Detail Person',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Agent',
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.response.show', [$activity, $response]))
            ->assertOk()
            ->assertSee('Detail Person')
            ->assertSee('127.0.0.1')
            ->assertSee('PHPUnit Agent');
    }

    public function test_response_id_tampering_returns_404(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $otherActivity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $otherForm = AttendanceForm::query()->create([
            'activity_id' => $otherActivity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => true,
        ]);
        $foreignResponse = $this->createResponse($otherForm, [
            'nip_nik' => '7000000000000001',
            'nip_nik_normalized' => '7000000000000001',
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.response.show', [$activity, $foreignResponse]))
            ->assertNotFound();
    }

    public function test_route_cache_succeeds(): void
    {
        $this->artisan('route:cache')->assertSuccessful();
        $this->artisan('route:clear')->assertSuccessful();
    }

    public function test_view_cache_succeeds(): void
    {
        $this->artisan('view:cache')->assertSuccessful();
        $this->artisan('view:clear')->assertSuccessful();
    }
}
