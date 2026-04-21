<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\StorageQuota;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.view'), 403);

        $users = User::query()->with('roles', 'storageQuota')->latest()->paginate(20);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.create'), 403);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('users', 'employee_id')],
            'mobile' => ['required', 'string', 'max:30', Rule::unique('users', 'mobile')],
            'ext_id' => ['required', 'string', 'max:30', Rule::unique('users', 'ext_id')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['super_admin', 'manager', 'employee', 'viewer'])],
            'quota_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = User::create([
            'name' => $data['full_name'],
            'full_name' => $data['full_name'],
            'employee_id' => $data['employee_id'],
            'mobile' => $data['mobile'],
            'ext_id' => $data['ext_id'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $user->syncRoles([$data['role']]);

        Folder::firstOrCreate(
            [
                'owner_id' => $user->id,
                'parent_id' => null,
                'name' => $user->employee_id,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'path_cache' => '/'.$user->employee_id,
                'depth' => 0,
            ]
        );

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
            'full_name' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:30', Rule::unique('users', 'mobile')->ignore($user->id)],
            'ext_id' => ['nullable', 'string', 'max:30', Rule::unique('users', 'ext_id')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::in(['super_admin', 'manager', 'employee', 'viewer'])],
            'is_active' => ['nullable', 'boolean'],
            'quota_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $oldEmployeeId = (string) $user->employee_id;

        if (isset($data['full_name'])) {
            $data['name'] = $data['full_name'];
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->fill($data);
        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        if (isset($data['quota_bytes'])) {
            StorageQuota::updateOrCreate(
                ['user_id' => $user->id],
                ['quota_bytes' => (int) $data['quota_bytes']]
            );
        }

        if (isset($data['employee_id']) && $oldEmployeeId !== (string) $data['employee_id']) {
            $this->syncEmployeeNamespace($user, $oldEmployeeId, (string) $data['employee_id']);
        }

        $this->logAction($request, 'users.update', $user);

        return response()->json($user->load('roles', 'storageQuota'));
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

    private function syncEmployeeNamespace(User $user, string $oldEmployeeId, string $newEmployeeId): void
    {
        $oldSafe = preg_replace('/[^A-Za-z0-9._-]/', '_', $oldEmployeeId) ?: 'unknown';
        $newSafe = preg_replace('/[^A-Za-z0-9._-]/', '_', $newEmployeeId) ?: 'unknown';

        if ($oldSafe === $newSafe) {
            $rootFolder = Folder::query()->where('owner_id', $user->id)->whereNull('parent_id')->first();
            if ($rootFolder) {
                $rootFolder->name = $newEmployeeId;
                $rootFolder->path_cache = '/'.$newEmployeeId;
                $rootFolder->save();
                $this->syncChildPaths($rootFolder);
            }

            return;
        }

        $oldPrefix = 'drive/employees/'.$oldSafe;
        $newPrefix = 'drive/employees/'.$newSafe;

        File::query()->where('owner_id', $user->id)->chunkById(100, function ($files) use ($oldPrefix, $newPrefix): void {
            foreach ($files as $file) {
                $currentPath = (string) $file->storage_path;
                if (! str_starts_with($currentPath, $oldPrefix.'/')) {
                    continue;
                }

                $newPath = preg_replace('/^'.preg_quote($oldPrefix, '/').'\//', $newPrefix.'/', $currentPath, 1) ?: $currentPath;

                if ($newPath !== $currentPath && Storage::disk($file->disk)->exists($currentPath)) {
                    Storage::disk($file->disk)->move($currentPath, $newPath);
                }

                if ($newPath !== $currentPath) {
                    $file->storage_path = $newPath;
                    $file->save();
                }
            }
        });

        $rootFolder = Folder::query()->where('owner_id', $user->id)->whereNull('parent_id')->first();
        if ($rootFolder) {
            $rootFolder->name = $newEmployeeId;
            $rootFolder->path_cache = '/'.$newEmployeeId;
            $rootFolder->save();
            $this->syncChildPaths($rootFolder);
        }
    }

    private function syncChildPaths(Folder $folder): void
    {
        $folder->load('children');

        foreach ($folder->children as $child) {
            $child->depth = $folder->depth + 1;
            $child->path_cache = rtrim((string) $folder->path_cache, '/').'/'.$child->name;
            $child->save();
            $this->syncChildPaths($child);
        }
    }
}
