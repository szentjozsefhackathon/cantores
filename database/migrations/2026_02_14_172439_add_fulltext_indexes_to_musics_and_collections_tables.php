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
        // Add full-text index to musics table for title, subtitle, and custom_id
        Schema::table('musics', function (Blueprint $table) {
            $table->fullText(['title', 'subtitle', 'custom_id'])->language('hungarian');
        });

        // Add full-text index to collections table for title and abbreviation
        Schema::table('collections', function (Blueprint $table) {
            $table->fullText(['title', 'abbreviation'])->language('hungarian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('musics', function (Blueprint $table) {
            $table->dropFullText(['title', 'subtitle', 'custom_id']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropFullText(['title', 'abbreviation']);
        });
    }
};
