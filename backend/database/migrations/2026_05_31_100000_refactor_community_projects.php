<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_arguments');

        Schema::table('community_projects', function (Blueprint $table) {
            $table->dropColumn(['thesis_title', 'thesis_body']);
        });

        Schema::table('community_projects', function (Blueprint $table) {
            $table->dateTime('deadline')->nullable()->after('status');
            $table->boolean('has_budget')->default(false)->after('deadline');
            $table->boolean('has_job_positions')->default(false)->after('has_budget');
        });

        DB::table('community_projects')->where('status', 'open')->update(['status' => 'active']);

        if (Schema::hasColumn('community_project_budget_items', 'cost')) {
            Schema::table('community_project_budget_items', function (Blueprint $table) {
                $table->decimal('unit_cost', 12, 2)->default(0)->after('description');
                $table->decimal('units', 10, 2)->default(1)->after('unit_cost');
                $table->decimal('subtotal', 12, 2)->default(0)->after('units');
            });

            DB::table('community_project_budget_items')->orderBy('id')->each(function (object $row): void {
                $cost = (string) ($row->cost ?? '0');
                DB::table('community_project_budget_items')->where('id', $row->id)->update([
                    'unit_cost' => $cost,
                    'units' => '1.00',
                    'subtotal' => $cost,
                ]);
            });

            Schema::table('community_project_budget_items', function (Blueprint $table) {
                $table->dropColumn('cost');
            });
        }

        Schema::create('community_project_job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained('community_projects')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['community_project_id', 'sort_order'], 'cp_job_positions_proj_sort_idx');
        });

        Schema::create('community_project_job_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_position_id')->constrained('community_project_job_positions')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['job_position_id', 'sort_order'], 'cp_job_tasks_pos_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_project_job_tasks');
        Schema::dropIfExists('community_project_job_positions');

        if (Schema::hasColumn('community_project_budget_items', 'unit_cost')) {
            Schema::table('community_project_budget_items', function (Blueprint $table) {
                $table->decimal('cost', 12, 2)->default(0)->after('description');
            });

            DB::table('community_project_budget_items')->orderBy('id')->each(function (object $row): void {
                DB::table('community_project_budget_items')->where('id', $row->id)->update([
                    'cost' => (string) ($row->subtotal ?? $row->unit_cost ?? '0'),
                ]);
            });

            Schema::table('community_project_budget_items', function (Blueprint $table) {
                $table->dropColumn(['unit_cost', 'units', 'subtotal']);
            });
        }

        Schema::table('community_projects', function (Blueprint $table) {
            $table->dropColumn(['deadline', 'has_budget', 'has_job_positions']);
        });

        Schema::table('community_projects', function (Blueprint $table) {
            $table->string('thesis_title')->after('description');
            $table->text('thesis_body')->nullable()->after('thesis_title');
        });

        DB::table('community_projects')->where('status', 'active')->update(['status' => 'open']);

        Schema::create('project_arguments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('community_projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_arguments')->cascadeOnDelete();
            $table->string('stance', 8);
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'author_id']);
        });
    }
};
