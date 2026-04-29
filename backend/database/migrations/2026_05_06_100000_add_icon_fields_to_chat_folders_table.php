<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('icon_emoji', 16)->nullable()->after('name');
            $table->string('icon_bg_color', 7)->nullable()->after('icon_emoji');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn(['icon_emoji', 'icon_bg_color']);
        });
    }
};
