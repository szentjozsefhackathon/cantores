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
        Schema::create('music_plan_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_import_id')->constrained('music_plan_imports')->cascadeOnDelete();
            $table->date('celebration_date')->comment('Date of the celebration');
            $table->string('celebration_info')->nullable()->comment('Additional celebration info (e.g., "NAGYBÖJT II. VASÁRNAPJA")');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_plan_import_items');
    }
};
