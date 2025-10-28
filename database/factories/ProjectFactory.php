<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->sentence(3),
            'description'   => $this->faker->paragraph(),
            'status_id'     => ProjectStatus::factory(), // assumes you have this factory
            'owner_id'      => User::factory(),
            'ticket_prefix' => strtoupper($this->faker->lexify('PRJ??')),
            'status_type'   => 'draft', // adjust to your app logic
            'type'          => 'default', // adjust as needed
        ];
    }
}
