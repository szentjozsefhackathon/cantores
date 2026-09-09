<?php

use App\Models\Booklet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The booklet's face, now that it is the face of the music too.
     *
     * Until now `text_font` dressed only what the booklet writes — headings,
     * rubrics, page numbers — while every score kept whatever its author had
     * engraved it in, so an interface face was a defensible default. It is not
     * one for a whole printed booklet: Inter is a screen face, and the lyrics
     * under the staves are now set in it as well.
     *
     * Booklets still sitting on the old default are moved with it rather than
     * left behind, because nobody chose Inter — it is one day old and was never
     * anything but what the column happened to say. A booklet that wants it back
     * says so in its own font select.
     */
    public function up(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->string('text_font', 64)->default('EB Garamond')->change();
        });

        Booklet::query()->where('text_font', 'Inter')->update(['text_font' => 'EB Garamond']);
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->string('text_font', 64)->default('Inter')->change();
        });
    }
};
