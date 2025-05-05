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
        Schema::table('users', function (Blueprint $table) {
            $table->string('mfa_code')->nullable();
            $table->timestamp('mfa_expires_at')->nullable();
            $table->boolean('is_mfa_verified')->default(false);
        });
    }
    
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_code', 'mfa_expires_at', 'is_mfa_verified']);
        });
    }
    
};
