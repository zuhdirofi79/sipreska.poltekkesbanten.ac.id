<?php

namespace Tests\Feature\Phase1A;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\InitialAdminSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_seeder_creates_five_units(): void
    {
        $this->seed(UnitSeeder::class);

        $this->assertDatabaseCount('units', 5);
        $this->assertDatabaseHas('units', ['slug' => 'd3-kebidanan']);
        $this->assertDatabaseHas('units', ['slug' => 'str-ners']);
    }

    public function test_unit_seeder_is_idempotent(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(UnitSeeder::class);

        $this->assertDatabaseCount('units', 5);
    }

    public function test_initial_admin_seeder_creates_expected_accounts(): void
    {
        putenv('SIPRESKA_INITIAL_ADMIN_PASSWORD=Test-Seed-Password-1');
        $_ENV['SIPRESKA_INITIAL_ADMIN_PASSWORD'] = 'Test-Seed-Password-1';

        $this->seed(UnitSeeder::class);
        $this->seed(InitialAdminSeeder::class);

        $superadmin = User::query()->where('email', 'qowiyrofi@poltekkesbanten.ac.id')->first();
        $this->assertNotNull($superadmin);
        $this->assertSame(UserRole::Superadmin, $superadmin->role);
        $this->assertNull($superadmin->unit_id);
        $this->assertTrue($superadmin->must_change_password);

        $kebidanan = User::query()->where('email', 'poltekkesadakpoltekkes@gmail.com')->first();
        $this->assertNotNull($kebidanan);
        $this->assertSame(
            Unit::query()->where('slug', 'd3-kebidanan')->value('id'),
            $kebidanan->unit_id
        );
    }

    public function test_initial_admin_seeder_does_not_reset_existing_password(): void
    {
        $this->seed(UnitSeeder::class);

        $existing = User::factory()->create([
            'email' => 'qowiyrofi@poltekkesbanten.ac.id',
            'password' => Hash::make('existing-password-xyz'),
            'role' => UserRole::Superadmin,
            'must_change_password' => false,
        ]);

        putenv('SIPRESKA_INITIAL_ADMIN_PASSWORD=New-Password-Should-Not-Apply');
        $_ENV['SIPRESKA_INITIAL_ADMIN_PASSWORD'] = 'New-Password-Should-Not-Apply';

        $this->seed(InitialAdminSeeder::class);

        $existing->refresh();
        $this->assertTrue(Hash::check('existing-password-xyz', $existing->password));
    }
}
