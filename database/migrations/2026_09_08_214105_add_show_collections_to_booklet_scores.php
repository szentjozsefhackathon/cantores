<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the music can be looked up, printed beside its name.
     *
     * A congregation holds books as well as this booklet, and the number the
     * music has in them is what lets someone follow it there instead. But a
     * booklet is printed because those books are not enough, so the numbers are
     * said only when they are asked for — and, like the slot's name and the
     * music's, the answer is kept on whichever row opens the music.
     */
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->boolean('show_collections')->default(false)->after('show_music_title');
        });
    }

    public function down(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropColumn('show_collections');
        });
    }
};
