<?php

namespace Tests\Feature\Sipreska;

use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Support\AttendancePublicRoutes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendancePublicRouteContractTest extends TestCase
{
    use SipreskaTestHelpers;

    private const TOKEN = 'route-contract-token-abc123';

    public function test_all_public_attendance_named_routes_are_registered(): void
    {
        foreach (AttendancePublicRoutes::ALL as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] should be registered.");
        }
    }

    public function test_public_attendance_urls_can_be_generated_without_exception(): void
    {
        $showUrl = route(AttendancePublicRoutes::SHOW, ['public_token' => self::TOKEN]);
        $storeUrl = route(AttendancePublicRoutes::STORE, ['public_token' => self::TOKEN]);
        $successUrl = route(AttendancePublicRoutes::SUCCESS, ['public_token' => self::TOKEN]);

        $this->assertStringContainsString('/absensi/'.self::TOKEN, $showUrl);
        $this->assertStringContainsString('/absensi/'.self::TOKEN, $storeUrl);
        $this->assertStringContainsString('/absensi/'.self::TOKEN.'/berhasil', $successUrl);
    }

    public function test_registered_routes_match_expected_methods_uris_and_controller(): void
    {
        $routes = app('router')->getRoutes();

        $show = $routes->getByName(AttendancePublicRoutes::SHOW);
        $store = $routes->getByName(AttendancePublicRoutes::STORE);
        $success = $routes->getByName(AttendancePublicRoutes::SUCCESS);

        $this->assertNotNull($show);
        $this->assertNotNull($store);
        $this->assertNotNull($success);

        $this->assertContains('GET', $show->methods());
        $this->assertSame('absensi/{public_token}', $show->uri());
        $this->assertStringContainsString('PublicAttendanceController', (string) $show->getAction('uses'));
        $this->assertStringContainsString('show', (string) $show->getAction('uses'));

        $this->assertContains('POST', $store->methods());
        $this->assertSame('absensi/{public_token}', $store->uri());
        $this->assertStringContainsString('PublicAttendanceController', (string) $store->getAction('uses'));
        $this->assertStringContainsString('store', (string) $store->getAction('uses'));
        $this->assertContains('throttle:10,1', $store->gatherMiddleware());

        $this->assertContains('GET', $success->methods());
        $this->assertSame('absensi/{public_token}/berhasil', $success->uri());
        $this->assertStringContainsString('PublicAttendanceController', (string) $success->getAction('uses'));
        $this->assertStringContainsString('success', (string) $success->getAction('uses'));
    }

    public function test_route_cache_preserves_public_attendance_routes(): void
    {
        Artisan::call('route:clear');

        $exitCode = Artisan::call('route:cache');

        $this->assertSame(0, $exitCode);

        foreach (AttendancePublicRoutes::ALL as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] should survive route:cache.");
        }

        Artisan::call('route:clear');
    }

    public function test_edit_attendance_tab_returns_200_when_public_routes_exist(): void
    {
        $this->seedSipreskaBasics();

        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => self::TOKEN,
            'is_open' => true,
            'signature_required' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee(route(AttendancePublicRoutes::SHOW, ['public_token' => self::TOKEN]), false);
    }
}
