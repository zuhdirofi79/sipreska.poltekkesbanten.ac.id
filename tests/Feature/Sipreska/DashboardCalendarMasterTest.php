<?php

namespace Tests\Feature\Sipreska;

use App\Models\Activity;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\User;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class DashboardCalendarMasterTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    public function test_dashboard_shows_real_stats_for_admin_unit(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        Activity::factory()->count(3)->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-keperawatan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('Total Kegiatan');
    }

    public function test_superadmin_dashboard_includes_all_units(): void
    {
        $superadmin = $this->superadmin();

        Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Kegiatan Semua Unit',
        ]);

        $this->actingAs($superadmin)
            ->get('/dashboard?unit_id='.$this->unitBySlug('d3-kebidanan')->id)
            ->assertOk()
            ->assertSee('Kegiatan Semua Unit');
    }

    public function test_calendar_is_unit_isolated(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Kalender Kebidanan',
        ]);
        Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-keperawatan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Kalender Keperawatan',
        ]);

        $this->actingAs($admin)
            ->getJson('/kalender/events')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Kalender Kebidanan'])
            ->assertJsonMissing(['title' => 'Kalender Keperawatan']);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $superadmin = $this->superadmin();
        $category = Category::query()->where('slug', 'rapat')->firstOrFail();

        Activity::factory()->create([
            'unit_id' => $this->unitBySlug('direktorat')->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($superadmin)
            ->delete(route('master.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_unit_in_use_cannot_be_deleted(): void
    {
        $superadmin = $this->superadmin();
        $unit = $this->unitBySlug('d3-kebidanan');

        Activity::factory()->create([
            'unit_id' => $unit->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $this->actingAs($superadmin)
            ->delete(route('master.units.destroy', $unit))
            ->assertRedirect();

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_activity_log_does_not_store_secrets(): void
    {
        $superadmin = $this->superadmin();

        ActivityLog::query()->create([
            'user_id' => $superadmin->id,
            'action' => 'user_updated',
            'entity_type' => 'user',
            'entity_id' => $superadmin->id,
            'old_values' => json_encode(['password' => 'secret-hash']),
            'new_values' => json_encode(['token' => 'abc123']),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $response = $this->actingAs($superadmin)->get('/log-aktivitas');
        $response->assertOk();
        $response->assertDontSee('secret-hash');
        $response->assertDontSee('abc123');
    }
}
