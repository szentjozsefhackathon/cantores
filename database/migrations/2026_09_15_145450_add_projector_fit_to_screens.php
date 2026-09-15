<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where on the wall the picture actually lands.
     *
     * The deck is built for a shape of screen and the presenter letterboxes it
     * into whatever shape the projector is, which is the right default and is
     * not the whole answer: one of the churches throws a 4:3 beamer at a square
     * screen hung off-centre, so a deck fitted perfectly to the glass is still
     * half a foot high and clipped at the bottom. Nothing about that is a fact
     * about the deck — next Sunday's deck lands exactly as wrong — so it is
     * held here, on the screen, where it survives every deck put on it and is
     * still true when the laptop is opened again next week.
     *
     * Three numbers, all relative to the fitted picture rather than to pixels:
     * a browser window that is resized, a projector swapped for a brighter one
     * and a deck built in another shape all keep whatever the cantor lined up.
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            // 1 is the picture as large as the screen will take it: the fit
            // this application has always drawn, and the one nobody has to
            // think about.
            $table->float('fit_scale')->default(1);

            // And where it sits, as a fraction of its own width and height, so
            // that 0.1 is a tenth of the picture to the right whatever the
            // picture happens to be.
            $table->float('fit_x')->default(0);
            $table->float('fit_y')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn(['fit_scale', 'fit_x', 'fit_y']);
        });
    }
};
