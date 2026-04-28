<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->nullOnDelete();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('type', 24)->default('info');
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content_markdown')->nullable();
            $table->json('tags')->nullable();
            $table->dateTimeTz('start_at')->nullable();
            $table->dateTimeTz('end_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->text('recurrence_rule')->nullable();
            $table->string('recurrence_id', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('influence_area_type', 16)->default('none');
            $table->unsignedInteger('influence_radius_meters')->nullable();
            $table->json('influence_area_geojson')->nullable();
            $table->string('visibility_scope', 24)->default('private');
            $table->timestamps();

            $table->index(['community_id', 'type']);
            $table->index(['author_id', 'created_at']);
            $table->index(['shared_group_id', 'created_at']);
            $table->index(['calendar_id', 'start_at']);
            $table->index(['visibility_scope', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

