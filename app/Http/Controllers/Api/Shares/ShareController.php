<?php

namespace App\Http\Controllers\Api\Shares;

use App\Http\Controllers\Controller;
use App\Mail\ShareReceivedMail;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\Share;
use App\Models\SharingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class ShareController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Share::class);

        $policy = SharingPolicy::query()->first();
        if ($policy && ! $policy->internal_sharing_enabled) {
            abort(403, 'Internal sharing is currently disabled by policy.');
        }

        $data = $request->validate([
            'shareable_type' => ['required', Rule::in(['file', 'folder'])],
            'shareable_id' => ['required', 'integer'],
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required', Rule::in(['view', 'edit'])],
            'expires_at' => ['nullable', 'date'],
        ]);

        if ($policy && ! empty($data['expires_at'])) {
            $maxDate = now()->addDays((int) $policy->max_share_duration_days);
            if (Carbon::parse($data['expires_at'])->greaterThan($maxDate)) {
                abort(422, 'Share expiry exceeds maximum allowed duration.');
            }
        }

        $type = $data['shareable_type'] === 'file' ? File::class : Folder::class;

        $shareable = $type::findOrFail($data['shareable_id']);
        if ($request->user()->id !== $shareable->owner_id && ! $request->user()->hasAnyRole(['super_admin', 'manager'])) {
            abort(403);
        }

        $share = Share::updateOrCreate(
            [
                'shareable_type' => $type,
                'shareable_id' => $shareable->id,
                'target_user_id' => $data['target_user_id'],
            ],
            [
                'permission' => $data['permission'],
                'granted_by' => $request->user()->id,
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        $share->load(['shareable', 'targetUser', 'grantedBy']);

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'files.share_internal',
            'subject_type' => Share::class,
            'subject_id' => $share->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        try {
            if ($share->targetUser?->email) {
                Mail::to($share->targetUser->email)->send(new ShareReceivedMail(
                    share: $share,
                    shareTitle: $this->shareTitle($share),
                    shareUrl: $this->shareUrl($share),
                ));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json($share, 201);
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
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->paginate(20);

        return response()->json($shares);
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

        if ($share->shareable instanceof Folder) {
            return $frontendUrl.'/folders/'.$share->shareable->id;
        }

        return $frontendUrl.'/shared';
    }
}
