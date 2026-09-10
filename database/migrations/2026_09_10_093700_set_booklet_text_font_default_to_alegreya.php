<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alegreya becomes the default face, everywhere at once.
 *
 * It is the face the whole application's sizes are now quoted in: a booklet's
 * lyric size means points of Alegreya, and every other face is sized to match it
 * (see OPTICAL_X_HEIGHT in resources/js/booklet-geometry.js). Having the column
 * default to the face the numbers are calibrated against is the difference
 * between a default that is a choice and one that is merely the first entry in a
 * list.
 *
 * Existing booklets keep whatever they are set to. Unlike Inter before it, EB
 * Garamond is a face somebody may well have wanted — it is what a chant book
 * looks like — and a booklet already laid out and printed should not change face
 * because the default moved underneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->string('text_font', 64)->default('Alegreya')->change();
        });
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->string('text_font', 64)->default('EB Garamond')->change();
        });
    }
};
