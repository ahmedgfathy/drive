<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SharingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharingPolicyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('shares.manage_policy'), 403);

        return response()->json($this->policy());
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('shares.manage_policy'), 403);

        $data = $request->validate([
            'internal_sharing_enabled' => ['required', 'boolean'],
            'allow_external_links' => ['required', 'boolean'],
            'default_link_expiry_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_share_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'require_password_for_external_links' => ['required', 'boolean'],
        ]);

        $policy = $this->policy();
        $policy->fill($data);
        $policy->updated_by = $request->user()->id;
        $policy->save();

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'sharing.policy.update',
            'subject_type' => SharingPolicy::class,
            'subject_id' => $policy->id,
            'metadata' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json($policy);
    }

    private function policy(): SharingPolicy
    {
        return SharingPolicy::query()->firstOrCreate([], [
            'internal_sharing_enabled' => true,
            'allow_external_links' => false,
            'default_link_expiry_days' => 7,
            'max_share_duration_days' => 30,
            'require_password_for_external_links' => true,
        ]);
    }
}
