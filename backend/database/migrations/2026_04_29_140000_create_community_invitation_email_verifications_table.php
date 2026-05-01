<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_invitation_email_verifications')) {
            return;
        }

        Schema::create('community_invitation_email_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('community_invitation_id');
            $table->foreign('community_invitation_id', 'ci_email_verif_inv_fk')
                ->references('id')
                ->on('community_invitations')
                ->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['community_invitation_id', 'email'], 'ci_email_verif_inv_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_invitation_email_verifications');
    }
};
