<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scores a folder or plan loan deliberately leaves out.
 *
 * Stored as exclusions rather than inclusions so that an empty set means
 * everything: a musician at a service who cannot open a score because of a
 * forgotten tick is worse than one who sees a half-finished arrangement, and a
 * score added to the folder later is included without anyone revisiting the loan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_score_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['loan_id', 'score_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_score_exclusions');
    }
};
