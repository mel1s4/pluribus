<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('proposer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thesis_title');
            $table->text('thesis_body')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestamps();

            $table->index(['community_id', 'created_at']);
        });

        Schema::create('project_arguments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('community_projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_arguments')->cascadeOnDelete();
            $table->string('stance', 8);
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_arguments');
        Schema::dropIfExists('community_projects');
    }
};
