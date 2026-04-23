<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('location_type', 16)->default('none')->after('longitude');
        });

        DB::table('places')->whereNotNull('latitude')->whereNotNull('longitude')->update(['location_type' => 'point']);

        DB::table('places')
            ->whereIn('service_area_type', ['radius', 'polygon'])
            ->update(['location_type' => 'point']);
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('location_type');
        });
    }
};
