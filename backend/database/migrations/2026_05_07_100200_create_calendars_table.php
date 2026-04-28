<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#22c55e');
            $table->string('visibility_scope', 24)->default('private');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['community_id', 'visibility_scope']);
            $table->index(['owner_id', 'created_at']);
            $table->index(['shared_group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};

