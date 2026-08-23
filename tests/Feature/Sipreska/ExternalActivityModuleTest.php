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
}
