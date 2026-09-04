<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('score_rights_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_id')->constrained()->cascadeOnDelete();
            // Kept when the publication row is gone, so the complaint survives
            // the score leaving the library.
            $table->foreignId('score_publication_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 16);
            $table->string('capacity', 32);
            $table->text('claim');

            // A rights holder rarely has an account here, so the reporter is
            // identified by what they typed rather than by a user row.
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name');
            $table->string('reporter_email');

            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['score_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_rights_reports');
    }
};
