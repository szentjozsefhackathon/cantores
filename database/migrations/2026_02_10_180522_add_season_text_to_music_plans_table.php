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
        Schema::table('music_plans', function (Blueprint $table) {
            $table->string('season_text')->nullable()->after('season');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropColumn('season_text');
        });
    }
};
