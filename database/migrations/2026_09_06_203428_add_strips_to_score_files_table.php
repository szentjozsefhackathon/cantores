<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The systems found on each rendered page.
     *
     * A file-backed score enters a booklet as its systems rather than as its
     * pages, because a MuseScore export is usually three or four systems on a
     * mostly empty sheet. The strips themselves are images beside the page
     * renders; this column is the index of them — which page each came from, in
     * what order, and how big it is, so the booklet can lay one out before it
     * has fetched it.
     *
     * Null means the file predates banding or has not been rendered since; it
     * is not the same as an empty list, which means a rendered page held no ink.
     */
    public function up(): void
    {
        Schema::table('score_files', function (Blueprint $table) {
            $table->json('strips')->nullable()->after('page_count');
        });
    }

    public function down(): void
    {
        Schema::table('score_files', function (Blueprint $table) {
            $table->dropColumn('strips');
        });
    }
};
