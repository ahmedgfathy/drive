<?php

namespace App\Http\Controllers\Api\Files;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class FileController extends Controller
{
    public function show(Request $request, File $file): JsonResponse
    {
        $this->authorize('view', $file);

        return response()->json($file->load('owner', 'folder'));
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('files.view'), 403);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = File::query();

        if ($request->user()->hasRole('employee')) {
            $query->where('owner_id', $request->user()->id);
        }

        if (! empty($data['q'])) {
            $query->where('original_name', 'like', '%'.$data['q'].'%');
        }

        if (! empty($data['mime_type'])) {
            $query->where('mime_type', $data['mime_type']);
        }

        if (! empty($data['owner_id'])) {
            $query->where('owner_id', $data['owner_id']);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function update(Request $request, File $file): JsonResponse
    {
        $this->authorize('update', $file);

        $data = $request->validate([
            'original_name' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        if (array_key_exists('folder_id', $data) && $data['folder_id']) {
            $targetFolder = Folder::findOrFail($data['folder_id']);
            $this->authorize('view', $targetFolder);

            if (! $request->user()->hasAnyRole(['super_admin', 'manager']) && $targetFolder->owner_id !== $file->owner_id) {
                return response()->json(['message' => 'Invalid target folder.'], 422);
            }
        }

        $file->fill($data);
        $file->save();

        $this->logAction($request, 'files.update', $file);

        return response()->json($file);
    }

    public function destroy(Request $request, File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        $file->delete();

        $this->logAction($request, 'files.delete', $file);

        return response()->json(['message' => 'File moved to trash.']);
    }

    public function restore(Request $request, int $file): JsonResponse
    {
        $fileModel = File::withTrashed()->findOrFail($file);
        $this->authorize('restore', $fileModel);

        $fileModel->restore();

        $this->logAction($request, 'files.restore', $fileModel);

        return response()->json(['message' => 'File restored.']);
    }

    public function download(Request $request, File $file)
    {
        $this->authorize('download', $file);

        $this->logAction($request, 'files.download', $file);

        return Storage::disk($file->disk)->download($file->storage_path, $file->original_name);
    }

    public function downloadArchive(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('files.download'), 403);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:file,folder'],
            'items.*.id' => ['required', 'integer'],
        ]);

        $files = collect();

        foreach ($data['items'] as $item) {
            if ($item['type'] === 'file') {
                $file = File::findOrFail($item['id']);
                $this->authorize('download', $file);

                $files->push([
                    'file' => $file,
                    'path' => $this->safeArchiveSegment($file->original_name),
                ]);

                $this->logAction($request, 'files.download_archive', $file);

                continue;
            }

            $folder = Folder::findOrFail($item['id']);
            $this->authorize('view', $folder);

            $nestedFiles = $this->filesForFolderArchive($folder);
            foreach ($nestedFiles as $entry) {
                /** @var File $nestedFile */
                $nestedFile = $entry['file'];
                $this->authorize('download', $nestedFile);
                $files->push($entry);
                $this->logAction($request, 'files.download_archive', $nestedFile);
            }
        }

        abort_if($files->isEmpty(), 422, 'No downloadable files were selected.');

        $archivePath = storage_path('app/temp/'.Str::uuid().'.zip');
        $archiveDirectory = dirname($archivePath);

        if (! is_dir($archiveDirectory)) {
            mkdir($archiveDirectory, 0775, true);
        }

        $zip = new ZipArchive();
        abort_if($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true, 500, 'Unable to create archive.');

        foreach ($this->deduplicateArchivePaths($files) as $entry) {
            /** @var File $file */
            $file = $entry['file'];
            $absolutePath = Storage::disk($file->disk)->path($file->storage_path);

            if (! is_file($absolutePath)) {
                continue;
            }

            $zip->addFile($absolutePath, $entry['path']);
        }

        $zip->close();

        return response()->download(
            $archivePath,
            'pms-drive-download-'.now()->format('Ymd-His').'.zip',
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }

    private function logAction(Request $request, string $action, File $subject): void
    {
        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => File::class,
            'subject_id' => $subject->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, array{file: File, path: string}>
     */
    private function filesForFolderArchive(Folder $folder): Collection
    {
        $folder->loadMissing(['files', 'children']);

        $entries = collect();
        $folderName = $this->safeArchiveSegment($folder->name);

        foreach ($folder->files as $file) {
            $entries->push([
                'file' => $file,
                'path' => $folderName.'/'.$this->safeArchiveSegment($file->original_name),
            ]);
        }

        foreach ($folder->children as $child) {
            foreach ($this->filesForFolderArchive($child) as $entry) {
                $entries->push([
                    'file' => $entry['file'],
                    'path' => $folderName.'/'.ltrim($entry['path'], '/'),
                ]);
            }
        }

        return $entries;
    }

    /**
     * @param  Collection<int, array{file: File, path: string}>  $entries
     * @return Collection<int, array{file: File, path: string}>
     */
    private function deduplicateArchivePaths(Collection $entries): Collection
    {
        $seen = [];

        return $entries->map(function (array $entry) use (&$seen): array {
            $path = $entry['path'];

            if (! isset($seen[$path])) {
                $seen[$path] = 0;

                return $entry;
            }

            $seen[$path]++;
            $suffix = $seen[$path];
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $directory = pathinfo($path, PATHINFO_DIRNAME);
            $renamed = $filename.'-'.$suffix.($extension !== '' ? '.'.$extension : '');

            $entry['path'] = ($directory !== '.' ? $directory.'/' : '').$renamed;

            return $entry;
        });
    }

    private function safeArchiveSegment(string $value): string
    {
        $clean = trim(str_replace('\\', '/', $value), '/');
        $segments = array_filter(explode('/', $clean), fn (string $segment) => $segment !== '' && $segment !== '.' && $segment !== '..');
        $normalized = implode('/', $segments);

        return $normalized !== '' ? $normalized : 'item';
    }
}
