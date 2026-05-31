<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->string('local_currency_code', 3)->nullable()->after('currency_name');
        });

        if (Schema::hasColumn('place_offers', 'fiat_price') && ! Schema::hasColumn('place_offers', 'local_price')) {
            Schema::table('place_offers', function (Blueprint $table): void {
                $table->decimal('local_price', 12, 2)->nullable()->after('price');
            });

            DB::table('place_offers')->whereNotNull('fiat_price')->update([
                'local_price' => DB::raw('fiat_price'),
            ]);

            Schema::table('place_offers', function (Blueprint $table): void {
                $table->dropColumn(['fiat_price', 'fiat_currency_code']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('place_offers', 'fiat_price') && Schema::hasColumn('place_offers', 'local_price')) {
            Schema::table('place_offers', function (Blueprint $table): void {
                $table->decimal('fiat_price', 12, 2)->nullable()->after('price');
                $table->string('fiat_currency_code', 3)->nullable()->after('fiat_price');
            });

            DB::table('place_offers')->whereNotNull('local_price')->update([
                'fiat_price' => DB::raw('local_price'),
            ]);

            Schema::table('place_offers', function (Blueprint $table): void {
                $table->dropColumn('local_price');
            });
        }

        Schema::table('communities', function (Blueprint $table): void {
            $table->dropColumn('local_currency_code');
        });
    }
};
