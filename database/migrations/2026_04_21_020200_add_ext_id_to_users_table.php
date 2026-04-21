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
            $table->string('ext_id')->nullable()->after('mobile');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $extId = $user->ext_id ?: ('EXT'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT));
                DB::table('users')->where('id', $user->id)->update(['ext_id' => $extId]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('ext_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['ext_id']);
            $table->dropColumn('ext_id');
        });
    }
};
