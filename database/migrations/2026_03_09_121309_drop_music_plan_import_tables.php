<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('music_imports');
        Schema::dropIfExists('music_plan_import_items');
        Schema::dropIfExists('slot_imports');
        Schema::dropIfExists('music_plan_imports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
