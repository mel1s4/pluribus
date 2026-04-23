<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('title')->nullable();
            $table->string('icon_emoji', 16)->nullable();
            $table->string('icon_bg_color', 7)->nullable();
            $table->foreignId('folder_id')->nullable()->constrained('chat_folders')->nullOnDelete();
            $table->timestamps();

            $table->index(['community_id', 'type']);
            $table->index(['owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
