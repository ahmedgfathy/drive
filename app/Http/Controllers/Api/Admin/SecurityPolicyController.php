<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SecurityPolicy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityPolicyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('security.manage_settings'), 403);

        return response()->json($this->policy());
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('security.manage_settings'), 403);

        $data = $request->validate([
            'password_min_length' => ['required', 'integer', 'min:8', 'max:64'],
            'password_requires_uppercase' => ['required', 'boolean'],
            'password_requires_number' => ['required', 'boolean'],
            'password_requires_symbol' => ['required', 'boolean'],
            'max_failed_logins' => ['required', 'integer', 'min:3', 'max:20'],
            'lockout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'enforce_2fa_for_admins' => ['required', 'boolean'],
        ]);

        $policy = $this->policy();
        $policy->fill($data);
        $policy->updated_by = $request->user()->id;
        $policy->save();

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'security.policy.update',
            'subject_type' => SecurityPolicy::class,
            'subject_id' => $policy->id,
            'metadata' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json($policy);
    }

    public function sessions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('security.manage_settings'), 403);

        $users = User::query()
            ->select(['id', 'name', 'email', 'is_active'])
            ->withCount('tokens')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function revokeUserSessions(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('security.manage_settings'), 403);

        $count = $user->tokens()->count();
        $user->tokens()->delete();

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'security.sessions.revoke',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => ['revoked_tokens' => $count],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'User sessions revoked.']);
    }

    private function policy(): SecurityPolicy
    {
        return SecurityPolicy::query()->firstOrCreate([], [
            'password_min_length' => 8,
            'password_requires_uppercase' => true,
            'password_requires_number' => true,
            'password_requires_symbol' => true,
            'max_failed_logins' => 5,
            'lockout_minutes' => 15,
            'session_timeout_minutes' => 120,
            'enforce_2fa_for_admins' => false,
        ]);
    }
}
