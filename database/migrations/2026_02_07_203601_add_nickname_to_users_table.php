<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('email');
        });

        // Backfill existing users with a placeholder nickname
        \DB::table('users')->whereNull('nickname')->update(['nickname' => \DB::raw("'user_' || id")]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->unique()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
