<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a screen of words is coloured, for the whole deck at once.
 *
 * Music is engraved black on white and stays so: a staff is a drawing, the
 * engines draw it in ink, and a stave reversed out of black is harder to read
 * across a nave, not easier. Words are the other way about — a projector
 * throwing a full white screen with eight words on it is the brightest thing in
 * a darkened church, and everyone looks away from exactly what they were meant
 * to read.
 *
 * So text-only slides are white on black by default, which is what every other
 * projection in the room does, and the deck may say otherwise once for all of
 * them. One choice rather than one per slide: a deck that changed colour halfway
 * through is a deck that looks broken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projections', function (Blueprint $table) {
            $table->string('text_theme', 16)->default('dark')->after('ratio');
        });
    }

    public function down(): void
    {
        Schema::table('projections', function (Blueprint $table) {
            $table->dropColumn('text_theme');
        });
    }
};
