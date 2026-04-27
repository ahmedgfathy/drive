<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('department')->nullable()->after('ext_id');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'department' => $user->department ?: 'General',
                    ]);
            }
        });

        Schema::table('shares', function (Blueprint $table): void {
            $table->foreignId('target_user_id')->nullable()->change();
            $table->string('channel')->default('internal')->after('shareable_id');
            $table->string('target_type')->default('user')->after('channel');
            $table->string('target_name')->nullable()->after('target_type');
            $table->string('target_email')->nullable()->after('target_name');
            $table->string('target_department')->nullable()->after('target_email');
            $table->string('public_token', 120)->nullable()->after('target_department');
            $table->string('public_password')->nullable()->after('public_token');
            $table->boolean('allow_download')->default(true)->after('public_password');
        });

        DB::table('shares')->orderBy('id')->chunkById(100, function ($shares): void {
            foreach ($shares as $share) {
                $user = null;

                if ($share->target_user_id) {
                    $user = DB::table('users')->where('id', $share->target_user_id)->first();
                }

                DB::table('shares')
                    ->where('id', $share->id)
                    ->update([
                        'channel' => 'internal',
                        'target_type' => 'user',
                        'target_name' => $user->full_name ?? $user->name ?? null,
                        'target_email' => $user->email ?? null,
                        'target_department' => $user->department ?? null,
                        'allow_download' => true,
                    ]);
            }
        });

        Schema::table('shares', function (Blueprint $table): void {
            $table->dropUnique('shares_unique_target');
            $table->unique(['shareable_type', 'shareable_id', 'target_user_id', 'channel'], 'shares_unique_internal_target');
            $table->unique('public_token');
            $table->index(['channel', 'target_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table): void {
            $table->dropUnique('shares_unique_internal_target');
            $table->dropUnique(['public_token']);
            $table->dropIndex(['channel', 'target_type']);
            $table->dropColumn([
                'channel',
                'target_type',
                'target_name',
                'target_email',
                'target_department',
                'public_token',
                'public_password',
                'allow_download',
            ]);
        });

        DB::table('users')->whereNull('department')->update(['department' => 'General']);

        Schema::table('shares', function (Blueprint $table): void {
            $table->foreignId('target_user_id')->nullable(false)->change();
            $table->unique(['shareable_type', 'shareable_id', 'target_user_id'], 'shares_unique_target');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('department');
        });
    }
};
