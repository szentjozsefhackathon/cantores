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
        Schema::table('screens', function (Blueprint $table) {
            $table->foreignId('applied_presentation_id')
                ->nullable()
                ->constrained('presentations')
                ->nullOnDelete();
            $table->unsignedBigInteger('applied_version')->default(0);
            $table->string('drawn_revision')->nullable();
            $table->timestamp('applied_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applied_presentation_id');
            $table->dropColumn(['applied_version', 'drawn_revision', 'applied_at']);
        });
    }
};
