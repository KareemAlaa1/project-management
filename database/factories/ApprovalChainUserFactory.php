<?php

namespace Database\Factories;

use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalChainUserFactory extends Factory
{
    protected $model = ApprovalChainUser::class;

    public function definition(): array
    {
        return [
            'approval_chain_id' => ApprovalChain::factory(),
            'user_id'           => User::factory(),
            'sequence'          => $this->faker->unique()->numberBetween(1, 5),
            'approved_at'       => null,
            'approved_by'       => null,
            'is_current'        => false,
        ];
    }

    /**
     * Mark this chain user as current approver.
     */
    public function current(): self
    {
        return $this->state([
            'is_current' => true,
        ]);
    }

    /**
     * Mark this chain user as approved.
     */
    public function approved(): self
    {
        return $this->state([
            'approved_at' => now(),
            'approved_by' => User::factory(),
            'is_current'  => false,
        ]);
    }
}
