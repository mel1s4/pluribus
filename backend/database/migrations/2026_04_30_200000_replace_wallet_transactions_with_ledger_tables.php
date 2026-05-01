<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wallet_privileged_audits');
        Schema::dropIfExists('wallet_transactions');

        Schema::create('wallet_ledger_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('height');
            $table->string('prev_commitment', 64);
            $table->string('merkle_root', 64)->nullable();
            $table->unsignedBigInteger('first_entry_id')->nullable();
            $table->unsignedBigInteger('last_entry_id')->nullable();
            $table->unsignedInteger('entry_count')->default(0);
            $table->string('block_commitment', 64)->nullable();
            $table->text('operator_signature')->nullable();
            $table->timestamp('sealed_at')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'height']);
            $table->index(['community_id', 'id']);
        });

        Schema::create('wallet_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_ledger_block_id')->constrained('wallet_ledger_blocks')->cascadeOnDelete();
            $table->unsignedInteger('position_in_block');
            $table->string('type', 32);
            $table->decimal('amount', 14, 2);
            $table->string('from_public_ref', 26)->nullable();
            $table->string('to_public_ref', 26);
            $table->string('actor_kind', 32);
            $table->text('note')->nullable();
            $table->string('leaf_hash', 64);
            $table->timestamps();

            $table->index(['community_id', 'id']);
            $table->unique(['wallet_ledger_block_id', 'position_in_block'], 'wallet_ledger_entries_blk_pos_uq');
        });

        Schema::create('wallet_privileged_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_ledger_entry_id')->constrained('wallet_ledger_entries')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('wallet_ledger_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_privileged_audits');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_ledger_blocks');

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 14, 2);
            $table->string('from_public_ref', 26)->nullable();
            $table->string('to_public_ref', 26);
            $table->string('actor_kind', 32);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['community_id', 'created_at']);
            $table->index('from_public_ref');
            $table->index('to_public_ref');
        });

        Schema::create('wallet_privileged_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_transaction_id')->constrained('wallet_transactions')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('wallet_transaction_id');
        });
    }
};
