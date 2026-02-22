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
            $table->integer('page_number')->nullable()->after('reference');
            $table->string('tag')->nullable()->after('page_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_imports', function (Blueprint $table) {
            $table->dropColumn(['page_number', 'tag']);
        });
    }
};
