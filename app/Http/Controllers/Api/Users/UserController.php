<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\StorageQuota;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.view'), 403);

        $users = User::query()->with('roles')->latest()->paginate(20);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['super_admin', 'manager', 'employee', 'viewer'])],
            'quota_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $user->syncRoles([$data['role']]);

        if (isset($data['quota_bytes'])) {
            StorageQuota::create([
                'user_id' => $user->id,
                'quota_bytes' => (int) $data['quota_bytes'],
                'used_bytes' => 0,
            ]);
        }

        $this->logAction($request, 'users.create', $user);

        return response()->json($user->load('roles'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.update'), 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['nullable', 'string', Rule::in(['super_admin', 'manager', 'employee', 'viewer'])],
        ]);

        $user->fill($data);
        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        $this->logAction($request, 'users.update', $user);

        return response()->json($user->load('roles'));
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.activate'), 403);

        $user->update(['is_active' => true]);

        $this->logAction($request, 'users.activate', $user);

        return response()->json(['message' => 'User activated.']);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.deactivate'), 403);

        $user->update(['is_active' => false]);

        $this->logAction($request, 'users.deactivate', $user);

        return response()->json(['message' => 'User deactivated.']);
    }

    private function logAction(Request $request, string $action, User $subject): void
    {
        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
