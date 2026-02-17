<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MUSICS: add the single FTS column
        Schema::table('musics', function (Blueprint $table) {
            // store concatenated titles here (title + subtitle, etc)
            $table->text('titles')->nullable();
        });

        // Full-text index ONLY on musics.titles (Hungarian)
        Schema::table('musics', function (Blueprint $table) {
            $table->fullText('titles')->language('hungarian');
        });

        // COLLECTIONS: remove full-text indexes (if they exist)
        // If you previously created a fullText index there, drop it.
        // The index name depends on Laravel's naming; default is: collections_title_abbreviation_fulltext
        Schema::table('collections', function (Blueprint $table) {
            // adjust name if yours differs
            $table->dropFullText('collections_title_abbreviation_fulltext');
        });

        // MUSICS: remove the old multi-column fullText index (if it exists)
        // default name: musics_title_subtitle_custom_id_fulltext
        Schema::table('musics', function (Blueprint $table) {
            // adjust name if yours differs
            $table->dropFullText('musics_title_subtitle_custom_id_fulltext');
        });
    }

    public function down(): void
    {
        // restore old indexes (optional)
        Schema::table('musics', function (Blueprint $table) {
            $table->fullText(['title', 'subtitle', 'custom_id'])->language('hungarian');
            $table->dropFullText('musics_titles_fulltext'); // default name for titles fulltext
            $table->dropColumn('titles');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->fullText(['title', 'abbreviation'])->language('hungarian');
        });
    }
};
