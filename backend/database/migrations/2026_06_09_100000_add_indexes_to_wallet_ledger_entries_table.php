<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table): void {
            $table->index('from_public_ref');
            $table->index('to_public_ref');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table): void {
            $table->dropIndex(['from_public_ref']);
            $table->dropIndex(['to_public_ref']);
        });
    }
};
