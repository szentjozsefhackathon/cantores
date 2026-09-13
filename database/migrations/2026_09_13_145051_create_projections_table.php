<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A projection: the scores of one service, collected for the screen the
     * congregation reads from.
     *
     * The sibling of a booklet, and deliberately thinner than one. A booklet
     * carries a page geometry and the numbers every score in it is unified to,
     * because it puts scores engraved for different nominal pages onto one real
     * sheet. A projection unifies almost nothing: the author of each score has
     * already tuned it for 16:9, 4:3 and 1:1 in the score editor, against the
     * very canvas this will engrave it onto, so the deck's job is to choose the
     * shape and stay out of the way.
     */
    public function up(): void
    {
        Schema::create('projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Where the score list came from. Nullable and nullOnDelete: a deck
            // already projected at a service outlives the plan it was built
            // from, and keeps the scores it was given.
            $table->foreignId('music_plan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');

            // The shape of the screen, and the whole of this deck's geometry.
            // Everything else about how a slide looks is the score's own
            // per-ratio layout, or the one override the slide carries.
            $table->string('ratio', 8)->default('16/9');

            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projections');
    }
};
