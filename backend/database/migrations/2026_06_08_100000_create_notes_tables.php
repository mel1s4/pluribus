<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->longText('content_markdown')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['folder_id', 'sort_order']);
            $table->index(['author_id', 'created_at']);
        });

        Schema::create('note_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 16);
            $table->timestamps();

            $table->unique(['note_id', 'user_id']);
        });

        Schema::create('note_edit_locks', function (Blueprint $table) {
            $table->foreignId('note_id')->primary()->constrained('notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 64);
            $table->dateTimeTz('locked_until');
            $table->timestamps();
        });

        Schema::create('note_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('edited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->longText('content_markdown')->nullable();
            $table->timestampTz('created_at');

            $table->index(['note_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_revisions');
        Schema::dropIfExists('note_edit_locks');
        Schema::dropIfExists('note_collaborators');
        Schema::dropIfExists('notes');
    }
};
