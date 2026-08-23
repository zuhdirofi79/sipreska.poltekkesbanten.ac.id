<?php

namespace Tests\Feature\Sipreska;

use App\Models\ExternalActivity;
use App\Models\ExternalAttendanceParticipant;
use App\Support\ExternalAttendancePublicRoutes;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class ExternalAttendancePublicTest extends TestCase
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
    private function createEvent(array $dates = ['2026-08-25', '2026-08-26', '2026-08-27'], array $overrides = []): ExternalActivity
    {
        $activity = ExternalActivity::factory()->create(array_merge([
            'title' => '2nd Banten International Conference 2026 Participant Attendance List',
            'is_open' => true,
        ], $overrides));

        foreach ($dates as $date) {
            $activity->dates()->create(['event_date' => $date]);
        }

        return $activity->fresh(['dates']) ?? $activity;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Ahmad',
            'institution' => 'Universitas X',
            'email' => 'ahmad@example.com',
            'attendance_dates' => ['2026-08-25', '2026-08-26'],
            'signature_data' => $this->minimalPngBase64(),
        ], $overrides);
    }

    public function test_public_short_link_opens_without_login(): void
    {
        $activity = $this->createEvent();
        $url = ExternalAttendancePublicRoutes::showUrlForActivity($activity);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/ae/', $url);
        $this->assertStringNotContainsString('/a/', $url);

        $this->get($url)
            ->assertOk()
            ->assertSee($activity->title, false)
            ->assertSee('Full Name', false)
            ->assertSee('Institution', false)
            ->assertSee('Email Address', false)
            ->assertSee('25 Agustus 2026', false)
            ->assertSee('26 Agustus 2026', false)
            ->assertSee('27 Agustus 2026', false)
            ->assertSee('attendance_dates[]', false)
            ->assertSee('Hapus tanda tangan', false)
            ->assertDontSee('type="radio"', false);
    }

    public function test_long_token_url_also_opens(): void
    {
        $activity = $this->createEvent();

        $this->get(route(ExternalAttendancePublicRoutes::SHOW, ['public_token' => $activity->public_token]))
            ->assertOk()
            ->assertSee($activity->title, false);
    }

    public function test_existing_short_attendance_prefix_is_not_used(): void
    {
        $activity = $this->createEvent();

        $this->get('/a/'.$activity->short_code)->assertNotFound();
    }

    public function test_required_fields_and_email_validation(): void
    {
        $activity = $this->createEvent();
        $url = ExternalAttendancePublicRoutes::storeUrlForActivity($activity);

        $this->post($url, [])
            ->assertSessionHasErrors(['full_name', 'institution', 'email', 'attendance_dates', 'signature_data']);

        $this->post($url, $this->payload(['email' => 'bukan-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_submit_with_multiple_dates_persists_relations(): void
    {
        $activity = $this->createEvent();
        $url = ExternalAttendancePublicRoutes::storeUrlForActivity($activity);

        $this->post($url, $this->payload())
            ->assertRedirect(route(ExternalAttendancePublicRoutes::SUCCESS, ['public_token' => $activity->public_token]));

        $participant = ExternalAttendanceParticipant::query()->first();
        $this->assertNotNull($participant);
        $this->assertSame('Ahmad', $participant->full_name);
        $this->assertSame('Universitas X', $participant->institution);
        $this->assertSame('ahmad@example.com', $participant->email_normalized);
        $this->assertSame(
            ['2026-08-25', '2026-08-26'],
            $participant->attendanceDates->map(fn ($date) => $date->event_date->format('Y-m-d'))->values()->all()
        );
        Storage::disk('sipreska')->assertExists($participant->signature_path);
    }

    public function test_single_date_submit_is_allowed(): void
    {
        $activity = $this->createEvent();

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->payload([
            'email' => 'budi@example.com',
            'full_name' => 'Budi',
            'attendance_dates' => ['2026-08-27'],
        ]))->assertRedirect();

        $this->assertSame(
            ['2026-08-27'],
            ExternalAttendanceParticipant::query()->where('email_normalized', 'budi@example.com')->first()?->attendanceDates
                ->map(fn ($date) => $date->event_date->format('Y-m-d'))
                ->all()
        );
    }

    public function test_rejects_date_not_belonging_to_event(): void
    {
        $activity = $this->createEvent();

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->payload([
            'attendance_dates' => ['2026-09-01'],
        ]))->assertSessionHasErrors('attendance_dates');

        $this->assertSame(0, ExternalAttendanceParticipant::query()->count());
    }

    public function test_duplicate_email_on_same_event_is_rejected(): void
    {
        $activity = $this->createEvent();
        $url = ExternalAttendancePublicRoutes::storeUrlForActivity($activity);

        $this->post($url, $this->payload())->assertRedirect();

        $this->post($url, $this->payload(['full_name' => 'Ahmad Kedua', 'attendance_dates' => ['2026-08-27']]))
            ->assertRedirect()
            ->assertSessionHas('external_attendance_duplicate', true);

        $this->assertSame(1, $activity->participants()->count());
    }

    public function test_same_email_is_allowed_on_different_events(): void
    {
        $eventA = $this->createEvent(['2026-08-25']);
        $eventB = $this->createEvent(['2026-09-01'], ['title' => 'Event B']);

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($eventA), $this->payload([
            'attendance_dates' => ['2026-08-25'],
        ]))->assertRedirect();

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($eventB), $this->payload([
            'attendance_dates' => ['2026-09-01'],
        ]))->assertRedirect();

        $this->assertSame(1, $eventA->participants()->count());
        $this->assertSame(1, $eventB->participants()->count());
    }

    public function test_closed_form_does_not_accept_submit(): void
    {
        $activity = $this->createEvent(['2026-08-25'], ['is_open' => false]);

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertSee('tidak menerima pengisian', false);

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->payload([
            'attendance_dates' => ['2026-08-25'],
        ]))->assertSessionHasErrors('form');
    }

    public function test_public_page_does_not_expose_other_participants(): void
    {
        $activity = $this->createEvent(['2026-08-25']);
        ExternalAttendanceParticipant::query()->create([
            'external_activity_id' => $activity->id,
            'full_name' => 'Rahasia',
            'institution' => 'Hidden',
            'email' => 'rahasia@example.com',
            'email_normalized' => 'rahasia@example.com',
            'signature_disk' => 'sipreska',
            'signature_path' => 'docs/tanda_tangan_eksternal/'.$activity->id.'/x.png',
            'submitted_at' => now(),
        ]);

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertDontSee('Rahasia', false)
            ->assertDontSee('rahasia@example.com', false);
    }
}
