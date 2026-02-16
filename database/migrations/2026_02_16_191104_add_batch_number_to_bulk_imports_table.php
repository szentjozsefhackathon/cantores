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
        Schema::table('bulk_imports', function (Blueprint $table) {
            $table->unsignedInteger('batch_number')->nullable(false)->default(1);
            $table->index('batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_imports', function (Blueprint $table) {
            $table->dropIndex(['batch_number']);
            $table->dropColumn('batch_number');
        });
    }
};
