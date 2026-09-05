<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booklet: the printed thing a music plan becomes, with its page geometry
     * and the one set of numbers every score in it is unified to.
     */
    public function up(): void
    {
        Schema::create('booklets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Where the score list came from. Nullable and nullOnDelete: a
            // booklet already printed for a service outlives the plan it was
            // built from, and keeps the scores it was given.
            $table->foreignId('music_plan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');

            // Page geometry, in millimetres — the whole booklet works in
            // physical units, because it puts scores engraved on different
            // nominal pages onto one real sheet.
            $table->string('page_size', 8)->default('a5');
            $table->string('orientation', 16)->default('portrait');
            $table->float('margin_mm')->default(12);

            // The unifiers. Lyric size is the primary one: it is the only
            // setting all four formats have, and the one the eye compares
            // between an engraved score and a lyrics-only sheet. Staff height
            // is secondary, and means nothing to ChordPro.
            $table->float('lyric_size_pt')->default(11);
            $table->float('staff_height_mm')->default(7);

            $table->boolean('show_titles')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booklets');
    }
};
