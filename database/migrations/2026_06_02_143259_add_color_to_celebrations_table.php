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
        Schema::table('celebrations', function (Blueprint $table) {
            $table->string('color_id')->nullable()->after('season_text');
            $table->string('color_text')->nullable()->after('color_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            $table->dropColumn(['color_id', 'color_text']);
        });
    }
};
