<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Rapat', 'slug' => 'rapat'],
            ['name' => 'Pelatihan', 'slug' => 'pelatihan'],
            ['name' => 'Webinar', 'slug' => 'webinar'],
            ['name' => 'Kunjungan', 'slug' => 'kunjungan'],
            ['name' => 'Lainnya', 'slug' => 'lainnya'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
