<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('surveys', 'allow_multiple')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->boolean('allow_multiple')->default(false)->after('closes_at');
                $table->boolean('require_ranked')->default(false)->after('allow_multiple');
                $table->boolean('allow_add_options')->default(false)->after('require_ranked');
            });
        }

        if (! Schema::hasColumn('survey_options', 'is_custom')) {
            Schema::table('survey_options', function (Blueprint $table) {
                $table->boolean('is_custom')->default(false)->after('sort_order');
                $table->foreignId('created_by_user_id')->nullable()->after('is_custom')
                    ->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('survey_votes', 'rank')) {
            Schema::table('survey_votes', function (Blueprint $table) {
                $table->dropForeign(['survey_id']);
                $table->dropForeign(['user_id']);
                $table->dropForeign(['survey_option_id']);
            });

            Schema::table('survey_votes', function (Blueprint $table) {
                $table->unsignedSmallInteger('rank')->nullable()->after('survey_option_id');
                $table->dropUnique(['survey_id', 'user_id']);
                $table->unique(['survey_id', 'user_id', 'survey_option_id']);
                $table->index(['survey_id', 'user_id']);
            });

            Schema::table('survey_votes', function (Blueprint $table) {
                $table->foreign('survey_id')->references('id')->on('surveys')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('survey_option_id')->references('id')->on('survey_options')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('survey_votes', 'rank')) {
            Schema::table('survey_votes', function (Blueprint $table) {
                $table->dropForeign(['survey_id']);
                $table->dropForeign(['user_id']);
                $table->dropForeign(['survey_option_id']);
            });

            Schema::table('survey_votes', function (Blueprint $table) {
                $table->dropIndex(['survey_id', 'user_id']);
                $table->dropUnique(['survey_id', 'user_id', 'survey_option_id']);
                $table->unique(['survey_id', 'user_id']);
                $table->dropColumn('rank');
            });

            Schema::table('survey_votes', function (Blueprint $table) {
                $table->foreign('survey_id')->references('id')->on('surveys')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('survey_option_id')->references('id')->on('survey_options')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('survey_options', 'is_custom')) {
            Schema::table('survey_options', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by_user_id');
                $table->dropColumn('is_custom');
            });
        }

        if (Schema::hasColumn('surveys', 'allow_multiple')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->dropColumn(['allow_multiple', 'require_ranked', 'allow_add_options']);
            });
        }
    }
};
