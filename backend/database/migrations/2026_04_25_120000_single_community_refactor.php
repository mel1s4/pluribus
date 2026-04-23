<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->text('rules')->nullable()->after('description');
            $table->string('logo')->nullable()->after('rules');
        });

        DB::table('users')->where('user_type', 'moderator')->update(['user_type' => 'member']);

        Schema::dropIfExists('community_invitations');
        Schema::dropIfExists('community_user');

        $ids = DB::table('communities')->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            DB::table('communities')->insert([
                'name' => 'Community',
                'description' => null,
                'rules' => null,
                'logo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $keepId = (int) $ids->first();
            DB::table('communities')->where('id', '!=', $keepId)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['rules', 'logo']);
        });

        Schema::create('community_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->timestamps();
            $table->unique(['community_id', 'user_id']);
        });

        Schema::create('community_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('email')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }
};
