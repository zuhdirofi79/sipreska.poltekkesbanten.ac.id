<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env(config('sipreska.initial_admin_password_env'));

        if (! is_string($password) || $password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'SIPRESKA_INITIAL_ADMIN_PASSWORD must be set in production before seeding initial admin accounts.'
                );
            }

            $password = 'Sipreska-Dev-Only-2026!';
        }

        $accounts = [
            [
                'name' => 'Qowiy Muhammad Rofi Zuhdi',
                'email' => 'qowiyrofi@poltekkesbanten.ac.id',
                'role' => UserRole::Superadmin,
                'unit_slug' => null,
            ],
            [
                'name' => 'Admin D3 Kebidanan',
                'email' => 'poltekkesadakpoltekkes@gmail.com',
                'role' => UserRole::AdminUnit,
                'unit_slug' => 'd3-kebidanan',
            ],
            [
                'name' => 'Admin D3 Keperawatan',
                'email' => 'd3.keperawatan@poltekkesbanten.ac.id',
                'role' => UserRole::AdminUnit,
                'unit_slug' => 'd3-keperawatan',
            ],
            [
                'name' => 'Admin D3 TLM',
                'email' => 'adak.tlm@poltekkesbanten.ac.id',
                'role' => UserRole::AdminUnit,
                'unit_slug' => 'd3-tlm',
            ],
            [
                'name' => 'Admin STR Ners',
                'email' => 'strners@poltekkesbanten.ac.id',
                'role' => UserRole::AdminUnit,
                'unit_slug' => 'str-ners',
            ],
        ];

        DB::transaction(function () use ($accounts, $password): void {
            foreach ($accounts as $account) {
                $unitId = null;

                if ($account['unit_slug'] !== null) {
                    $unit = Unit::query()->where('slug', $account['unit_slug'])->first();

                    if ($unit === null) {
                        throw new RuntimeException(
                            "Unit with slug [{$account['unit_slug']}] not found. Run UnitSeeder first."
                        );
                    }

                    $unitId = $unit->id;
                }

                $email = strtolower($account['email']);
                $existing = User::query()->where('email', $email)->first();

                if ($existing !== null) {
                    $existing->fill([
                        'name' => $account['name'],
                        'role' => $account['role'],
                        'unit_id' => $account['role'] === UserRole::Superadmin ? null : $unitId,
                        'is_active' => true,
                    ]);
                    $existing->save();

                    continue;
                }

                User::query()->create([
                    'name' => $account['name'],
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => $account['role'],
                    'unit_id' => $account['role'] === UserRole::Superadmin ? null : $unitId,
                    'is_active' => true,
                    'must_change_password' => true,
                ]);
            }
        });
    }
}
