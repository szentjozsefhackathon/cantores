<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the score's uploaded files this row prints.
     *
     * A score may hold several — the editable source beside the PDF, a projection
     * slide beside the accompaniment — and until now a booklet could only ever
     * reach the one uploaded first. The file is what is chosen, so the row says
     * which one.
     *
     * Null keeps meaning what it has always meant: the score's own default, the
     * oldest file. That is what every existing row is, so nothing needs
     * backfilling, and a score written in the editor rather than uploaded has no
     * file to name at all.
     */
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->foreignId('score_file_id')->nullable()->after('score_id')
                ->constrained()->nullOnDelete();

            $table->dropUnique('booklet_scores_booklet_id_score_id_unique');
        });

        // A booklet may now hold one score twice, once per file, so the same
        // score is only a duplicate when it is the same file — and a row naming
        // no file is the score's default, one particular file, which is why the
        // absent id is folded into a value rather than left to compare unequal
        // to itself.
        DB::statement('create unique index booklet_scores_booklet_id_score_id_file_id_unique
            on booklet_scores (booklet_id, score_id, coalesce(score_file_id, 0))');
    }

    public function down(): void
    {
        DB::statement('drop index if exists booklet_scores_booklet_id_score_id_file_id_unique');

        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('score_file_id');
            $table->unique(['booklet_id', 'score_id']);
        });
    }
};
