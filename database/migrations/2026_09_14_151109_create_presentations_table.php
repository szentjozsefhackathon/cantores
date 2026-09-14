<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A presentation: one deck being shown, on one screen, once.
     *
     * A projection is the deck; this row is that deck in front of a
     * congregation, and it exists so that two devices can agree on where the
     * service has got to. The cantor is at the organ and the laptop is across
     * the building, so the person who knows when to advance is never the person
     * whose hand is on the keyboard; the row is the authority both of them read
     * and write.
     *
     * Server-side rather than peer to peer, because it then survives a phone
     * that locked itself, a tab that reloaded, and a cantor who picks the remote
     * up after the first hymn — and because it is the seam a websocket transport
     * slides under later without disturbing anything above it.
     *
     * Timestamps rather than a status enum, after loans and device pairings:
     * live means `ended_at` null and `last_seen_at` inside the last few minutes.
     */
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('projection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which borrowed screen is showing it, where one was signed in from
            // a phone. Nothing depends on it; it is how the devices list can say
            // which laptop has the deck up.
            $table->foreignId('device_pairing_id')->nullable()->constrained()->nullOnDelete();

            // Where the service has got to, as the slide's own address rather
            // than as an offset into anybody's array. `go()` takes a position in
            // the *filtered* deck, and the filter is the verses left out today —
            // so the moment one is brought back mid-service every position after
            // it moves. A row and a place within that row survive that, and
            // survive an edit besides.
            //
            // Deliberately not a foreign key: when the row it names is deleted,
            // the service has to land on the next row that survived, and a
            // nulled column would have thrown away the only thing that says
            // which row that is.
            $table->unsignedBigInteger('entry_id')->nullable();
            $table->unsignedInteger('slide_index')->default(0);

            // Where that row stood when it was last seen. The anchor that keeps
            // "the next row that survived" answerable after the row itself is
            // gone: an id says nothing about order, and by the time we are
            // asked, the row whose order we want no longer exists.
            $table->unsignedInteger('entry_sequence')->nullable();

            $table->boolean('blanked')->default(false);

            // Orders reads. Every state change increments it, and a client
            // ignores any state whose version does not exceed the last it
            // applied — without which a read answered just before the cantor
            // pressed space arrives just after it and sends the room back a
            // slide.
            $table->unsignedBigInteger('version')->default(1);

            // The fingerprint of the deck the wall has actually finished
            // engraving, as reported by the presenter with its heartbeat. The
            // remote compares it with the projection's current one, which is the
            // honest answer to "is the wall showing my edit yet". The current
            // one itself is read off the rows rather than stored: see
            // Projection::revision().
            $table->string('drawn_revision')->nullable();

            // The verses brought back for this service alone, keyed by row.
            // Which slides a deck leaves out is a deliberate arrangement kept on
            // the projection; a cantor reacting to a long procession is not
            // rewriting it, so today's deviation lives here and dies with the
            // service.
            $table->json('reveals')->nullable();

            $table->timestamp('started_at');

            // The poll doubles as the heartbeat, so a tab closed without warning
            // goes stale rather than depending on an event browsers do not
            // reliably give.
            $table->timestamp('last_seen_at');

            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'ended_at', 'last_seen_at']);
            $table->index(['projection_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};
