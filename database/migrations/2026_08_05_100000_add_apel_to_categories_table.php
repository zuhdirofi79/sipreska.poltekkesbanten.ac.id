<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Category::query()->updateOrCreate(
            ['slug' => 'apel'],
            [
                'name' => 'Apel',
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        Category::query()
            ->where('slug', 'apel')
            ->whereDoesntHave('activities')
            ->delete();
    }
};
