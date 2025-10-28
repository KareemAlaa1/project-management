<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectApprovalChainController extends Controller
{
    
    public function show(Project $project): JsonResponse
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
