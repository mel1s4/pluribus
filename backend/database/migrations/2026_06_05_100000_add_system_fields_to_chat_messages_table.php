<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('type', 20)->default('user')->after('user_id');
            $table->string('event_key', 40)->nullable()->after('type');
            $table->json('event_meta')->nullable()->after('event_key');
            $table->index(['chat_id', 'id', 'type'], 'chat_messages_chat_id_id_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_chat_id_id_type_idx');
            $table->dropColumn(['type', 'event_key', 'event_meta']);
        });
    }
};
