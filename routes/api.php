<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\AdminOverviewController;
use App\Http\Controllers\Api\Admin\RolePermissionController;
use App\Http\Controllers\Api\Admin\SecurityPolicyController;
use App\Http\Controllers\Api\Admin\SharingPolicyController;
use App\Http\Controllers\Api\Admin\SystemConfigController;
use App\Http\Controllers\Api\Audit\ActivityLogController;
use App\Http\Controllers\Api\Files\FileController;
use App\Http\Controllers\Api\Files\FileUploadController;
use App\Http\Controllers\Api\Folders\FolderController;
use App\Http\Controllers\Api\Shares\ShareController;
use App\Http\Controllers\Api\Storage\StorageUsageController;
use App\Http\Controllers\Api\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update']);
    Route::patch('users/{user}/activate', [UserController::class, 'activate']);
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate']);

    Route::get('folders/root', [FolderController::class, 'root']);
    Route::get('folders/tree', [FolderController::class, 'tree']);
    Route::get('folders/{folder}/children', [FolderController::class, 'children']);
    Route::apiResource('folders', FolderController::class)->only(['store', 'update', 'destroy']);
    Route::post('folders/{folder}/restore', [FolderController::class, 'restore']);

    Route::get('files/search', [FileController::class, 'search']);
    Route::get('files/{file}', [FileController::class, 'show']);
    Route::patch('files/{file}', [FileController::class, 'update']);
    Route::delete('files/{file}', [FileController::class, 'destroy']);
    Route::post('files/{file}/restore', [FileController::class, 'restore']);
    Route::get('files/{file}/download', [FileController::class, 'download']);
    Route::post('files/upload', [FileUploadController::class, 'store']);

    Route::post('shares', [ShareController::class, 'store']);
    Route::delete('shares/{share}', [ShareController::class, 'destroy']);
    Route::get('shares/me', [ShareController::class, 'mine']);

    Route::get('audit-logs', [ActivityLogController::class, 'index']);
    Route::get('storage/usage', [StorageUsageController::class, 'index']);
    Route::patch('storage/quotas/{user}', [StorageUsageController::class, 'update']);

    Route::prefix('admin')->group(function (): void {
        Route::get('overview', [AdminOverviewController::class, 'index']);

        Route::post('roles', [RolePermissionController::class, 'store']);
        Route::get('roles-permissions', [RolePermissionController::class, 'index']);
        Route::put('roles/{role}/permissions', [RolePermissionController::class, 'update']);

        Route::get('sharing-policy', [SharingPolicyController::class, 'show']);
        Route::put('sharing-policy', [SharingPolicyController::class, 'update']);

        Route::get('security-policy', [SecurityPolicyController::class, 'show']);
        Route::put('security-policy', [SecurityPolicyController::class, 'update']);
        Route::get('sessions', [SecurityPolicyController::class, 'sessions']);
        Route::post('sessions/{user}/revoke', [SecurityPolicyController::class, 'revokeUserSessions']);

        Route::get('system-settings', [SystemConfigController::class, 'showSettings']);
        Route::put('system-settings', [SystemConfigController::class, 'updateSettings']);
        Route::get('backup-config', [SystemConfigController::class, 'showBackup']);
        Route::put('backup-config', [SystemConfigController::class, 'updateBackup']);
        Route::post('backup-run', [SystemConfigController::class, 'triggerBackup']);
    });
});
