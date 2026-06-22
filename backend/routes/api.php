<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('api')->group(function () {

    Route::prefix('dealer-levels')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\DealerLevelController::class, 'index']);
        Route::get('/all', [\App\Http\Controllers\Api\DealerLevelController::class, 'all']);
        Route::get('/stats', [\App\Http\Controllers\Api\DealerLevelController::class, 'stats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\DealerLevelController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\DealerLevelController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\DealerLevelController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\DealerLevelController::class, 'destroy']);
        Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\DealerLevelController::class, 'toggleActive']);
    });

    Route::prefix('invite-codes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\InviteCodeController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\InviteCodeController::class, 'stats']);
        Route::get('/check', [\App\Http\Controllers\Api\InviteCodeController::class, 'check']);
        Route::get('/code/{code}', [\App\Http\Controllers\Api\InviteCodeController::class, 'findByCode']);
        Route::get('/{id}', [\App\Http\Controllers\Api\InviteCodeController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\InviteCodeController::class, 'store']);
        Route::post('/batch', [\App\Http\Controllers\Api\InviteCodeController::class, 'batchCreate']);
        Route::put('/{id}', [\App\Http\Controllers\Api\InviteCodeController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\InviteCodeController::class, 'destroy']);
        Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\InviteCodeController::class, 'toggleStatus']);
    });

    Route::prefix('invite-chains')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\InviteChainController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\InviteChainController::class, 'show']);
        Route::get('/user/{userId}/stats', [\App\Http\Controllers\Api\InviteChainController::class, 'getInviteStats']);
        Route::get('/user/{userId}/lineage', [\App\Http\Controllers\Api\InviteChainController::class, 'getInviterLineage']);
        Route::get('/user/{userId}/tree', [\App\Http\Controllers\Api\InviteChainController::class, 'getInviteTree']);
        Route::post('/use-code', [\App\Http\Controllers\Api\InviteChainController::class, 'useInviteCode']);
        Route::post('/create', [\App\Http\Controllers\Api\InviteChainController::class, 'createDirectInvite']);
        Route::patch('/{id}/reward', [\App\Http\Controllers\Api\InviteChainController::class, 'markRewarded']);
        Route::patch('/batch/reward', [\App\Http\Controllers\Api\InviteChainController::class, 'batchMarkRewarded']);
        Route::patch('/{id}/confirm', [\App\Http\Controllers\Api\InviteChainController::class, 'confirm']);
        Route::patch('/{id}/cancel', [\App\Http\Controllers\Api\InviteChainController::class, 'cancel']);
        Route::patch('/batch/confirm', [\App\Http\Controllers\Api\InviteChainController::class, 'batchConfirm']);
        Route::patch('/batch/cancel', [\App\Http\Controllers\Api\InviteChainController::class, 'batchCancel']);
    });

    Route::prefix('upgrade-records')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'stats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'show']);
        Route::get('/user/{userId}/history', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'userHistory']);
        Route::post('/manual-upgrade', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'manualUpgrade']);
        Route::post('/check-auto-upgrade', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'checkAutoUpgrade']);
        Route::patch('/{id}/reward', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'markRewarded']);
        Route::patch('/batch/reward', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'batchMarkRewarded']);
        Route::patch('/reward-all-pending', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'markAllPendingRewarded']);
        Route::patch('/{id}/approve', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'approve']);
        Route::patch('/{id}/reject', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'reject']);
        Route::patch('/batch/approve', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'batchApprove']);
        Route::patch('/batch/reject', [\App\Http\Controllers\Api\UpgradeRecordController::class, 'batchReject']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\UserController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\UserController::class, 'stats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\UserController::class, 'show']);
        Route::get('/{id}/invitees', [\App\Http\Controllers\Api\UserController::class, 'getInvitees']);
        Route::post('/', [\App\Http\Controllers\Api\UserController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\UserController::class, 'update']);
        Route::patch('/{id}/add-achievement', [\App\Http\Controllers\Api\UserController::class, 'addAchievement']);
    });

});
