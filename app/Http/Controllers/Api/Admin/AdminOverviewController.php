<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Share;
use App\Models\StorageQuota;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOverviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('admin.dashboard.view'), 403);

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $totalFiles = File::count();
        $sharedFiles = Share::distinct('shareable_id')->count('shareable_id');
        $storageUsed = StorageQuota::sum('used_bytes');
        $storageAllocated = StorageQuota::sum('quota_bytes');

        $recentActivities = ActivityLog::query()
            ->with('actor:id,name,email')
            ->latest('created_at')
            ->limit(20)
            ->get();

        $failedLogins = ActivityLog::query()
            ->where('action', 'auth.login_failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'total_files' => $totalFiles,
                'shared_files' => $sharedFiles,
                'storage_used_bytes' => (int) $storageUsed,
                'storage_left_bytes' => max((int) ($storageAllocated - $storageUsed), 0),
                'failed_logins_last_24h' => $failedLogins,
            ],
            'recent_activities' => $recentActivities,
        ]);
    }
}
