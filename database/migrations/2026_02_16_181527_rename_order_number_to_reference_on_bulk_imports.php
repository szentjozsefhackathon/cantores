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
            // Drop the existing index first
            $table->dropIndex(['collection', 'order_number']);

            // Rename the column
            $table->renameColumn('order_number', 'reference');

            // Change the column type from unsignedInteger to string
            $table->string('reference')->nullable(false)->change();

            // Recreate the index with the new column name
            $table->index(['collection', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_imports', function (Blueprint $table) {
            // Drop the new index
            $table->dropIndex(['collection', 'reference']);

            // Change the column type back to unsignedInteger
            $table->unsignedInteger('reference')->nullable(false)->change();

            // Rename back to order_number
            $table->renameColumn('reference', 'order_number');

            // Recreate the original index
            $table->index(['collection', 'order_number']);
        });
    }
};
