<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class ApprovalChain extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'created_by', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function chainUsers(): HasMany
    {
        return $this->hasMany(ApprovalChainUser::class, 'approval_chain_id', 'id')->orderBy('sequence');
    }

    public function currentApprover(): ?ApprovalChainUser
    {
        return $this->chainUsers()->where('is_current', true)->first();
    }

    public function completedSteps(): HasMany
    {
        return $this->chainUsers()->whereNotNull('approved_at');
    }

    public function isCompleted(): bool
    {
        return $this->completedSteps()->count() === $this->chainUsers()->count();
    }

    public function getNextApprover(): ?ApprovalChainUser
    {
        $current = $this->currentApprover();
        if (!$current) {
            return $this->chainUsers()->first();
        }

        $nextSequence = $current->sequence + 1;
        return $this->chainUsers()->where('sequence', $nextSequence)->first();
    }

    public function approveAndForward(): void
    {
        $current = $this->currentApprover();
        
        if ($current && !$current->approved_at) {
            $nextApprover = $this->getNextApprover();
            // Mark current as approved
            $current->update([
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'is_current' => false
            ]);

            // Set next approver as current
            if ($nextApprover) {
                $nextApprover->update(['is_current' => true]);
            } else {
                // Chain completed - mark project as completed
                $completedStatus = ProjectStatus::where('name', 'Completed')->first();
                if ($completedStatus) {
                    $this->project->update(['status_id' => $completedStatus->id]);
                }
                $this->update(['is_active' => false]);
            }
        }
    }
}

