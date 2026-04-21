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
        Schema::create('sharing_policies', function (Blueprint $table) {
            $table->id();
            $table->boolean('internal_sharing_enabled')->default(true);
            $table->boolean('allow_external_links')->default(false);
            $table->unsignedInteger('default_link_expiry_days')->default(7);
            $table->unsignedInteger('max_share_duration_days')->default(30);
            $table->boolean('require_password_for_external_links')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharing_policies');
    }
};
