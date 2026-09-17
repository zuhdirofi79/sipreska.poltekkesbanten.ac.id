<?php

namespace Database\Factories;

use App\Enums\ExternalActivityStatus;
use App\Models\ExternalActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalActivity>
 */
class ExternalActivityFactory extends Factory
{
    protected $model = ExternalActivity::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'status' => ExternalActivityStatus::Terdaftar,
            'is_open' => true,
            'signature_required' => true,
            'use_custom_questions' => false,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['is_open' => false]);
    }
}
