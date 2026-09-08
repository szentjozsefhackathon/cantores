<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a booklet looks like before anyone touches it, rewritten from a print.
     *
     * The first numbers were guesses at a sensible page; these are what a test
     * print of an A5 booklet actually wanted. Lyrics at 10.5pt over a 5 mm staff
     * balance, where 11pt over 7 mm gave the music more weight than the words,
     * and a heading at 0.9 stands over that without shouting.
     *
     * ABC gets a booklet-wide staff separation of its own because it is the one
     * engine that reserves that space above the first staff as well as between
     * two of them: at the score's own 36 it is the gap under a heading, and on a
     * booklet page that gap is a hole. Only defaults change here — a booklet
     * already laid out keeps every number it was given.
     */
    public function up(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->float('lyric_size_pt')->default(10.5)->change();
            $table->float('staff_height_mm')->default(5)->change();
            $table->float('heading_scale')->default(0.9)->change();
            $table->float('abc_staff_sep')->default(25);
        });
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->float('lyric_size_pt')->default(11)->change();
            $table->float('staff_height_mm')->default(7)->change();
            $table->float('heading_scale')->default(1)->change();
            $table->dropColumn('abc_staff_sep');
        });
    }
};
