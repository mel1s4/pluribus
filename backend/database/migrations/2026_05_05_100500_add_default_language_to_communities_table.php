<?php

use App\Support\LocaleOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->string('default_language', 8)->default(LocaleOptions::default())->after('logo');
        });

        DB::table('communities')->whereNull('default_language')->update([
            'default_language' => LocaleOptions::default(),
        ]);
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->dropColumn('default_language');
        });
    }
};
