<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_offers', function (Blueprint $table): void {
            $table->string('category', 128)->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('place_offers', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
