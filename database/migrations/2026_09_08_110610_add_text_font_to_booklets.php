<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The booklet's own typography: the face it speaks in, and how loud.
     *
     * Everything the booklet writes rather than engraves — the heading above a
     * score, a rubric between two of them, the page number — was set in Inter at
     * the lyric size, both of which were decisions taken in a constant. They are
     * the booklet's to make: a booklet set in Garamond wants its headings in
     * Garamond, and a heading at the full lyric size shouts on a small page.
     */
    public function up(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->string('text_font', 64)->default('Inter');
            $table->float('heading_scale')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->dropColumn(['text_font', 'heading_scale']);
        });
    }
};
