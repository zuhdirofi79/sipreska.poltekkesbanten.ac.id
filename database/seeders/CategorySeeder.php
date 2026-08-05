<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Apel', 'slug' => 'apel'],
            ['name' => 'Kunjungan', 'slug' => 'kunjungan'],
            ['name' => 'Lainnya', 'slug' => 'lainnya'],
            ['name' => 'Pelatihan', 'slug' => 'pelatihan'],
            ['name' => 'Rapat', 'slug' => 'rapat'],
            ['name' => 'Webinar', 'slug' => 'webinar'],
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
