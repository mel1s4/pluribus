<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->string('currency_code', 4)->nullable()->after('default_language');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->dropColumn('currency_code');
        });
    }
};
