<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->text('terms_markdown')->nullable()->after('rules');
            $table->text('privacy_policy_markdown')->nullable()->after('terms_markdown');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['terms_markdown', 'privacy_policy_markdown']);
        });
    }
};
