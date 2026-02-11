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
        Schema::table('music_plan_template_slots', function (Blueprint $table) {
            $table->renameColumn('template_id', 'music_plan_template_id');
            $table->renameColumn('slot_id', 'music_plan_slot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plan_template_slots', function (Blueprint $table) {
            $table->renameColumn('music_plan_template_id', 'template_id');
            $table->renameColumn('music_plan_slot_id', 'slot_id');
        });
    }
};
