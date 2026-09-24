<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A row with no score may still hold music.
     *
     * Words written straight into a booklet or a deck have been Markdown until
     * now. A few bars of a response, a psalm tone, the chords of a refrain sung
     * once — none of these is worth a score of its own in the library, and all
     * of them are music. So a text row may name the notation its words are
     * written in, and is then engraved exactly as a score in that format would
     * be. Null keeps it Markdown, which is every row written before this.
     */
    public function up(): void
    {
        foreach (['booklet_scores', 'projection_slides'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('text_format', 16)->nullable()->after('text');
            });
        }
    }

    public function down(): void
    {
        foreach (['booklet_scores', 'projection_slides'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('text_format');
            });
        }
    }
};
