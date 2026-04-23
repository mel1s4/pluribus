<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('service_area_type', 16)->default('none');
            $table->unsignedInteger('radius_meters')->nullable();
            $table->json('area_geojson')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('place_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('photo_path')->nullable();
            $table->json('gallery_paths')->nullable();
            $table->timestamps();
        });

        Schema::create('place_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_lessons');
        Schema::dropIfExists('place_products');
        Schema::dropIfExists('places');
    }
};
