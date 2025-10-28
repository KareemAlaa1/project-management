<?php

// use App\Http\Controllers\Api\ApprovalChainController;
// use App\Http\Controllers\Api\ProjectApprovalChainController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Approval Chain Routes
Route::middleware('auth:sanctum')->group(function () {
    // Create approval chain for a project
    Route::post('projects/{project}/approval-chains', [ApprovalChainController::class, 'create'])
        ->name('api.projects.approval-chains.create');
    
    // Get approval chain for a project
    Route::get('projects/{project}/approval-chains', [ProjectApprovalChainController::class, 'show'])
        ->name('api.projects.approval-chains.index');
    
    // Approve and forward
    Route::post('approval-chains/{approvalChain}/approve', [ApprovalChainController::class, 'approve'])
        ->name('api.approval-chains.approve');
    
    // Get approval chain details
    Route::get('approval-chains/{approvalChain}', [ApprovalChainController::class, 'index'])
        ->name('api.approval-chains.show');
});
