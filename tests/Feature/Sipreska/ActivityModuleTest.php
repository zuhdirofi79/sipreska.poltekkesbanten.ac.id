<?php

namespace Tests\Feature\Sipreska;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\Category;
use App\Services\ActivityCompletenessService;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class ActivityModuleTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    private function activityPayload(int $unitId): array
    {
        return [
            'title' => 'Workshop SIPRESKA',
            'category_id' => $this->defaultCategory()->id,
            'activity_date' => '2026-07-13',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'location' => 'Ruang Aula',
            'unit_id' => $unitId,
            'pic_name' => 'Dr. Budi',
            'pic_contact' => '08123456789',
            'description' => 'Deskripsi kegiatan',
        ];
    }

    public function test_superadmin_can_crud_activity(): void
    {
        $superadmin = $this->superadmin();
        $unit = $this->unitBySlug('d3-kebidanan');

        $this->actingAs($superadmin)
            ->post(route('activities.store'), $this->activityPayload($unit->id))
            ->assertRedirect();

        $activity = Activity::query()->first();
        $this->assertNotNull($activity);
        $this->assertSame(ActivityStatus::Terdaftar, $activity->status);

        $this->actingAs($superadmin)
            ->put(route('activities.update', $activity), array_merge($this->activityPayload($unit->id), [
                'status' => ActivityStatus::Selesai->value,
                'title' => 'Workshop SIPRESKA Updated',
            ]))
            ->assertRedirect(route('activities.edit', $activity));

        $activity->refresh();
        $this->assertSame('Workshop SIPRESKA Updated', $activity->title);

        $this->actingAs($superadmin)
            ->delete(route('activities.destroy', $activity))
            ->assertRedirect(route('activities.index'));

        $this->assertSoftDeleted($activity);
    }

    public function test_admin_unit_only_sees_own_unit_activities(): void
    {
        $kebidanan = $this->unitBySlug('d3-kebidanan');
        $keperawatan = $this->unitBySlug('d3-keperawatan');

        Activity::factory()->create([
            'unit_id' => $kebidanan->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Kegiatan Kebidanan',
        ]);
        Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Kegiatan Keperawatan',
        ]);

        $admin = $this->adminUnit('d3-kebidanan');

        $response = $this->actingAs($admin)->get('/kegiatan');
        $response->assertOk();
        $response->assertSee('Kegiatan Kebidanan');
        $response->assertDontSee('Kegiatan Keperawatan');
    }

    public function test_admin_unit_cannot_access_other_unit_activity(): void
    {
        $keperawatan = $this->unitBySlug('d3-keperawatan');
        $activity = Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $admin = $this->adminUnit('d3-kebidanan');

        $this->actingAs($admin)
            ->get(route('activities.show', $activity))
            ->assertForbidden();
    }

    public function test_admin_unit_cannot_tamper_unit_id_on_store(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $otherUnit = $this->unitBySlug('d3-keperawatan');

        $this->actingAs($admin)
            ->post(route('activities.store'), array_merge($this->activityPayload($otherUnit->id), [
                'unit_id' => $otherUnit->id,
            ]))
            ->assertRedirect();

        $activity = Activity::query()->latest('id')->first();
        $this->assertSame($admin->unit_id, $activity->unit_id);
    }

    public function test_activity_filters_and_pagination_work(): void
    {
        $superadmin = $this->superadmin();
        $unit = $this->unitBySlug('direktorat');
        $category = $this->defaultCategory();

        Activity::factory()->count(20)->create([
            'unit_id' => $unit->id,
            'category_id' => $category->id,
            'status' => ActivityStatus::Terdaftar,
            'title' => 'Rapat Rutin',
        ]);

        $response = $this->actingAs($superadmin)->get('/kegiatan?search=Rapat&status=terdaftar&category_id='.$category->id);
        $response->assertOk();
        $response->assertSee('Rapat Rutin');
    }

    public function test_apel_category_is_available_and_filterable(): void
    {
        $superadmin = $this->superadmin();
        $unit = $this->unitBySlug('direktorat');
        $apel = Category::query()->where('slug', 'apel')->firstOrFail();

        $this->actingAs($superadmin)
            ->get(route('activities.index'))
            ->assertOk()
            ->assertSee('Apel');

        $this->actingAs($superadmin)
            ->get(route('activities.create'))
            ->assertOk()
            ->assertSee('Apel');

        $this->actingAs($superadmin)
            ->post(route('activities.store'), array_merge($this->activityPayload($unit->id), [
                'title' => 'Apel Seluruh Pegawai',
                'category_id' => $apel->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'title' => 'Apel Seluruh Pegawai',
            'category_id' => $apel->id,
        ]);

        $this->actingAs($superadmin)
            ->get('/kegiatan?category_id='.$apel->id)
            ->assertOk()
            ->assertSee('Apel Seluruh Pegawai')
            ->assertSee('Apel');
    }

    public function test_end_time_validation(): void
    {
        $superadmin = $this->superadmin();
        $unit = $this->unitBySlug('direktorat');

        $this->actingAs($superadmin)
            ->post(route('activities.store'), array_merge($this->activityPayload($unit->id), [
                'start_time' => '10:00',
                'end_time' => '08:00',
            ]))
            ->assertSessionHasErrors('end_time');
    }

    public function test_document_completeness_calculation(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('direktorat')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $completeness = ActivityCompletenessService::make()->evaluate($activity);

        $this->assertSame(0, $completeness['completed']);
        $this->assertContains('Surat Undangan', $completeness['missing']);
    }
}
