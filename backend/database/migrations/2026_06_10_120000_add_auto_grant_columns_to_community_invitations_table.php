<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_invitations', function (Blueprint $table): void {
            $table->decimal('grant_credits', 10, 2)->nullable()->after('uses_count');
            $table->unsignedInteger('grant_limit_uses')->nullable()->after('grant_credits');
            $table->unsignedInteger('grant_uses_count')->default(0)->after('grant_limit_uses');
        });
    }

    public function down(): void
    {
        Schema::table('community_invitations', function (Blueprint $table): void {
            $table->dropColumn([
                'grant_credits',
                'grant_limit_uses',
                'grant_uses_count',
            ]);
        });
    }
};
