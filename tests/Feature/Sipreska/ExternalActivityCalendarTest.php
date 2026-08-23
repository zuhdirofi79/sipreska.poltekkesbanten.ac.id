<?php

namespace Tests\Feature\Sipreska;

use App\Enums\ActivityStatus;
use App\Enums\ExternalActivityStatus;
use App\Models\Activity;
use App\Models\ExternalActivity;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class ExternalActivityCalendarTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    public function test_external_multi_date_events_appear_purple_on_each_date(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = ExternalActivity::factory()->create([
            'title' => 'Conference Kalender',
            'created_by' => $admin->id,
        ]);
        foreach (['2026-08-25', '2026-08-26', '2026-08-27'] as $date) {
            $activity->dates()->create(['event_date' => $date]);
        }

        $response = $this->actingAs($admin)->getJson('/kalender/events?start=2026-08-01&end=2026-08-31');
        $response->assertOk();

        $titles = collect($response->json())->where('title', 'Conference Kalender');
        $this->assertCount(3, $titles);
        $this->assertEqualsCanonicalizing(
            ['2026-08-25', '2026-08-26', '2026-08-27'],
            $titles->pluck('start')->all()
        );
        $titles->each(function (array $event): void {
            $this->assertSame(ExternalActivityStatus::CALENDAR_COLOR, $event['backgroundColor']);
            $this->assertSame('external', $event['extendedProps']['event_type']);
        });
    }

    public function test_internal_events_keep_status_colors_and_unit_isolation(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Internal Terdaftar',
            'status' => ActivityStatus::Terdaftar,
            'activity_date' => '2026-08-20',
        ]);
        Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Internal Selesai',
            'status' => ActivityStatus::Selesai,
            'activity_date' => '2026-08-21',
        ]);
        Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-keperawatan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Internal Unit Lain',
            'status' => ActivityStatus::Terdaftar,
            'activity_date' => '2026-08-22',
        ]);

        $response = $this->actingAs($admin)->getJson('/kalender/events?start=2026-08-01&end=2026-08-31');
        $response->assertOk()
            ->assertJsonFragment(['title' => 'Internal Terdaftar', 'backgroundColor' => ActivityStatus::Terdaftar->color()])
            ->assertJsonFragment(['title' => 'Internal Selesai', 'backgroundColor' => ActivityStatus::Selesai->color()])
            ->assertJsonMissing(['title' => 'Internal Unit Lain']);
    }

    public function test_calendar_page_keeps_status_legend_and_adds_source_legend(): void
    {
        $html = $this->actingAs($this->superadmin())->get('/kalender')->assertOk()->getContent();

        $this->assertStringContainsString('Terdaftar', $html);
        $this->assertStringContainsString('Selesai', $html);
        $this->assertStringContainsString('Batal', $html);
        $this->assertStringContainsString('Agenda Internal', $html);
        $this->assertStringContainsString('Agenda Eksternal', $html);
        $this->assertStringContainsString(ExternalActivityStatus::CALENDAR_COLOR, $html);
        $this->assertStringContainsString(ActivityStatus::Terdaftar->color(), $html);
        $this->assertStringContainsString(ActivityStatus::Selesai->color(), $html);
        $this->assertStringContainsString(ActivityStatus::Batal->color(), $html);
    }

    public function test_admin_unit_can_see_institutional_external_events(): void
    {
        $activity = ExternalActivity::factory()->create([
            'title' => 'Conference Institusional',
        ]);
        $activity->dates()->create(['event_date' => '2026-08-25']);

        $this->actingAs($this->adminUnit('d3-keperawatan'))
            ->getJson('/kalender/events?start=2026-08-01&end=2026-08-31')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Conference Institusional']);
    }
}
