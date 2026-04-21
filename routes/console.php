<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('drive:backfill-employee-storage', function (): void {
    $moved = 0;
    $updated = 0;

    User::query()->chunkById(100, function ($users) use (&$moved, &$updated): void {
        foreach ($users as $user) {
            $employeeId = $user->employee_id ?: ('EMP'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT));
            $safeEmployeeId = preg_replace('/[^A-Za-z0-9._-]/', '_', $employeeId) ?: 'unknown';

            $rootFolder = Folder::firstOrCreate(
                [
                    'owner_id' => $user->id,
                    'parent_id' => null,
                    'name' => $employeeId,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'path_cache' => '/'.$employeeId,
                    'depth' => 0,
                ]
            );

            File::query()->where('owner_id', $user->id)->chunkById(100, function ($files) use ($user, $safeEmployeeId, $rootFolder, &$moved, &$updated): void {
                foreach ($files as $file) {
                    if (! $file->folder_id) {
                        $file->folder_id = $rootFolder->id;
                    }

                    $newPrefix = 'drive/employees/'.$safeEmployeeId;
                    $newPath = $file->storage_path;

                    if (str_starts_with((string) $file->storage_path, 'drive/'.$user->id.'/')) {
                        $newPath = str_replace('drive/'.$user->id, $newPrefix, (string) $file->storage_path);
                    }

                    if ($newPath !== $file->storage_path && Storage::disk($file->disk)->exists($file->storage_path)) {
                        Storage::disk($file->disk)->move($file->storage_path, $newPath);
                        $moved++;
                    }

                    if ($newPath !== $file->storage_path) {
                        $file->storage_path = $newPath;
                        $updated++;
                    }

                    $file->save();
                }
            });
        }
    });

    $this->info('Backfill complete. Files moved: '.$moved.', file records updated: '.$updated);
})->purpose('Backfill employee root folders and migrate storage paths to employee namespaces');
