<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Naming the music is a choice, like naming the variation.
     *
     * The slot alone is usually enough — whoever holds the booklet is looking
     * for the moment, not the title — so the music's own name is off until
     * someone asks for it on that row, and then it joins the slot's line.
     */
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->boolean('show_music_title')->default(false)->after('show_variation');
        });
    }

    public function down(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropColumn('show_music_title');
        });
    }
};
