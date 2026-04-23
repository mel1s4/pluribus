<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('photo_path')->nullable();
            $table->json('gallery_paths')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('place_products')) {
            $products = DB::table('place_products')->orderBy('id')->get();
            foreach ($products as $row) {
                DB::table('place_offers')->insert([
                    'place_id' => $row->place_id,
                    'title' => $row->title,
                    'description' => $row->description,
                    'price' => $row->price,
                    'photo_path' => $row->photo_path,
                    'gallery_paths' => $row->gallery_paths,
                    'tags' => json_encode([]),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        if (Schema::hasTable('place_lessons')) {
            $lessons = DB::table('place_lessons')->orderBy('id')->get();
            foreach ($lessons as $row) {
                DB::table('place_offers')->insert([
                    'place_id' => $row->place_id,
                    'title' => $row->title,
                    'description' => $row->description,
                    'price' => 0,
                    'photo_path' => null,
                    'gallery_paths' => null,
                    'tags' => json_encode([]),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('place_lessons');
        Schema::dropIfExists('place_products');

        Schema::table('places', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
        });

        Schema::table('places', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type']);
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('2026_04_28_120000_places_refactor_location_tags_offers cannot be reversed safely.');
    }
};
