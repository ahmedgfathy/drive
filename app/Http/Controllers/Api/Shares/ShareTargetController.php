<?php

namespace App\Http\Controllers\Api\Shares;

use App\Http\Controllers\Controller;
use App\Models\SharingPolicy;
use App\Models\User;
use App\Services\Auth\ActiveDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ShareTargetController extends Controller
{
    public function __construct(
        private readonly ActiveDirectoryService $activeDirectory,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('files.share_internal'), 403);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        $results = [];

        foreach ($this->localUserMatches($term) as $user) {
            $results[] = [
                'type' => 'employee',
                'key' => 'employee:'.$user->id,
                'label' => $user->full_name ?: $user->name,
                'description' => trim(implode(' | ', array_filter([
                    $user->department,
                    $user->employee_id,
                    $user->email,
                ]))),
                'employee_id' => $user->employee_id,
                'email' => $user->email,
                'department' => $user->department ?: 'General',
                'samaccountname' => $user->name,
                'target_user_id' => $user->id,
            ];
        }

        try {
            foreach ($this->activeDirectory->searchUsers($term, 20) as $entry) {
                $key = mb_strtolower('employee:'.($entry['email'] ?: $entry['employee_id'] ?: $entry['samaccountname']));

                if (collect($results)->contains(fn (array $item) => mb_strtolower((string) $item['key']) === $key)) {
                    continue;
                }

                $results[] = [
                    'type' => 'employee',
                    'key' => $key,
                    'label' => $entry['display_name'],
                    'description' => trim(implode(' | ', array_filter([
                        $entry['department'] ?? null,
                        $entry['employee_id'] ?? null,
                        $entry['email'] ?? null,
                    ]))),
                    'employee_id' => $entry['employee_id'] ?? null,
                    'email' => $entry['email'] ?? null,
                    'department' => $entry['department'] ?? 'General',
                    'samaccountname' => $entry['samaccountname'] ?? null,
                ];
            }

            foreach ($this->activeDirectory->searchDepartments($term, 10) as $department) {
                $results[] = [
                    'type' => 'department',
                    'key' => 'department:'.mb_strtolower($department),
                    'label' => $department,
                    'description' => 'Share with everyone in this department',
                    'department' => $department,
                ];
            }
        } catch (RuntimeException) {
            // Fall back to local users only when directory lookup is unavailable.
        }

        $results[] = [
            'type' => 'everyone',
            'key' => 'everyone:all',
            'label' => 'Everyone',
            'description' => 'Share with every directory member that PMS Drive can resolve',
        ];

        $sorted = collect($results)
            ->unique('key')
            ->sortBy(function (array $item) use ($term): string {
                $label = mb_strtolower((string) ($item['label'] ?? ''));
                $starts = $term !== '' && str_starts_with($label, mb_strtolower($term)) ? '0' : '1';

                return $starts.'|'.$label;
            }, SORT_NATURAL)
            ->values();

        return response()->json([
            'targets' => $sorted,
            'policy' => SharingPolicy::query()->first(),
        ]);
    }

    private function localUserMatches(string $term)
    {
        return User::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('full_name', 'like', '%'.$term.'%')
                        ->orWhere('name', 'like', '%'.$term.'%')
                        ->orWhere('employee_id', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhere('department', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get();
    }
}
