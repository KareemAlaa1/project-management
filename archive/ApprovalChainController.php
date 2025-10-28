<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalChain;
use App\Models\Project;
use App\Policies\ApprovalChainPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApprovalChainController extends Controller
{
    
    public function create(Request $request, Project $project): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'users' => 'required|array|min:1',
            'users.*' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check permission - only project owner or administrator can create approval chains
        $isAuthorized = $project->owner_id === auth()->id()
            || $project->users()->where('user_id', auth()->id())
                ->where('role', config('system.projects.affectations.roles.can_manage'))
                ->exists();
        
        if (!$isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create approval chains for this project'
            ], 403);
        }

        // Validate unique users
        $userIds = $request->input('users');
        if (count($userIds) !== count(array_unique($userIds))) {
            return response()->json([
                'success' => false,
                'message' => 'Users in the approval chain must be unique'
            ], 422);
        }

        // Deactivate any existing approval chains
        $project->approvalChains()->update(['is_active' => false]);

        // Create approval chain
        $chain = ApprovalChain::create([
            'project_id' => $project->id,
            'created_by' => auth()->id(),
            'is_active' => true
        ]);

        // Add users to the chain
        foreach ($userIds as $sequence => $userId) {
            $chain->chainUsers()->create([
                'user_id' => $userId,
                'sequence' => $sequence + 1,
                'is_current' => $sequence === 0 // First user is current approver
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Approval chain created successfully',
            'data' => $chain->load('chainUsers.user')
        ], 201);
    }

    
    public function approve(Request $request, ApprovalChain $approvalChain): JsonResponse
    {
        // Check permission
        if (!auth()->user()->can('approve', $approvalChain)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to approve this chain'
            ], 403);
        }

        // Perform approval and forward
        $approvalChain->approveAndForward();

        return response()->json([
            'success' => true,
            'message' => 'Project approved and forwarded to next approver',
            'data' => $approvalChain->fresh(['chainUsers.user'])
        ]);
    }

    
    public function index(Project $project): JsonResponse
    {
        $chain = $project->approvalChains()
            ->where('is_active', true)
            ->with(['chainUsers.user', 'creator'])
            ->first();

        if (!$chain) {
            return response()->json([
                'success' => false,
                'message' => 'No active approval chain found for this project'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $chain
        ]);
    }
}
