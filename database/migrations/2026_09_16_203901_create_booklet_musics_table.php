<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A music this booklet holds and its plan does not — the twin of
     * projection_musics, for the same reasons.
     */
    public function up(): void
    {
        Schema::create('booklet_musics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booklet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('music_id')->constrained('musics')->restrictOnDelete();
            $table->foreignId('music_plan_slot_plan_id')->nullable()->constrained('music_plan_slot_plan')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booklet_musics');
    }
};
