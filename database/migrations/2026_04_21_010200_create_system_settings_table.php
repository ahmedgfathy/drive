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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('Petroleum Marine Services');
            $table->string('company_website')->default('https://www.pmsoffshore.com');
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('footer_address')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('read_only_mode')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
