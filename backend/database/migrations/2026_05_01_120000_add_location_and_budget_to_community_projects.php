<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('community_projects', 'latitude')) {
            Schema::table('community_projects', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable()->after('thesis_body');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                $table->string('location_type', 16)->default('none')->after('longitude');
                $table->string('service_area_type', 16)->default('none')->after('location_type');
                $table->unsignedInteger('radius_meters')->nullable()->after('service_area_type');
                $table->json('area_geojson')->nullable()->after('radius_meters');
            });
        }

        if (! Schema::hasTable('community_project_budget_items')) {
            Schema::create('community_project_budget_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_project_id')->constrained('community_projects')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('cost', 12, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['community_project_id', 'sort_order'], 'cp_budget_items_proj_sort_idx');
            });
        } elseif (! Schema::hasIndex('community_project_budget_items', ['community_project_id', 'sort_order'])) {
            Schema::table('community_project_budget_items', function (Blueprint $table) {
                $table->index(['community_project_id', 'sort_order'], 'cp_budget_items_proj_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_project_budget_items');

        Schema::table('community_projects', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'location_type',
                'service_area_type',
                'radius_meters',
                'area_geojson',
            ]);
        });
    }
};
