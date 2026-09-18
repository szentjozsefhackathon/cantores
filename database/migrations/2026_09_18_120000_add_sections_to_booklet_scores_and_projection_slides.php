<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of a score's `%section`s one row prints, and in what order.
 *
 * A hymn is written once, its stanzas and its refrain marked in the source;
 * this is what lets a row pick "1, 4, 2, 4" out of it instead of printing the
 * whole thing or forking a score per stanza. See plans/score-sections.md.
 *
 * `null` means the whole score, as written — every row today, and any row
 * whose score carries no marker at all. A number the score no longer has is
 * kept rather than dropped, and skipped when drawn: the row is made for one
 * date, and a score edited afterwards is usually meant to be followed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('settings_override');
        });

        Schema::table('projection_slides', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('excluded_slides');
        });
    }

    public function down(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropColumn('sections');
        });

        Schema::table('projection_slides', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
