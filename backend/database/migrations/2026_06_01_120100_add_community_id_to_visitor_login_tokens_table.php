<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_login_tokens', function (Blueprint $table) {
            $table->foreignId('community_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visitor_login_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('community_id');
        });
    }
};
