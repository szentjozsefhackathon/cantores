<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booklet holds more than scores.
     *
     * Two things arrive here at once, and they are the same change: a row is no
     * longer necessarily a score. It may be a paragraph of instructions the
     * congregation reads instead of sings, which is why score_id becomes
     * nullable and `text` appears beside it.
     *
     * The rest is about what is printed above a score. A booklet names the
     * moment in the service rather than the engraving, so the row remembers
     * which slot assignment it was chosen from and the heading is resolved from
     * the plan at render time — a slot renamed in the plan is renamed in the
     * booklet. The score's own variation name is off unless someone asks for it
     * on that row.
     */
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->unsignedBigInteger('score_id')->nullable()->change();

            $table->foreignId('music_plan_slot_assignment_id')
                ->nullable()
                ->after('score_id')
                ->constrained()
                ->nullOnDelete();

            $table->text('text')->nullable()->after('music_plan_slot_assignment_id');

            $table->boolean('show_variation')->default(false)->after('start_on_new_page');
        });
    }

    public function down(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('music_plan_slot_assignment_id');
            $table->dropColumn(['text', 'show_variation']);
        });

        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->unsignedBigInteger('score_id')->nullable(false)->change();
        });
    }
};
