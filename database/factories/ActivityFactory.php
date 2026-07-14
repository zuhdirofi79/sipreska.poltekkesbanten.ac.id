<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'activity_date' => fake()->dateTimeBetween('-3 months', '+3 months')->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '12:00',
            'location' => fake()->city(),
            'status' => ActivityStatus::Terdaftar,
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'pic_name' => fake()->name(),
            'pic_contact' => fake()->phoneNumber(),
            'description' => fake()->paragraph(),
            'objective' => fake()->sentence(),
            'target_participants' => fake()->numberBetween(20, 200).' peserta',
            'notes' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}
