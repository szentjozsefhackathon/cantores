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
        Schema::table('music_plans', function (Blueprint $table) {
            // Drop the old setting column
            $table->dropColumn('setting');
            // Add new realm_id foreign key
            $table->foreignId('realm_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropForeign(['realm_id']);
            $table->dropColumn('realm_id');
            $table->string('setting')->nullable();
        });
    }
};
