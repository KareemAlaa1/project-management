<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalChainUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_chain_id', 'user_id', 'sequence',
        'approved_at', 'approved_by', 'is_current'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'is_current' => 'boolean'
    ];

    public function approvalChain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class, 'approval_chain_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function isApproved(): bool
    {
        return !is_null($this->approved_at);
    }
}

