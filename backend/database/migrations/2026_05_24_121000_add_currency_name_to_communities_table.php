<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->string('currency_name', 64)->nullable()->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->dropColumn('currency_name');
        });
    }
};
