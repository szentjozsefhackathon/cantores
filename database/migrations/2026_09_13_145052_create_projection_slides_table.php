<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What stands in a projection, in order.
     *
     * One row is one score — not one slide: a score cut by `%pagebreak169` into
     * three screens is still one thing chosen from the plan, and putting the
     * three on separate rows would make the editor lie about what was chosen.
     * The cutting happens in the browser, at render time, from the score's own
     * source, so a break moved on Thursday is a different slide on Sunday.
     *
     * A row may carry no score at all: a few words set on a screen of their own
     * between the music.
     *
     * The overrides live here rather than on the score for the same two reasons
     * a booklet's do — the score may not be the deck owner's to edit, and the
     * same score in next month's deck at another ratio needs other numbers — so
     * a projection never writes back to a score.
     */
    public function up(): void
    {
        Schema::create('projection_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_id')->nullable()->constrained()->cascadeOnDelete();

            // Which of a score's uploaded files is projected. Null means the
            // score's own default, the oldest it holds.
            $table->foreignId('score_file_id')->nullable()->constrained()->nullOnDelete();

            // Where in the plan this was chosen from. Both nullable: a score put
            // in from outside the plan belongs to no slot, and a deck may have
            // no plan behind it at all.
            $table->foreignId('music_plan_slot_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('music_plan_slot_plan_id')->nullable()->constrained('music_plan_slot_plan')->nullOnDelete();

            // Words rather than music, in Markdown, when there is no score.
            $table->text('text')->nullable();

            $table->unsignedInteger('sequence')->default(0);

            // A sparse bucket in the same shape and the same keys as
            // scores.settings, holding only what someone changed by hand.
            // Everything absent falls through to the score's own layout for this
            // ratio, and then to the format's screen defaults. Validated against
            // App\Support\ProjectionSettingFields before it is written.
            $table->json('settings_override')->nullable();

            // What is named on the slide. A congregation is being sung to rather
            // than read to, so a projection announces much less than a booklet:
            // the slot and the collections are off unless someone asks for them.
            $table->boolean('show_slot')->default(false);
            $table->boolean('show_music_title')->default(true);
            $table->boolean('show_variation')->default(false);
            $table->boolean('show_collections')->default(false);

            $table->timestamps();

            $table->index(['projection_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_slides');
    }
};
