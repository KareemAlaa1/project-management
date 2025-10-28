<?php

namespace Database\Factories;

use App\Models\ApprovalChain;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalChainFactory extends Factory
{
    protected $model = ApprovalChain::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'is_active'  => true,
        ];
    }

    /**
     * State for inactive approval chain
     */
    public function inactive(): self
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
