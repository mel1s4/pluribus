<?php

use App\Models\Community;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_user')) {
            Schema::create('community_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('community_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 16)->default('member');
                $table->timestamps();
                $table->unique(['community_id', 'user_id']);
            });
        }

        $community = Community::current();
        $now = now();
        $rows = DB::table('users')
            ->select(['id', 'user_type'])
            ->get();

        foreach ($rows as $row) {
            $role = match ((string) ($row->user_type ?? '')) {
                'admin' => 'admin',
                'developer' => 'developer',
                'visitor' => 'visitor',
                default => 'member',
            };
            DB::table('community_user')->updateOrInsert(
                [
                    'community_id' => $community->id,
                    'user_id' => (int) $row->id,
                ],
                [
                    'role' => $role,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Intentionally keep data; rollback should not orphan historical memberships.
    }
};
