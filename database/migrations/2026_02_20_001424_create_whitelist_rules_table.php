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
        Schema::create('whitelist_rules', function (Blueprint $table) {
            $table->id();
            $table->string('hostname');
            $table->string('path_prefix')->default('/');
            $table->string('scheme')->default('https');
            $table->boolean('allow_any_port')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hostname', 'path_prefix', 'scheme']);
            $table->index('hostname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whitelist_rules');
    }
};
