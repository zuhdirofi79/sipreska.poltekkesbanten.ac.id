<?php

namespace Tests\Feature\Sipreska;

use App\Enums\ExternalActivityStatus;
use App\Models\ExternalActivity;
use App\Models\ExternalAttendanceParticipant;
use App\Support\ExternalAttendancePublicRoutes;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class ExternalActivityModuleTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('sipreska');
        app()->setLocale('id');
    }

    private const SAMPLE_OFFLINE_ADDRESS = 'Gedung Graha Bina Husada, Kampus Tangerang, Jalan Dr. Sitanala, Kelurahan Karangsari, Kecamatan Neglasari, Kota Tangerang, Banten';

    private const SAMPLE_ONLINE_URL = 'https://meet.example.com/j/93441888855?pwd=sampleQueryParam';

    /** @param list<string> $dates */
    private function payload(array $dates = ['2026-08-25'], array $overrides = []): array
    {
        return array_merge([
            'title' => '2nd Banten International Conference 2026 Participant Attendance List',
            'status' => ExternalActivityStatus::Terdaftar->value,
            'is_open' => '1',
            'dates' => $dates,
        ], $overrides);
    }

    public function test_superadmin_and_admin_unit_can_open_index(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('external-activities.index'))
            ->assertOk()
            ->assertSee('Agenda Kegiatan Eksternal', false);

        $this->actingAs($this->adminUnit('d3-kebidanan'))
            ->get(route('external-activities.index'))
            ->assertOk();
    }

    public function test_sidebar_adds_external_menu_without_removing_existing_menus(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/dashboard');

        $response->assertOk();
        $response->assertSee(route('activities.index'), false);
        $response->assertSee(route('calendar.index'), false);
        $response->assertSee(route('external-activities.index'), false);
        $response->assertSee('Agenda Kegiatan', false);
        $response->assertSee('Agenda Kegiatan Eksternal', false);
        $response->assertSee('Kalender Kegiatan', false);
        $response->assertSee('Dashboard - Statistik', false);
    }

    public function test_can_create_event_with_one_two_and_three_dates(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        foreach ([
            ['2026-08-25'],
            ['2026-08-25', '2026-08-26'],
            ['2026-08-25', '2026-08-26', '2026-08-27'],
        ] as $dates) {
            $this->actingAs($admin)
                ->post(route('external-activities.store'), $this->payload($dates, [
                    'title' => 'Conference '.implode('-', $dates),
                ]))
                ->assertRedirect();
        }

        $this->assertSame(3, ExternalActivity::query()->count());
        $this->assertSame(6, ExternalActivity::query()->withCount('dates')->get()->sum('dates_count'));
    }

    public function test_create_rejects_empty_dates(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('external-activities.store'), $this->payload([]))
            ->assertSessionHasErrors('dates');
    }

    public function test_admin_can_edit_event_and_view_detail(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post(route('external-activities.store'), $this->payload(['2026-08-25', '2026-08-26']))
            ->assertRedirect();

        $activity = ExternalActivity::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('external-activities.show', $activity))
            ->assertOk()
            ->assertSee($activity->title, false)
            ->assertSee('25 Agustus 2026', false)
            ->assertSee('26 Agustus 2026', false)
            ->assertSee(ExternalAttendancePublicRoutes::showUrlForActivity($activity), false);

        $this->actingAs($admin)
            ->put(route('external-activities.update', $activity), $this->payload(
                ['2026-08-25', '2026-08-26', '2026-08-27'],
                ['title' => 'Conference Updated', 'status' => ExternalActivityStatus::Selesai->value]
            ))
            ->assertRedirect(route('external-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('Conference Updated', $activity->title);
        $this->assertSame(ExternalActivityStatus::Selesai, $activity->status);
        $this->assertSame(['2026-08-25', '2026-08-26', '2026-08-27'], $activity->allowedDateStrings());
    }

    public function test_cannot_remove_date_that_has_attendance(): void
    {
        $admin = $this->superadmin();
        $activity = ExternalActivity::factory()->create(['created_by' => $admin->id]);
        $kept = $activity->dates()->create(['event_date' => '2026-08-25']);
        $activity->dates()->create(['event_date' => '2026-08-26']);

        $participant = ExternalAttendanceParticipant::query()->create([
            'external_activity_id' => $activity->id,
            'full_name' => 'Ahmad',
            'institution' => 'Universitas X',
            'email' => 'ahmad@example.com',
            'email_normalized' => 'ahmad@example.com',
            'signature_disk' => 'sipreska',
            'signature_path' => 'docs/tanda_tangan_eksternal/1/sig.png',
            'submitted_at' => now(),
        ]);
        $participant->attendanceDates()->attach($kept->id);

        $this->actingAs($admin)
            ->put(route('external-activities.update', $activity), $this->payload(['2026-08-26']))
            ->assertSessionHasErrors('dates');

        $this->assertTrue($activity->dates()->whereDate('event_date', '2026-08-25')->exists());
    }

    public function test_admin_can_view_attendance_and_export(): void
    {
        $admin = $this->superadmin();
        $activity = ExternalActivity::factory()->create([
            'title' => 'Conference Export',
            'created_by' => $admin->id,
        ]);
        $date = $activity->dates()->create(['event_date' => '2026-08-25']);

        $participant = ExternalAttendanceParticipant::query()->create([
            'external_activity_id' => $activity->id,
            'full_name' => 'Ahmad',
            'institution' => 'Universitas X',
            'email' => 'ahmad@example.com',
            'email_normalized' => 'ahmad@example.com',
            'signature_disk' => 'sipreska',
            'signature_path' => 'docs/tanda_tangan_eksternal/'.$activity->id.'/sig.png',
            'submitted_at' => now(),
        ]);
        $participant->attendanceDates()->attach($date->id);

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.index', $activity))
            ->assertOk()
            ->assertSee('Ahmad', false)
            ->assertSee('Universitas X', false)
            ->assertSee('ahmad@example.com', false)
            ->assertSee(\Illuminate\Support\Carbon::parse('2026-08-25')->locale('id')->translatedFormat('d M Y'), false);

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.export-excel', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.export-pdf', $activity))
            ->assertOk();
    }

    public function test_guest_cannot_open_admin_pages(): void
    {
        $this->get(route('external-activities.index'))->assertRedirect(route('login'));
    }

    public function test_create_without_location_succeeds(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('external-activities.store'), $this->payload())
            ->assertRedirect();

        $activity = ExternalActivity::query()->firstOrFail();
        $this->assertNull($activity->location_address);
        $this->assertNull($activity->online_url);
        $this->assertFalse($activity->hasOfflineLocation());
        $this->assertFalse($activity->hasOnlineUrl());
    }

    public function test_create_with_offline_location_only(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'location_address' => self::SAMPLE_OFFLINE_ADDRESS,
            ]));

        $response->assertRedirect()->assertSessionHasNoErrors();

        $activity = ExternalActivity::query()->firstOrFail();
        $this->assertSame(self::SAMPLE_OFFLINE_ADDRESS, $activity->location_address);
        $this->assertNull($activity->online_url);
        $this->assertTrue($activity->hasOfflineLocation());
        $this->assertFalse($activity->hasOnlineUrl());
    }

    public function test_create_with_online_url_only_preserves_query_parameters(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'online_url' => self::SAMPLE_ONLINE_URL,
            ]))
            ->assertRedirect();

        $activity = ExternalActivity::query()->firstOrFail();
        $this->assertNull($activity->location_address);
        $this->assertSame(self::SAMPLE_ONLINE_URL, $activity->online_url);
        $this->assertFalse($activity->hasOfflineLocation());
        $this->assertTrue($activity->hasOnlineUrl());
    }

    public function test_create_with_hybrid_location(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'location_address' => self::SAMPLE_OFFLINE_ADDRESS,
                'online_url' => self::SAMPLE_ONLINE_URL,
            ]))
            ->assertRedirect();

        $activity = ExternalActivity::query()->firstOrFail();
        $this->assertSame(self::SAMPLE_OFFLINE_ADDRESS, $activity->location_address);
        $this->assertSame(self::SAMPLE_ONLINE_URL, $activity->online_url);
        $this->assertTrue($activity->hasOfflineLocation());
        $this->assertTrue($activity->hasOnlineUrl());
    }

    public function test_create_rejects_invalid_online_url(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'online_url' => 'bukan-url',
            ]))
            ->assertSessionHasErrors('online_url');

        $this->assertSame(0, ExternalActivity::query()->count());
    }

    public function test_whitespace_only_location_fields_are_stored_as_null(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'location_address' => '   ',
                'online_url' => '   ',
            ]))
            ->assertRedirect();

        $activity = ExternalActivity::query()->firstOrFail();
        $this->assertNull($activity->location_address);
        $this->assertNull($activity->online_url);
    }

    public function test_edit_updates_location_fields(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'location_address' => self::SAMPLE_OFFLINE_ADDRESS,
                'online_url' => self::SAMPLE_ONLINE_URL,
            ]))
            ->assertRedirect();

        $activity = ExternalActivity::query()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('external-activities.update', $activity), $this->payload(overrides: [
                'location_address' => 'Ruang Aula Poltekkes',
                'online_url' => 'https://teams.microsoft.com/l/meetup-join/example',
            ]))
            ->assertRedirect(route('external-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('Ruang Aula Poltekkes', $activity->location_address);
        $this->assertSame('https://teams.microsoft.com/l/meetup-join/example', $activity->online_url);
    }

    public function test_validation_error_preserves_location_old_input(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->from(route('external-activities.create'))
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'location_address' => self::SAMPLE_OFFLINE_ADDRESS,
                'online_url' => 'bukan-url',
            ]));

        $response
            ->assertRedirect(route('external-activities.create'))
            ->assertSessionHasErrors('online_url');

        $this->assertSame(self::SAMPLE_OFFLINE_ADDRESS, session()->getOldInput('location_address'));
        $this->assertSame('bukan-url', session()->getOldInput('online_url'));
    }

    public function test_show_displays_location_when_present_and_hides_when_empty(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post(route('external-activities.store'), $this->payload(overrides: [
                'location_address' => self::SAMPLE_OFFLINE_ADDRESS,
                'online_url' => self::SAMPLE_ONLINE_URL,
            ]))
            ->assertRedirect();

        $activity = ExternalActivity::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('external-activities.show', $activity))
            ->assertOk()
            ->assertSee('Lokasi Offline', false)
            ->assertSee(self::SAMPLE_OFFLINE_ADDRESS, false)
            ->assertSee('Kegiatan Online', false)
            ->assertSee('Buka Link Meeting', false);

        $emptyLocation = ExternalActivity::factory()->create([
            'created_by' => $admin->id,
            'location_address' => null,
            'online_url' => null,
        ]);
        $emptyLocation->dates()->create(['event_date' => '2026-08-25']);

        $this->actingAs($admin)
            ->get(route('external-activities.show', $emptyLocation))
            ->assertOk()
            ->assertDontSee('Lokasi Offline', false)
            ->assertDontSee('Kegiatan Online', false);
    }

    public function test_export_includes_location_columns(): void
    {
        $admin = $this->superadmin();
        $activity = ExternalActivity::factory()->create([
            'title' => 'Conference With Location',
            'created_by' => $admin->id,
            'location_address' => self::SAMPLE_OFFLINE_ADDRESS,
            'online_url' => self::SAMPLE_ONLINE_URL,
        ]);
        $activity->dates()->create(['event_date' => '2026-08-25']);

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.export-excel', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.export-pdf', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
