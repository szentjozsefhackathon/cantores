<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop unique index on nickname
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_nickname_unique');
        });

        // Add city_id and first_name_id columns (nullable initially)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->constrained()->after('email');
            $table->foreignId('first_name_id')->nullable()->constrained()->after('city_id');
        });

        // Ensure there is at least one city
        if (DB::table('cities')->count() === 0) {
            DB::table('cities')->insert([
                'name' => 'Unknown',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ensure there is at least one first name (should already exist)
        if (DB::table('first_names')->count() === 0) {
            DB::table('first_names')->insert([
                'name' => 'Unknown',
                'gender' => 'male',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get the default city and first name IDs
        $defaultCityId = DB::table('cities')->orderBy('id')->first()->id;
        $defaultFirstNameId = DB::table('first_names')->orderBy('id')->first()->id;

        // Update existing users with default values
        DB::table('users')->update([
            'city_id' => $defaultCityId,
            'first_name_id' => $defaultFirstNameId,
        ]);

        // Make columns non-nullable
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable(false)->change();
            $table->foreignId('first_name_id')->nullable(false)->change();
        });

        // Drop nickname column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add nickname column back
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('email');
        });

        // Backfill nickname with placeholder (we can't recover original)
        DB::table('users')->whereNull('nickname')->update(['nickname' => DB::raw("'user_' || id")]);

        // Make nickname unique and non-nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->unique()->nullable(false)->change();
        });

        // Drop foreign keys and columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['first_name_id']);
            $table->dropColumn(['city_id', 'first_name_id']);
        });
    }
};
