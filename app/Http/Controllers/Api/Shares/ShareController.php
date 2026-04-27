<?php

namespace App\Http\Controllers\Api\Shares;

use App\Http\Controllers\Controller;
use App\Mail\ShareReceivedMail;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\Share;
use App\Models\SharingPolicy;
use App\Models\User;
use App\Services\Auth\ActiveDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ShareController extends Controller
{
    public function __construct(
        private readonly ActiveDirectoryService $activeDirectory,
    ) {
    }

    public function policy(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('files.share_internal'), 403);

        return response()->json(
            SharingPolicy::query()->first() ?? [
                'internal_sharing_enabled' => true,
                'allow_external_links' => false,
                'default_link_expiry_days' => 7,
                'max_share_duration_days' => 30,
                'require_password_for_external_links' => true,
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user(), 403);

        $policy = SharingPolicy::query()->first();

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.shareable_type' => ['required', Rule::in(['file', 'folder'])],
            'items.*.shareable_id' => ['required', 'integer'],
            'channel' => ['required', Rule::in(['internal', 'external'])],
            'target_type' => ['required', Rule::in(['user', 'department', 'everyone', 'external'])],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_email' => ['nullable', 'email', 'max:255'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'target_department' => ['nullable', 'string', 'max:255'],
            'directory_user' => ['nullable', 'array'],
            'directory_user.employee_id' => ['nullable', 'string', 'max:255'],
            'directory_user.display_name' => ['nullable', 'string', 'max:255'],
            'directory_user.email' => ['nullable', 'email', 'max:255'],
            'directory_user.samaccountname' => ['nullable', 'string', 'max:255'],
            'directory_user.department' => ['nullable', 'string', 'max:255'],
            'permission' => ['required', Rule::in(['view', 'edit'])],
            'expires_at' => ['nullable', 'date'],
            'allow_download' => ['nullable', 'boolean'],
            'public_password' => ['nullable', 'string', 'min:4', 'max:255'],
        ]);

        $this->enforceSharingPolicy($policy, $data);

        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;
        $allowDownload = array_key_exists('allow_download', $data) ? (bool) $data['allow_download'] : true;
        $created = [];

        foreach ($data['items'] as $item) {
            $shareable = $this->resolveShareable($item['shareable_type'], (int) $item['shareable_id']);
            abort_unless($this->canAccessShareable($request->user(), $shareable), 403, 'This action is unauthorized.');

            if ($data['channel'] === 'internal') {
                foreach ($this->resolveInternalRecipients($data) as $recipient) {
                    $share = Share::updateOrCreate(
                        [
                            'shareable_type' => $shareable::class,
                            'shareable_id' => $shareable->id,
                            'target_user_id' => $recipient->id,
                            'channel' => 'internal',
                        ],
                        [
                            'target_type' => $data['target_type'],
                            'target_name' => $recipient->full_name ?: $recipient->name,
                            'target_email' => $recipient->email,
                            'target_department' => $recipient->department,
                            'permission' => $data['permission'],
                            'granted_by' => $request->user()->id,
                            'expires_at' => $expiresAt,
                            'allow_download' => $allowDownload,
                            'public_token' => null,
                            'public_password' => null,
                        ]
                    );

                    $share->load(['shareable', 'targetUser', 'grantedBy']);
                    $created[] = $share;
                    $this->notifyRecipient($share);
                }

                continue;
            }

            $share = Share::create([
                'shareable_type' => $shareable::class,
                'shareable_id' => $shareable->id,
                'channel' => 'external',
                'target_type' => 'external',
                'target_user_id' => null,
                'target_name' => $data['target_name'] ?? null,
                'target_email' => $data['target_email'] ?? null,
                'target_department' => null,
                'permission' => 'view',
                'granted_by' => $request->user()->id,
                'expires_at' => $expiresAt,
                'allow_download' => $allowDownload,
                'public_token' => (string) Str::uuid(),
                'public_password' => ! empty($data['public_password']) ? Hash::make($data['public_password']) : null,
            ]);

            $share->load(['shareable', 'grantedBy']);
            $created[] = $share;
            $this->notifyRecipient($share);
        }

        foreach ($created as $share) {
            ActivityLog::create([
                'actor_id' => $request->user()?->id,
                'action' => $share->channel === 'external' ? 'files.share_external' : 'files.share_internal',
                'subject_type' => Share::class,
                'subject_id' => $share->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'channel' => $share->channel,
                    'target_type' => $share->target_type,
                    'target_email' => $share->target_email,
                ],
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Share created successfully.',
            'shares' => collect($created)->map(fn (Share $share) => $this->serializeShare($share))->values(),
        ], 201);
    }

    public function destroy(Request $request, Share $share): JsonResponse
    {
        $this->authorize('delete', $share);

        $shareId = $share->id;
        $share->delete();

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'shares.revoke',
            'subject_type' => Share::class,
            'subject_id' => $shareId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Share revoked.']);
    }

    public function mine(Request $request): JsonResponse
    {
        $shares = Share::query()
            ->with(['shareable', 'grantedBy', 'targetUser'])
            ->where('target_user_id', $request->user()->id)
            ->where('channel', 'internal')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->paginate(20);

        $shares->getCollection()->transform(fn (Share $share) => array_merge(
            $share->toArray(),
            ['share_url' => $this->shareUrl($share)]
        ));

        return response()->json($shares);
    }

    private function enforceSharingPolicy(?SharingPolicy $policy, array $data): void
    {
        if ($data['channel'] === 'internal' && $policy && ! $policy->internal_sharing_enabled) {
            abort(403, 'Internal sharing is currently disabled by policy.');
        }

        if ($data['channel'] === 'external' && $policy && ! $policy->allow_external_links) {
            abort(403, 'External sharing links are disabled by policy.');
        }

        if ($data['channel'] === 'external' && $policy?->require_password_for_external_links && empty($data['public_password'])) {
            abort(422, 'A password is required for external shares by policy.');
        }

        if ($data['channel'] === 'internal' && $data['target_type'] === 'external') {
            abort(422, 'Invalid internal share target.');
        }

        if ($data['channel'] === 'external' && $data['target_type'] !== 'external') {
            abort(422, 'External share links must use an external recipient.');
        }

        if ($policy && ! empty($data['expires_at'])) {
            $maxDate = now()->addDays((int) $policy->max_share_duration_days);
            if (Carbon::parse($data['expires_at'])->greaterThan($maxDate)) {
                abort(422, 'Share expiry exceeds maximum allowed duration.');
            }
        }
    }

    private function resolveShareable(string $type, int $id): File|Folder
    {
        $class = $type === 'file' ? File::class : Folder::class;

        return $class::findOrFail($id);
    }

    /**
     * @return array<int, User>
     */
    private function resolveInternalRecipients(array $data): array
    {
        return match ($data['target_type']) {
            'user' => [$this->resolveSingleRecipient($data)],
            'department' => $this->resolveDepartmentRecipients((string) ($data['target_department'] ?? '')),
            'everyone' => $this->resolveAllRecipients(),
            default => [],
        };
    }

    private function resolveSingleRecipient(array $data): User
    {
        if (! empty($data['target_user_id'])) {
            return User::findOrFail($data['target_user_id']);
        }

        if (! empty($data['directory_user'])) {
            return $this->syncDirectoryUser($data['directory_user']);
        }

        abort(422, 'A directory user must be selected.');
    }

    /**
     * @return array<int, User>
     */
    private function resolveDepartmentRecipients(string $department): array
    {
        $department = trim($department);
        abort_if($department === '', 422, 'Department is required.');

        $users = User::query()->where('department', $department)->get()->keyBy('id')->all();

        try {
            foreach ($this->activeDirectory->listUsersByDepartment($department) as $entry) {
                $synced = $this->syncDirectoryUser($entry);
                $users[$synced->id] = $synced;
            }
        } catch (Throwable) {
            // Fall back to local users when LDAP lookup is unavailable.
        }

        return array_values($users);
    }

    /**
     * @return array<int, User>
     */
    private function resolveAllRecipients(): array
    {
        $users = User::query()->get()->keyBy('id')->all();

        try {
            foreach ($this->activeDirectory->listAllUsers() as $entry) {
                $synced = $this->syncDirectoryUser($entry);
                $users[$synced->id] = $synced;
            }
        } catch (Throwable) {
            // Fall back to local users only when LDAP lookup is unavailable.
        }

        return array_values($users);
    }

    /**
     * @param  array<string, mixed>  $directoryUser
     */
    private function syncDirectoryUser(array $directoryUser): User
    {
        $email = mb_strtolower(trim((string) ($directoryUser['email'] ?? '')));
        $employeeId = trim((string) ($directoryUser['employee_id'] ?? ''));
        $fullName = trim((string) ($directoryUser['display_name'] ?? '')) ?: $employeeId;
        $samAccountName = trim((string) ($directoryUser['samaccountname'] ?? ''));
        $department = trim((string) ($directoryUser['department'] ?? '')) ?: 'General';

        $user = null;

        if ($email !== '') {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        if (! $user && $employeeId !== '') {
            $user = User::query()->where('employee_id', $employeeId)->first();
        }

        if (! $user && $samAccountName !== '') {
            $user = User::query()->where('name', $samAccountName)->first();
        }

        if (! $user) {
            $user = new User();
            $user->password = Hash::make(Str::random(40));
            $user->mobile = 'AD'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
            $user->ext_id = 'AD'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
            $user->is_active = true;
        }

        $user->name = $samAccountName !== '' ? $samAccountName : Str::slug($fullName, '.');
        $user->full_name = $fullName;
        $user->department = $department;
        $user->email = $email !== '' ? $email : ($employeeId !== '' ? $employeeId.'@pms.local' : Str::uuid().'@pms.local');
        $user->employee_id = $employeeId !== '' ? $employeeId : ($user->employee_id ?: 'EMP'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT));
        $user->mobile = $user->mobile ?: 'AD'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
        $user->ext_id = $samAccountName !== '' ? $samAccountName : ($user->ext_id ?: 'AD'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT));
        $user->is_active = true;
        $user->save();

        try {
            if (! $user->roles()->exists()) {
                $user->assignRole('employee');
            }
        } catch (Throwable) {
            // Role assignment should not block creating a share recipient.
        }

        return $user->fresh();
    }

    private function notifyRecipient(Share $share): void
    {
        try {
            if (! $share->target_email) {
                return;
            }

            Mail::to($share->target_email)->send(new ShareReceivedMail(
                share: $share,
                shareTitle: $this->shareTitle($share),
                shareUrl: $this->shareUrl($share),
                loginUrl: $this->loginUrl(),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function shareTitle(Share $share): string
    {
        if ($share->shareable instanceof File) {
            return $share->shareable->original_name;
        }

        if ($share->shareable instanceof Folder) {
            return $share->shareable->name;
        }

        return 'an item';
    }

    private function shareUrl(Share $share): string
    {
        $frontendUrl = rtrim((string) config('app.url'), '/');

        if ($share->channel === 'external' && $share->public_token) {
            return $frontendUrl.'/shared/external/'.$share->public_token;
        }

        if ($share->shareable instanceof Folder) {
            return $frontendUrl.'/folders/'.$share->shareable->id;
        }

        return $frontendUrl.'/shared';
    }

    private function loginUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/login';
    }

    private function canAccessShareable(User $user, File|Folder $shareable): bool
    {
        if ($shareable->owner_id === $user->id || $user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        if ($this->matchesOwnerIdentity($user, $shareable)) {
            return true;
        }

        if ($shareable instanceof File) {
            return $this->fileSharedWithUser($user, $shareable)
                || $this->folderPathSharedWithUser($user, $shareable->owner_id, $shareable->folder?->path_cache);
        }

        return $this->folderPathSharedWithUser($user, $shareable->owner_id, $shareable->path_cache);
    }

    private function fileSharedWithUser(User $user, File $file): bool
    {
        return $file->shares()
            ->where('target_user_id', $user->id)
            ->where('channel', 'internal')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function folderPathSharedWithUser(User $user, int $ownerId, ?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        return Folder::query()
            ->where('owner_id', $ownerId)
            ->whereIn('path_cache', $this->ancestorPaths($path))
            ->whereHas('shares', function ($query) use ($user): void {
                $query->where('target_user_id', $user->id)
                    ->where('channel', 'internal')
                    ->where(function ($shareQuery): void {
                        $shareQuery->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->exists();
    }

    private function matchesOwnerIdentity(User $user, File|Folder $shareable): bool
    {
        $shareable->loadMissing('owner');
        $owner = $shareable->owner;

        if (! $owner) {
            return false;
        }

        $candidatePairs = [
            [$user->employee_id, $owner->employee_id],
            [$user->email, $owner->email],
            [$user->ext_id, $owner->ext_id],
            [$user->name, $owner->name],
        ];

        foreach ($candidatePairs as [$left, $right]) {
            $normalizedLeft = $this->normalizeIdentityValue($left);
            $normalizedRight = $this->normalizeIdentityValue($right);

            if ($normalizedLeft !== null && $normalizedLeft === $normalizedRight) {
                return true;
            }
        }

        return false;
    }

    private function normalizeIdentityValue(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return Collection<int, string>
     */
    private function ancestorPaths(string $path): Collection
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return collect(['/']);
        }

        $segments = explode('/', $trimmed);
        $paths = [];

        foreach ($segments as $index => $segment) {
            $slice = array_slice($segments, 0, $index + 1);
            $paths[] = '/'.implode('/', $slice);
        }

        return collect($paths);
    }

    private function serializeShare(Share $share): array
    {
        return [
            'id' => $share->id,
            'channel' => $share->channel,
            'target_type' => $share->target_type,
            'target_name' => $share->target_name,
            'target_email' => $share->target_email,
            'target_department' => $share->target_department,
            'permission' => $share->permission,
            'expires_at' => optional($share->expires_at)?->toISOString(),
            'allow_download' => $share->allow_download,
            'public_url' => $share->public_token ? $this->shareUrl($share) : null,
            'shareable_type' => $share->shareable_type,
            'shareable_id' => $share->shareable_id,
        ];
    }
}
