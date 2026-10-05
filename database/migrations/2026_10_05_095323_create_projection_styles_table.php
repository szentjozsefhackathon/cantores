<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named slide style: one screen in one church, and how everything a deck puts
 * on it is set.
 *
 * Owned by a cantor rather than by a deck, so the style tuned once for the big
 * 4:3 screen of one parish is the style every deck for that parish is shown in,
 * and editing it moves all of them. See App\Models\ProjectionStyle.
 *
 * `settings` holds what the style says per format, in the keys and units the
 * score's own per-ratio settings use; a key it does not hold is the format's
 * factory default for the style's ratio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projection_styles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('ratio', 8)->default('16/9');
            $table->json('settings')->nullable();
            $table->string('text_theme', 16)->default('dark');
            $table->float('text_size_scale')->default(1);
            $table->float('text_line_height')->default(1.45);
            // How far a slide may be set smaller to save a slide, as a factor.
            $table->float('min_scale')->default(0.85);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_styles');
    }
};
