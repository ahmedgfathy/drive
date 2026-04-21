<?php

namespace Database\Seeders;

use App\Models\StorageQuota;
use App\Models\BackupConfig;
use App\Models\SecurityPolicy;
use App\Models\SharingPolicy;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.activate',
            'users.deactivate',
            'roles.assign',
            'folders.view',
            'folders.create',
            'folders.rename',
            'folders.move',
            'folders.delete',
            'folders.restore',
            'files.view',
            'files.upload',
            'files.download',
            'files.rename',
            'files.move',
            'files.delete',
            'files.restore',
            'files.share_internal',
            'shares.revoke',
            'storage.view_usage',
            'storage.manage_quota',
            'audit.view',
            'admin.dashboard.view',
            'roles.manage_permissions',
            'shares.manage_policy',
            'security.manage_settings',
            'system.manage_settings',
            'backups.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'sanctum']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'sanctum']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'sanctum']);

        $superAdmin->syncPermissions(Permission::all());

        $manager->syncPermissions([
            'users.view',
            'folders.view',
            'folders.create',
            'folders.rename',
            'folders.move',
            'folders.delete',
            'folders.restore',
            'files.view',
            'files.upload',
            'files.download',
            'files.rename',
            'files.move',
            'files.delete',
            'files.restore',
            'files.share_internal',
            'shares.revoke',
            'storage.view_usage',
            'audit.view',
            'admin.dashboard.view',
        ]);

        $employee->syncPermissions([
            'folders.view',
            'folders.create',
            'folders.rename',
            'folders.move',
            'folders.delete',
            'folders.restore',
            'files.view',
            'files.upload',
            'files.download',
            'files.rename',
            'files.move',
            'files.delete',
            'files.restore',
            'files.share_internal',
        ]);

        $viewer->syncPermissions([
            'folders.view',
            'files.view',
            'files.download',
        ]);

        $user = User::updateOrCreate(
            ['email' => 'xinreal@company.local'],
            [
                'name' => 'xinreal',
                'full_name' => 'xinreal',
                'employee_id' => 'EMP000001',
                'mobile' => '0000000001',
                'ext_id' => 'EXT0001',
                'password' => Hash::make('ZeroCall20!@H'),
                'is_active' => true,
            ]
        );

        $user->syncRoles(['super_admin']);

        StorageQuota::firstOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => 50 * 1024 * 1024 * 1024, 'used_bytes' => 0]
        );

        SharingPolicy::firstOrCreate([], [
            'internal_sharing_enabled' => true,
            'allow_external_links' => false,
            'default_link_expiry_days' => 7,
            'max_share_duration_days' => 30,
            'require_password_for_external_links' => true,
            'updated_by' => $user->id,
        ]);

        SecurityPolicy::firstOrCreate([], [
            'password_min_length' => 8,
            'password_requires_uppercase' => true,
            'password_requires_number' => true,
            'password_requires_symbol' => true,
            'max_failed_logins' => 5,
            'lockout_minutes' => 15,
            'session_timeout_minutes' => 120,
            'enforce_2fa_for_admins' => false,
            'updated_by' => $user->id,
        ]);

        SystemSetting::firstOrCreate([], [
            'company_name' => 'Petroleum Marine Services',
            'company_website' => 'https://www.pmsoffshore.com',
            'support_email' => 'info@pmsoffshore.com',
            'updated_by' => $user->id,
        ]);

        BackupConfig::firstOrCreate([], [
            'enabled' => false,
            'database_frequency' => 'daily',
            'files_frequency' => 'daily',
            'retention_period' => '30 days',
            'last_backup_status' => 'never',
            'updated_by' => $user->id,
        ]);
    }
}
