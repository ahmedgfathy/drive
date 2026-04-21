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
            $table->string('full_name')->nullable()->after('name');
            $table->string('employee_id')->nullable()->after('email');
            $table->string('mobile')->nullable()->after('employee_id');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $derivedEmployeeId = $user->employee_id ?: ('EMP'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT));
                $derivedMobile = $user->mobile ?: ('000000'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT));

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'full_name' => $user->full_name ?: $user->name,
                        'employee_id' => $derivedEmployeeId,
                        'mobile' => $derivedMobile,
                    ]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('employee_id');
            $table->unique('mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['employee_id']);
            $table->dropUnique(['mobile']);
            $table->dropColumn(['full_name', 'employee_id', 'mobile']);
        });
    }
};
