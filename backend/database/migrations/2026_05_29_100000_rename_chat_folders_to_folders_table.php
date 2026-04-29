<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_folders') && ! Schema::hasTable('folders')) {
            Schema::rename('chat_folders', 'folders');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('folders') && ! Schema::hasTable('chat_folders')) {
            Schema::rename('folders', 'chat_folders');
        }
    }
};
