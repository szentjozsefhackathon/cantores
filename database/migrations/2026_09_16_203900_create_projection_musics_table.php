<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A music this deck holds and its plan does not.
     *
     * The song asked for a minute before Mass: it belongs to this occasion's
     * deck and to no published service, so it is written here rather than into
     * the plan. A row of its own rather than a music id on the slides, because
     * the music has to exist before any of its scores are chosen — it is added
     * first and its engraving is picked afterwards, like any other music's.
     */
    public function up(): void
    {
        Schema::create('projection_musics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projection_id')->constrained()->cascadeOnDelete();

            // Restricted, so that a music merger which forgot this table fails
            // loudly instead of quietly emptying a deck.
            $table->foreignId('music_id')->constrained('musics')->restrictOnDelete();

            // The slot it was added in. Null is between slots, and a slot
            // deleted from the plan leaves the music there rather than losing it.
            $table->foreignId('music_plan_slot_plan_id')->nullable()->constrained('music_plan_slot_plan')->nullOnDelete();

            // How many rows are printed before it — read only while the music
            // has no row of its own to stand on.
            $table->unsignedInteger('sequence')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_musics');
    }
};
