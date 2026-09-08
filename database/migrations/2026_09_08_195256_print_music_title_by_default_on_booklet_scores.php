<?php

use App\Models\BookletScore;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The music's name belongs in the booklet.
     *
     * Whoever holds it is looking for the moment, but they sing the music, and a
     * page that names only the moment leaves them to recognise the song by its
     * first line. So the name is printed unless a row is told otherwise — beside
     * the slot where the slot holds one music, above the music where it holds
     * several — and the row's switch is now how a name is kept off the page
     * rather than how it is asked for.
     */
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->boolean('show_music_title')->default(true)->change();
        });

        BookletScore::query()->update(['show_music_title' => true]);
    }

    public function down(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->boolean('show_music_title')->default(false)->change();
        });
    }
};
