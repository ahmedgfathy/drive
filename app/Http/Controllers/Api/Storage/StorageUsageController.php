<?php

namespace App\Http\Controllers\Api\Storage;

use App\Http\Controllers\Controller;
use App\Models\StorageQuota;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('storage.view_usage'), 403);

        $rows = StorageQuota::query()
            ->with('user:id,name,email')
            ->orderByDesc('used_bytes')
            ->paginate(50);

        return response()->json($rows);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('storage.manage_quota'), 403);

        $data = $request->validate([
            'quota_bytes' => ['required', 'integer', 'min:0'],
        ]);

        $quota = StorageQuota::updateOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => (int) $data['quota_bytes']]
        );

        return response()->json($quota->load('user:id,name,email'));
    }
}
