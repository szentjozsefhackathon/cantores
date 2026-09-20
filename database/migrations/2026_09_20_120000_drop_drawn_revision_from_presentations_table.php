<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The deck a wall has finished engraving belongs to the wall, not to the show.
 *
 * Both rows carried the column for a while, and only one of them was ever
 * written: the browsers report what they have drawn to `screens.drawn_revision`
 * through the acknowledgement, which is the one that can answer the question
 * per wall — a parish with two beamers has two answers and a show has one row.
 * What was left here was a column no client wrote, reported in the state answer
 * as a null the remote then fell back to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table): void {
            $table->dropColumn('drawn_revision');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table): void {
            $table->string('drawn_revision')->nullable();
        });
    }
};
