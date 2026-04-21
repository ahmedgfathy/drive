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
        Schema::create('security_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('password_min_length')->default(8);
            $table->boolean('password_requires_uppercase')->default(true);
            $table->boolean('password_requires_number')->default(true);
            $table->boolean('password_requires_symbol')->default(true);
            $table->unsignedInteger('max_failed_logins')->default(5);
            $table->unsignedInteger('lockout_minutes')->default(15);
            $table->unsignedInteger('session_timeout_minutes')->default(120);
            $table->boolean('enforce_2fa_for_admins')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_policies');
    }
};
