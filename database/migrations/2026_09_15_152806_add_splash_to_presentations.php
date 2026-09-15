<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the room looks at before the service has reached its first slide.
     *
     * The projector window is opened on the laptop and then dragged onto the
     * beamer, and for those few seconds the congregation watches whatever the
     * page happens to be showing — which, until now, was the first slide of the
     * deck, put up minutes before anybody was meant to read it. A held title
     * card is the honest picture for that moment, and it is also the only time
     * anyone gets to check that the beamer is lined up at all.
     *
     * Held here rather than in the browser because both devices have to agree:
     * the first press comes as often from the phone at the organ as from the
     * laptop, and whichever hears it, the wall must leave the card and land on
     * the *first* slide and not the second.
     *
     * Off by default, so that every presentation that already exists — and
     * every deck a phone points at a screen mid-service — behaves exactly as it
     * did. It is switched on only where the card is wanted: a presenter window
     * opening a deck of its own accord.
     */
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->boolean('splash')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('splash');
        });
    }
};
