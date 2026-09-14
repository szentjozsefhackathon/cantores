<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pairing: one attempt to sign a borrowed screen in from a phone that is
     * already trusted.
     *
     * The church laptop is the problem this table exists for. It is already set
     * up, already online, and belongs to nobody — so the cantor who wants their
     * projection on it has to type an account into a strange keyboard in front of
     * a congregation, and leave whatever the browser remembers behind for the
     * next person. A pairing replaces that with a code on the screen and a tap on
     * a phone.
     *
     * A row is therefore two things in sequence, and the timestamps say which it
     * currently is. Before `claimed_at` it is a short-lived invitation: a token
     * worth a couple of minutes, rotated in place while nobody scans it, and good
     * exactly once. After `claimed_at` it is a signed-in device — the record that
     * lets the phone end a session it can no longer see, which is the half of the
     * feature a lending link could never provide.
     */
    public function up(): void
    {
        Schema::create('device_pairings', function (Blueprint $table) {
            $table->id();

            // Null until someone approves: a pending pairing belongs to nobody,
            // and is offered to whoever is signed in on the phone that scans it.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('token', 32)->unique();

            // The four characters printed under the QR and read back off the
            // other screen. What stops a link phished from somewhere else: there
            // is no second screen to check it against.
            $table->string('confirmation_code', 4);

            // The browser that asked for the code, and the only one allowed to
            // claim it. A token that leaks is useless in any other session.
            $table->string('requesting_session_id')->index();

            // That browser's session once it is signed in — what "log this device
            // out" ends. Null while the pairing is still an invitation.
            $table->string('session_id')->nullable()->index();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // The token's own short life, and nothing to do with the session that
            // comes after it. Pushed out once when a phone scans, so the code
            // cannot rotate away while someone is looking at it.
            $table->timestamp('expires_at');

            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            // Refreshed at most once a minute while the paired device is used, so
            // the devices list can say which screen is still in the building.
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairings');
    }
};
