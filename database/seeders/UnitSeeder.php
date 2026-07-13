<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'name' => 'Direktorat',
                'short_name' => 'Direktorat',
                'slug' => 'direktorat',
            ],
            [
                'name' => 'D3 Keperawatan',
                'short_name' => 'D3 Keperawatan',
                'slug' => 'd3-keperawatan',
            ],
            [
                'name' => 'D3 Kebidanan',
                'short_name' => 'D3 Kebidanan',
                'slug' => 'd3-kebidanan',
            ],
            [
                'name' => 'D3 Teknologi Laboratorium Medis',
                'short_name' => 'D3 TLM',
                'slug' => 'd3-tlm',
            ],
            [
                'name' => 'Sarjana Terapan Keperawatan dan Pendidikan Profesi Ners',
                'short_name' => 'STR Ners',
                'slug' => 'str-ners',
            ],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['slug' => $unit['slug']],
                [
                    'name' => $unit['name'],
                    'short_name' => $unit['short_name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
