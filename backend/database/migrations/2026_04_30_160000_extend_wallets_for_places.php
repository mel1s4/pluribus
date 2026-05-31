<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wallets', 'place_id')) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->foreignId('place_id')->nullable()->after('user_id')->constrained('places')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('wallets', 'wallet_owner_key')) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->string('wallet_owner_key', 48)->nullable()->after('public_ref');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("UPDATE wallets SET wallet_owner_key = 'u:' || CAST(user_id AS TEXT) WHERE user_id IS NOT NULL AND (wallet_owner_key IS NULL OR wallet_owner_key = '')");
        } else {
            DB::statement("UPDATE wallets SET wallet_owner_key = CONCAT('u:', user_id) WHERE user_id IS NOT NULL AND (wallet_owner_key IS NULL OR wallet_owner_key = '')");
        }

        if (Schema::hasIndex('wallets', ['community_id', 'user_id'], 'unique')) {
            // MySQL uses the composite unique as the supporting index for `community_id` FKs;
            // drop that FK before dropping the unique.
            if ($this->walletsForeignKeyNameForColumn('community_id') !== null) {
                Schema::table('wallets', function (Blueprint $table): void {
                    $table->dropForeign(['community_id']);
                });
            }
            Schema::table('wallets', function (Blueprint $table): void {
                $table->dropUnique(['community_id', 'user_id']);
            });
        }

        if ($this->walletsForeignKeyNameForColumn('user_id') !== null) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
            });
        }

        $userIdColumn = collect(Schema::getColumns('wallets'))->firstWhere('name', 'user_id');
        if ($userIdColumn !== null && ($userIdColumn['nullable'] ?? false) !== true) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }

        if ($this->walletsForeignKeyNameForColumn('user_id') === null) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('wallets', ['community_id', 'wallet_owner_key'], 'unique')) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->unique(['community_id', 'wallet_owner_key']);
            });
        }

        if ($this->walletsForeignKeyNameForColumn('community_id') === null) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();
            });
        }

        $walletOwnerKeyColumn = collect(Schema::getColumns('wallets'))->firstWhere('name', 'wallet_owner_key');
        if ($walletOwnerKeyColumn !== null && ($walletOwnerKeyColumn['nullable'] ?? false) === true) {
            Schema::table('wallets', function (Blueprint $table): void {
                $table->string('wallet_owner_key', 48)->nullable(false)->change();
            });
        }
    }

    private function walletsForeignKeyNameForColumn(string $column): ?string
    {
        foreach (Schema::getForeignKeys('wallets') as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return $foreignKey['name'];
            }
        }

        return null;
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropUnique(['community_id', 'wallet_owner_key']);
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('place_id');
            $table->dropColumn('wallet_owner_key');
        });

        DB::table('wallets')->whereNull('user_id')->delete();

        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->unique(['community_id', 'user_id']);
        });
    }
};
