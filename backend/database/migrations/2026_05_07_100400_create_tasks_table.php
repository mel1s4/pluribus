<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('highlighted')->default(false);
            $table->timestamps();

            $table->unique('post_id');
            $table->index(['folder_id', 'position']);
            $table->index(['assignee_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

