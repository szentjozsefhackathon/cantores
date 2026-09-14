<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a person calls one of their devices, and whether they want it
     * offered as a screen at all.
     *
     * Keyed by the person as well as the device, because the name is not a
     * property of the machine. *For me* that laptop is the parish laptop; what
     * it is for whoever else signs in on it is a different answer, and there is
     * no reason for one to overwrite the other.
     *
     * Its own table rather than columns on `screens`, and that is the whole
     * point of it: a screen row is a browser that is here now, and it goes stale
     * five minutes after a laptop is closed. The name has to outlive it, so that
     * the laptop coming back next Sunday under a new session is already called
     * what it was called — and so that a screen can be named from the phone,
     * with nobody standing at the laptop at all.
     */
    public function up(): void
    {
        Schema::create('device_names', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The browser's own id, minted into a cookie that outlives its
            // session. Not a foreign key: the device is remembered whether or
            // not it is currently standing anywhere as a screen.
            $table->uuid('device_id');

            // What this person calls it. Null is the ordinary case — most
            // devices are never named, and then the user-agent description
            // answers as it always did.
            $table->string('name', 40)->nullable();

            // Whether to offer it as somewhere a deck can be sent. The laptop at
            // home is signed in as the same person, describes itself with the
            // same string, and is genuinely live while its tab is open; no
            // heuristic tells it from the one in the church, and the person who
            // owns both can say so once and be right forever.
            $table->boolean('offered')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_names');
    }
};
