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
            $table->string('visibility_scope', 32)->default('public')->after('tags');
        });

        Schema::table('place_requirements', function (Blueprint $table) {
            $table->string('visibility_scope', 32)->default('public')->after('example_place_offer_id');
        });

        Schema::create('place_offer_audience', function (Blueprint $table) {
            $table->foreignId('place_offer_id')->constrained('place_offers')->cascadeOnDelete();
            $table->foreignId('place_audience_id')->constrained('place_audiences')->cascadeOnDelete();
            $table->primary(['place_offer_id', 'place_audience_id']);
        });

        Schema::create('place_requirement_audience', function (Blueprint $table) {
            $table->foreignId('place_requirement_id')->constrained('place_requirements')->cascadeOnDelete();
            $table->foreignId('place_audience_id')->constrained('place_audiences')->cascadeOnDelete();
            $table->primary(['place_requirement_id', 'place_audience_id']);
        });

        DB::table('place_offers')->update(['visibility_scope' => 'public']);
        DB::table('place_requirements')->update(['visibility_scope' => 'public']);
    }

    public function down(): void
    {
        Schema::dropIfExists('place_requirement_audience');
        Schema::dropIfExists('place_offer_audience');

        Schema::table('place_requirements', function (Blueprint $table) {
            $table->dropColumn('visibility_scope');
        });

        Schema::table('place_offers', function (Blueprint $table) {
            $table->dropColumn('visibility_scope');
        });
    }
};
