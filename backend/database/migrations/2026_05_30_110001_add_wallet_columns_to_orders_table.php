<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'community_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('community_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'payment_method')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('payment_method', 32)->nullable()->after('total_amount');
            });
        }

        if (! Schema::hasColumn('orders', 'wallet_settled_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->timestamp('wallet_settled_at')->nullable()->after('payment_method');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'community_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('community_id');
            });
        }

        $drops = array_values(array_filter([
            Schema::hasColumn('orders', 'payment_method') ? 'payment_method' : null,
            Schema::hasColumn('orders', 'wallet_settled_at') ? 'wallet_settled_at' : null,
        ]));

        if ($drops !== []) {
            Schema::table('orders', function (Blueprint $table) use ($drops): void {
                $table->dropColumn($drops);
            });
        }
    }
};
