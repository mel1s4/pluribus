<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_offers', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->after('place_id');
        });

        Schema::table('place_requirements', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->after('place_id');
        });

        DB::table('place_offers')
            ->orderBy('id')
            ->select(['id', 'place_id'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('place_offers')
                        ->where('id', $row->id)
                        ->update(['sku' => 'offer-'.$row->place_id.'-'.$row->id]);
                }
            });

        DB::table('place_requirements')
            ->orderBy('id')
            ->select(['id', 'place_id'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('place_requirements')
                        ->where('id', $row->id)
                        ->update(['sku' => 'requirement-'.$row->place_id.'-'.$row->id]);
                }
            });

        Schema::table('place_offers', function (Blueprint $table) {
            $table->unique(['place_id', 'sku']);
        });

        Schema::table('place_requirements', function (Blueprint $table) {
            $table->unique(['place_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::table('place_requirements', function (Blueprint $table) {
            $table->dropUnique(['place_id', 'sku']);
            $table->dropColumn('sku');
        });

        Schema::table('place_offers', function (Blueprint $table) {
            $table->dropUnique(['place_id', 'sku']);
            $table->dropColumn('sku');
        });
    }
};
