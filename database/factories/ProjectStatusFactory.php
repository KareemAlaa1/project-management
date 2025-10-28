<?php

namespace Database\Factories;

use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectStatusFactory extends Factory
{
    protected $model = ProjectStatus::class;

    public function definition(): array
    {
        return [
            'name'       => ucfirst($this->faker->word()),
            'color'      => $this->faker->safeColorName(),
            'is_default' => $this->faker->boolean(20), // 20% chance of being default
        ];
    }
}
