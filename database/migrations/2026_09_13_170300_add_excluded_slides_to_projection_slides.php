<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The slides a row still makes but the service does not show.
 *
 * A hymn with six verses is one row and six screens, and on an ordinary Sunday
 * three of them are sung. Taking the row out loses the hymn; editing the score
 * to drop the verses is vandalism against everyone else who sings it; so what is
 * wanted is the third thing — the slide is still cut, still drawn, still sitting
 * in the editor's contact sheet where it can be put back with one click, and the
 * presenter walks past it.
 *
 * Keyed by ratio, like the overrides beside it, because the page breaks are:
 * `%pagebreak169` and `%pagebreak43` cut one score into different numbers of
 * screens, so "the third slide" is a different verse at a different shape.
 * Positions within a row, zero-based, so a slide that moves because a break
 * moved is not silently the wrong one — a break moved is a different cut of the
 * score, and the deck should be looked at again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projection_slides', function (Blueprint $table) {
            $table->json('excluded_slides')->nullable()->after('settings_override');
        });
    }

    public function down(): void
    {
        Schema::table('projection_slides', function (Blueprint $table) {
            $table->dropColumn('excluded_slides');
        });
    }
};
