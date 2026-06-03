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
            $table->string('share_token', 32)->nullable()->unique()->after('private_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
