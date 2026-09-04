<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which of a score's files go out with its publication. A score often keeps
     * the editable source beside the PDFs cut for different paper, and may also
     * keep a bought reference copy that must stay behind — so this is per file
     * rather than per score.
     */
    public function up(): void
    {
        Schema::table('score_files', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('rights');

            $table->index(['score_id', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('score_files', function (Blueprint $table) {
            $table->dropIndex(['score_id', 'is_published']);
            $table->dropColumn('is_published');
        });
    }
};
