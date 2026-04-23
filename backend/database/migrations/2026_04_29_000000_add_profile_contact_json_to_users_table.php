<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('phone_numbers')->nullable()->after('avatar_path');
            $table->json('contact_emails')->nullable()->after('phone_numbers');
            $table->json('aliases')->nullable()->after('contact_emails');
            $table->json('external_links')->nullable()->after('aliases');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_numbers', 'contact_emails', 'aliases', 'external_links']);
        });
    }
};
