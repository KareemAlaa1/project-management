<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\ProjectStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $approver1;
    protected User $approver2;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->approver1 = User::factory()->create();
        $this->approver2 = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);

        // Create a "Completed" status for final approval
        ProjectStatus::factory()->create(['name' => 'Completed']);
    }

    /** @test */
    public function owner_can_create_approval_chain()
    {
        $this->actingAs($this->owner);

        $chain = ApprovalChain::create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'is_active' => true,
        ]);

        $chain->chainUsers()->createMany([
            [
                'user_id' => $this->approver1->id,
                'sequence' => 1,
                'is_current' => true,
            ],
            [
                'user_id' => $this->approver2->id,
                'sequence' => 2,
                'is_current' => false,
            ],
        ]);

        $this->assertDatabaseHas('approval_chains', [
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('approval_chain_users', [
            'user_id' => $this->approver1->id,
            'sequence' => 1,
            'is_current' => true,
        ]);
    }

    /** @test */
    public function current_user_can_approve_and_forward_to_next_user()
    {
        $chain = ApprovalChain::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'is_active' => true,
        ]);

        $first = $chain->chainUsers()->create([
            'user_id' => $this->approver1->id,
            'sequence' => 1,
            'is_current' => true,
        ]);
        $second = $chain->chainUsers()->create([
            'user_id' => $this->approver2->id,
            'sequence' => 2,
            'is_current' => false,
        ]);

        $this->actingAs($this->approver1);
        $chain->approveAndForward();

        $this->assertDatabaseHas('approval_chain_users', [
            'id' => $first->id,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('approval_chain_users', [
            'id' => $second->id,
            'is_current' => true,
        ]);
    }

    /** @test */
    public function project_completes_when_last_user_approves()
    {
        $chain = ApprovalChain::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'is_active' => true,
        ]);

        $lastUser = $chain->chainUsers()->create([
            'user_id' => $this->approver1->id,
            'sequence' => 1,
            'is_current' => true,
        ]);

        $this->actingAs($this->approver1);
        $chain->approveAndForward();

        $this->assertDatabaseMissing('approval_chain_users', [
            'is_current' => true,
        ]);
        $this->assertFalse($chain->fresh()->is_active);

        $this->project->refresh();
        $completedStatus = ProjectStatus::where('name', 'Completed')->first();
        $this->assertEquals($completedStatus->id, $this->project->status_id);
    }

    /** @test */
    public function get_next_approver_returns_first_when_no_current()
    {
        $chain = ApprovalChain::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
        ]);

        $first = $chain->chainUsers()->create([
            'user_id' => $this->approver1->id,
            'sequence' => 1,
            'is_current' => false,
        ]);

        $this->assertEquals($first->id, $chain->getNextApprover()->id);
    }

    /** @test */
    public function is_completed_returns_true_only_when_all_approved()
    {
        $chain = ApprovalChain::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
        ]);

        $u1 = $chain->chainUsers()->create([
            'user_id' => $this->approver1->id,
            'sequence' => 1,
            'approved_at' => now(),
        ]);
        $u2 = $chain->chainUsers()->create([
            'user_id' => $this->approver2->id,
            'sequence' => 2,
            'approved_at' => now(),
        ]);

        $this->assertTrue($chain->isCompleted());

        // Now test with incomplete chain
        $chain2 = ApprovalChain::factory()->create(['project_id' => $this->project->id]);
        $chain2->chainUsers()->create([
            'user_id' => $this->approver1->id,
            'sequence' => 1,
            'approved_at' => null,
        ]);
        $this->assertFalse($chain2->isCompleted());
    }

    /** @test */
    public function approval_chain_relationships_work()
    {
        $chain = ApprovalChain::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
        ]);

        $userRecord = $chain->chainUsers()->create([
            'user_id' => $this->approver1->id,
            'sequence' => 1,
            'approved_at' => now(),
            'approved_by' => $this->approver2->id,
            'is_current' => false,
        ]);

        // ApprovalChain relationships
        $this->assertInstanceOf(Project::class, $chain->project);
        $this->assertInstanceOf(User::class, $chain->creator);
        $this->assertTrue($chain->completedSteps()->exists());

        // ApprovalChainUser relationships
        $this->assertInstanceOf(ApprovalChain::class, $userRecord->approvalChain);
        $this->assertInstanceOf(User::class, $userRecord->user);
        $this->assertInstanceOf(User::class, $userRecord->approver);
        $this->assertTrue($userRecord->isApproved());
    }
}
