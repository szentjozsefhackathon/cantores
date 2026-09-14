<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How large the words a document says rather than sings are set, and how far
 * apart their lines stand.
 *
 * A rubric has had no settings of its own in either document. In a booklet it
 * took the lyric size and a line height nailed into the renderer; on a screen it
 * took a fifteenth of the slide's height and the same nailed leading, which
 * nobody could move at all. Both are the wrong default often enough to matter —
 * a long instruction wants to be smaller than the music around it, and eight
 * words thrown on a wall want to be larger than a fifteenth of the screen.
 *
 * Stated as a factor of what the document already computes rather than as a size
 * in points, for the reason the heading scale is: the base size answers to the
 * page or the screen, and a rubric is asking to be a little bigger or a little
 * smaller than that, not to be sixteen points wherever it lands. So every
 * existing document keeps exactly the type it has today.
 *
 * The line height is the leading the renderer already drew at, now written down
 * where somebody can move it. Headings stay relative, as they were: their size
 * is a share of the base and their air is a share of their own size, so both
 * follow this without a setting of their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booklets', function (Blueprint $table): void {
            $table->float('text_size_scale')->default(1.0)->after('heading_scale');
            $table->float('text_line_height')->default(1.45)->after('text_size_scale');
        });

        Schema::table('projections', function (Blueprint $table): void {
            $table->float('text_size_scale')->default(1.0)->after('text_theme');
            $table->float('text_line_height')->default(1.45)->after('text_size_scale');
        });
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table): void {
            $table->dropColumn(['text_size_scale', 'text_line_height']);
        });

        Schema::table('projections', function (Blueprint $table): void {
            $table->dropColumn(['text_size_scale', 'text_line_height']);
        });
    }
};
