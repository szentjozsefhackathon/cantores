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
            $table->renameColumn('is_published', 'is_private');
        });

        // Update the index name if it exists
        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropIndex('music_plans_user_id_is_published_index');
            $table->index(['user_id', 'is_private'], 'music_plans_user_id_is_private_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plans', function (Blueprint $table) {
            $table->renameColumn('is_private', 'is_published');
        });

        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropIndex('music_plans_user_id_is_private_index');
            $table->index(['user_id', 'is_published'], 'music_plans_user_id_is_published_index');
        });
    }
};
