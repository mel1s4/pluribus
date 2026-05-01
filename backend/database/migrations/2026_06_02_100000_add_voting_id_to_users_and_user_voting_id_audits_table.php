<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('voting_id', 6)->nullable()->unique()->after('user_type');
        });

        Schema::create('user_voting_id_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('old_voting_id', 6)->nullable();
            $table->string('new_voting_id', 6)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_voting_id_audits');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['voting_id']);
            $table->dropColumn('voting_id');
        });
    }
};
