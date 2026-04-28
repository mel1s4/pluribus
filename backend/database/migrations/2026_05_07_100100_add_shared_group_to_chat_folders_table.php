<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_folders', function (Blueprint $table) {
            $table->foreignId('shared_group_id')
                ->nullable()
                ->after('user_id')
                ->constrained('groups')
                ->nullOnDelete();
            $table->index(['shared_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_folders', function (Blueprint $table) {
            $table->dropIndex(['shared_group_id', 'sort_order']);
            $table->dropConstrainedForeignId('shared_group_id');
        });
    }
};

