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
        Schema::table('communities', function (Blueprint $table): void {
            $table->string('slug', 64)->nullable()->after('name');
        });

        $existing = Community::query()->orderBy('id')->get(['id', 'name']);
        $taken = [];
        foreach ($existing as $community) {
            $base = Community::slugFromName((string) $community->name);
            $slug = $base;
            $suffix = 2;
            while (isset($taken[$slug]) || DB::table('communities')->where('slug', $slug)->where('id', '!=', $community->id)->exists()) {
                $slug = substr($base, 0, 58).'-'.$suffix;
                $suffix++;
            }
            $taken[$slug] = true;
            DB::table('communities')->where('id', $community->id)->update(['slug' => $slug]);
        }

        Schema::table('communities', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
