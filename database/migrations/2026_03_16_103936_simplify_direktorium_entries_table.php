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
        Schema::dropIfExists('direktorium_entries');

        Schema::create('direktorium_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direktorium_edition_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->mediumText('markdown_text');
            $table->unsignedSmallInteger('pdf_page_start')->nullable();
            $table->unsignedSmallInteger('pdf_page_end')->nullable();
            $table->text('raw_ai_response')->nullable();
            $table->timestamps();

            $table->index(['direktorium_edition_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direktorium_entries');
    }
};
