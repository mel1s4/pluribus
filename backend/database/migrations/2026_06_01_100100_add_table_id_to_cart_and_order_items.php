<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('table_id')->nullable()->after('place_offer_id')->constrained('tables')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('table_id')->nullable()->after('place_id')->constrained('tables')->nullOnDelete();
            $table->index(['place_id', 'table_id']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['place_id', 'table_id']);
            $table->dropConstrainedForeignId('table_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_id');
        });
    }
};
