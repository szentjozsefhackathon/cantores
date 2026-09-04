<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 160 characters was never enough: a contact message alone may be 500, and
     * a rights complaint carries the claim and the reporter's address with it.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->text('message')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('message', 160)->change();
        });
    }
};
