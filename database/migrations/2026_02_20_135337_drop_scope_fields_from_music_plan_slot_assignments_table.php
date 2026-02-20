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
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropColumn(['scope_number', 'scope_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->integer('scope_number')->nullable();
            $table->string('scope_type', 50)->nullable();
        });
    }
};
