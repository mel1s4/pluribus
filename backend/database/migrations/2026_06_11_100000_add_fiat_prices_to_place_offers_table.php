<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('place_offers', 'local_price')) {
            return;
        }

        Schema::table('place_offers', function (Blueprint $table) {
            $table->decimal('local_price', 12, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('place_offers', 'local_price')) {
            return;
        }

        Schema::table('place_offers', function (Blueprint $table) {
            $table->dropColumn('local_price');
        });
    }
};
