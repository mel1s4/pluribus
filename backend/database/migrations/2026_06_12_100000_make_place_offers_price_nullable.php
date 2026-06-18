<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_offers', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('place_offers', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->nullable(false)->change();
        });
    }
};
