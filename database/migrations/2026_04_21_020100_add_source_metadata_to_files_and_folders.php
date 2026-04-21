<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->timestamp('source_created_at')->nullable()->after('depth');
            $table->timestamp('source_modified_at')->nullable()->after('source_created_at');
        });

        Schema::table('files', function (Blueprint $table): void {
            $table->timestamp('source_created_at')->nullable()->after('version');
            $table->timestamp('source_modified_at')->nullable()->after('source_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['source_created_at', 'source_modified_at']);
        });

        Schema::table('folders', function (Blueprint $table): void {
            $table->dropColumn(['source_created_at', 'source_modified_at']);
        });
    }
};
