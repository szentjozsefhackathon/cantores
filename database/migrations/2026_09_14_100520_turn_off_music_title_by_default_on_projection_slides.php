<?php

use App\Models\ProjectionSlide;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A congregation is sung to, not read to.
     *
     * Everything else a slide can name — the slot, the variation, the
     * collections — is off unless someone asks for it, and the music's own name
     * belongs beside them, not printed by default the way a booklet prints it.
     * The row's switch is how a name is put on the screen rather than how it is
     * kept off.
     */
    public function up(): void
    {
        Schema::table('projection_slides', function (Blueprint $table): void {
            $table->boolean('show_music_title')->default(false)->change();
        });

        ProjectionSlide::query()->update(['show_music_title' => false]);
    }

    public function down(): void
    {
        Schema::table('projection_slides', function (Blueprint $table): void {
            $table->boolean('show_music_title')->default(true)->change();
        });
    }
};
