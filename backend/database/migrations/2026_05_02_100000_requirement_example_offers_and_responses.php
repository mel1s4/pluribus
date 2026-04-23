<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_requirements', function (Blueprint $table) {
            $table->foreignId('example_place_offer_id')
                ->nullable()
                ->after('tags')
                ->constrained('place_offers')
                ->nullOnDelete();
        });

        Schema::create('place_requirement_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_requirement_id')->constrained('place_requirements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('photo_path')->nullable();
            $table->json('gallery_paths')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_requirement_responses');

        Schema::table('place_requirements', function (Blueprint $table) {
            $table->dropForeign(['example_place_offer_id']);
            $table->dropColumn('example_place_offer_id');
        });
    }
};
