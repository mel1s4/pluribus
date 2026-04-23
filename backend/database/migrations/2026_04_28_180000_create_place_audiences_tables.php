<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('place_audience_user', function (Blueprint $table) {
            $table->foreignId('place_audience_id')->constrained('place_audiences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['place_audience_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_audience_user');
        Schema::dropIfExists('place_audiences');
    }
};
