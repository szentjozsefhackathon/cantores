<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A screen: one browser that is showing the room something.
     *
     * The noun the presentations table left out. A `Presentation` is a deck
     * being shown once, but it only ever came into being because a laptop
     * opened a URL naming that deck — so nothing in the application meant *the
     * wall*, and a phone could not say "put this on the screen", only "follow
     * whatever the laptop already chose".
     *
     * This row is that address. It holds one interesting column: which
     * presentation it is currently showing, nullable, because a screen showing
     * nothing is the normal state before the service and the one the phone most
     * needs to be able to say out loud.
     *
     * Nothing is paired to make it. Both devices already hold a session for the
     * same person; what was missing was never permission but an address, so
     * claiming a screen is one device saying "I am the one facing the room" —
     * which it says by opening the page only a wall would open.
     */
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which borrowed screen this is, where the laptop was signed in from
            // a phone. Revoking the pairing signs that laptop out, and the
            // screen goes with it rather than staying live and pointing at a
            // browser that can no longer read anything.
            $table->foreignId('device_pairing_id')->nullable()->constrained()->nullOnDelete();

            // What makes a browser this browser. The session cookie, as
            // everywhere else here: a laptop reloading the page is the same
            // screen, and a second window of the same browser is deliberately
            // the same screen too, because it is the same room.
            $table->string('session_id')->unique();

            // For "which of my two screens is this", asked by a phone choosing
            // between them. Read through DeviceDescription, as for a pairing.
            $table->string('user_agent')->nullable();

            // What the room is looking at, or nothing. Nulled rather than
            // cascaded when the presentation goes, because a screen outlives the
            // decks put on it — that is the whole reason it exists.
            $table->foreignId('presentation_id')->nullable()->constrained()->nullOnDelete();

            // The poll doubles as the heartbeat, after presentations. Only the
            // screen's own browser refreshes it: a phone reading the row must
            // not keep a closed laptop looking alive.
            $table->timestamp('last_seen_at');

            $table->timestamps();

            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
