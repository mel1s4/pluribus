<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 64);
            $table->string('recurrence_mode', 16)->default('once');
            $table->json('recurrence_weekdays')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('gallery_paths')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_requirements');
    }
};
