<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The scores in a booklet, in order, each with whatever had to be adjusted
     * to make it sit well on the page.
     *
     * The overrides live here rather than on the score for two reasons: the
     * score may not be the booklet owner's to edit at all (it can be borrowed or
     * come from the library), and the same score in next month's booklet at a
     * different page size needs different numbers. So a booklet never writes
     * back to a score.
     */
    public function up(): void
    {
        Schema::create('booklet_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booklet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('sequence')->default(0);

            // A sparse bucket of render settings, in the same shape and using
            // the same keys as scores.settings, holding only what the person
            // changed by hand. Everything absent falls through to the booklet's
            // unified values and then to the score's own. Validated against
            // App\Support\BookletSettingFields before it is written.
            $table->json('settings_override')->nullable();

            $table->boolean('start_on_new_page')->default(false);

            $table->timestamps();

            $table->unique(['booklet_id', 'score_id']);
            $table->index(['booklet_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booklet_scores');
    }
};
