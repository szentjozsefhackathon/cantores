<?php

use App\Models\Presentation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The opening of a service, which turns out to have three pictures and not
     * two.
     *
     * The title card is up while the window is dragged onto the beamer and the
     * projector is lined up against it — and then the room fills, and what
     * should be on the wall is nothing at all, because a title card held for
     * twenty minutes in front of a seated congregation is an advertisement and
     * not a welcome. Only when the first hymn is announced does the deck begin.
     *
     * So: card, then dark, then the deck. Each press moves it on one, which is
     * also the answer to the question the boolean could not answer — how a
     * cantor gets *out* of the card, which with two states was a thing you had
     * to already know.
     *
     * Ordered, and only ever walked forwards. That is what the boolean's latch
     * was for and is worth keeping for the same reason: the wall reports every
     * ten seconds whether anything happened, and a heartbeat sent a moment
     * before the phone's press must not be able to put the card back over the
     * hymn the room has just been given.
     */
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('splash');
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->string('splash', 8)->default(Presentation::SPLASH_OFF);
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('splash');
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->boolean('splash')->default(false);
        });
    }
};
