<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A score keeps several files side by side — the editable source and the
     * printable PDFs cut for different paper sizes — so each one needs a name
     * of its own to be told apart from its siblings.
     */
    public function up(): void
    {
        Schema::table('score_files', function (Blueprint $table) {
            $table->string('label')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('score_files', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
