<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per person who opened a loan, and per score they chose to keep out of it.
 *
 * A bookmark and an open-log, never an authorization record: LoanAccessService
 * stays the only gate, so a row here can outlive the access it remembers without
 * granting anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();

            // Null keeps the whole loan; set keeps one score out of a folder or plan.
            $table->foreignId('score_id')->nullable()->constrained()->cascadeOnDelete();

            $table->timestamp('first_opened_at');
            $table->timestamp('last_opened_at');

            // Set only on a deliberate save, which is what the borrowed list reads.
            $table->timestamp('kept_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'loan_id', 'score_id']);
            $table->index(['loan_id', 'kept_at']);
        });

        // Postgres treats NULLs as distinct in a unique index, so the row that
        // records opening a loan as a whole needs an index of its own — otherwise
        // one person could accumulate a receipt per open.
        DB::statement(
            'CREATE UNIQUE INDEX received_loans_user_loan_whole_unique
             ON received_loans (user_id, loan_id) WHERE score_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('received_loans');
    }
};
