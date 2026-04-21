<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BackupConfig;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    public function showSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.manage_settings'), 403);

        return response()->json($this->settings());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.manage_settings'), 403);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_website' => ['required', 'url', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:255'],
            'footer_address' => ['nullable', 'string', 'max:500'],
            'maintenance_mode' => ['required', 'boolean'],
            'read_only_mode' => ['required', 'boolean'],
        ]);

        $settings = $this->settings();
        $settings->fill($data);
        $settings->updated_by = $request->user()->id;
        $settings->save();

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'system.settings.update',
            'subject_type' => SystemSetting::class,
            'subject_id' => $settings->id,
            'metadata' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json($settings);
    }

    public function showBackup(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('backups.manage'), 403);

        return response()->json($this->backup());
    }

    public function updateBackup(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('backups.manage'), 403);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'database_frequency' => ['required', 'string', 'max:50'],
            'files_frequency' => ['required', 'string', 'max:50'],
            'retention_period' => ['required', 'string', 'max:50'],
        ]);

        $backup = $this->backup();
        $backup->fill($data);
        $backup->updated_by = $request->user()->id;
        $backup->save();

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'backup.config.update',
            'subject_type' => BackupConfig::class,
            'subject_id' => $backup->id,
            'metadata' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json($backup);
    }

    public function triggerBackup(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('backups.manage'), 403);

        $backup = $this->backup();
        $backup->last_backup_at = now();
        $backup->last_backup_status = 'simulated_success';
        $backup->updated_by = $request->user()->id;
        $backup->save();

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'backup.run.manual',
            'subject_type' => BackupConfig::class,
            'subject_id' => $backup->id,
            'metadata' => ['status' => 'simulated_success'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Backup task executed (simulation).', 'backup' => $backup]);
    }

    private function settings(): SystemSetting
    {
        return SystemSetting::query()->firstOrCreate([], [
            'company_name' => 'Petroleum Marine Services',
            'company_website' => 'https://www.pmsoffshore.com',
        ]);
    }

    private function backup(): BackupConfig
    {
        return BackupConfig::query()->firstOrCreate([], [
            'enabled' => false,
            'database_frequency' => 'daily',
            'files_frequency' => 'daily',
            'retention_period' => '30 days',
            'last_backup_status' => 'never',
        ]);
    }
}
