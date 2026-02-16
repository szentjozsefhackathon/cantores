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
        // Drop foreign key constraints before renaming columns
        Schema::table('music_realm', function (Blueprint $table) {
            $table->dropForeign(['realm_id']);
        });

        Schema::table('collection_realm', function (Blueprint $table) {
            $table->dropForeign(['realm_id']);
        });

        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropForeign(['realm_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_realm_id']);
        });

        // Rename tables
        Schema::rename('realms', 'genres');
        Schema::rename('music_realm', 'music_genre');
        Schema::rename('collection_realm', 'collection_genre');

        // Rename foreign key columns
        Schema::table('music_genre', function (Blueprint $table) {
            $table->renameColumn('realm_id', 'genre_id');
        });

        Schema::table('collection_genre', function (Blueprint $table) {
            $table->renameColumn('realm_id', 'genre_id');
        });

        Schema::table('music_plans', function (Blueprint $table) {
            $table->renameColumn('realm_id', 'genre_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('current_realm_id', 'current_genre_id');
        });

        // Recreate foreign key constraints with new column names
        Schema::table('music_genre', function (Blueprint $table) {
            $table->foreign('genre_id')->references('id')->on('genres')->cascadeOnDelete();
        });

        Schema::table('collection_genre', function (Blueprint $table) {
            $table->foreign('genre_id')->references('id')->on('genres')->cascadeOnDelete();
        });

        Schema::table('music_plans', function (Blueprint $table) {
            $table->foreign('genre_id')->references('id')->on('genres')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_genre_id')->references('id')->on('genres')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints before renaming back
        Schema::table('music_genre', function (Blueprint $table) {
            $table->dropForeign(['genre_id']);
        });

        Schema::table('collection_genre', function (Blueprint $table) {
            $table->dropForeign(['genre_id']);
        });

        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropForeign(['genre_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_genre_id']);
        });

        // Rename columns back
        Schema::table('music_genre', function (Blueprint $table) {
            $table->renameColumn('genre_id', 'realm_id');
        });

        Schema::table('collection_genre', function (Blueprint $table) {
            $table->renameColumn('genre_id', 'realm_id');
        });

        Schema::table('music_plans', function (Blueprint $table) {
            $table->renameColumn('genre_id', 'realm_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('current_genre_id', 'current_realm_id');
        });

        // Rename tables back
        Schema::rename('collection_genre', 'collection_realm');
        Schema::rename('music_genre', 'music_realm');
        Schema::rename('genres', 'realms');

        // Recreate foreign key constraints with old column names
        Schema::table('music_realm', function (Blueprint $table) {
            $table->foreign('realm_id')->references('id')->on('realms')->cascadeOnDelete();
        });

        Schema::table('collection_realm', function (Blueprint $table) {
            $table->foreign('realm_id')->references('id')->on('realms')->cascadeOnDelete();
        });

        Schema::table('music_plans', function (Blueprint $table) {
            $table->foreign('realm_id')->references('id')->on('realms')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_realm_id')->references('id')->on('realms')->nullOnDelete();
        });
    }
};
