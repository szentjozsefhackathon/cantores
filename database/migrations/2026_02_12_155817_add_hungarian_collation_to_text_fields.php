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
        // Define columns to update with their attributes
        $columns = [
            'musics' => [
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
            ],
            'collections' => [
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
                ['name' => 'abbreviation', 'type' => 'string', 'nullable' => true],
                ['name' => 'author', 'type' => 'string', 'nullable' => true],
            ],
            'music_plan_slots' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => true],
                ['name' => 'description', 'type' => 'text', 'nullable' => true],
            ],
            'music_plan_templates' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => true],
                ['name' => 'description', 'type' => 'text', 'nullable' => true],
            ],
            'cities' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ],
            'first_names' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ],
            'music_urls' => [
                ['name' => 'label', 'type' => 'string', 'nullable' => true],
            ],
            'music_plans' => [
                ['name' => 'celebration_name', 'type' => 'string', 'nullable' => true],
                ['name' => 'setting', 'type' => 'string', 'nullable' => true],
                ['name' => 'season_text', 'type' => 'string', 'nullable' => true],
            ],
            'users' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ],
        ];

        foreach ($columns as $table => $columnDefs) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($columnDefs) {
                foreach ($columnDefs as $def) {
                    if ($def['type'] === 'string') {
                        $column = $tableBlueprint->string($def['name'], 255);
                    } else {
                        $column = $tableBlueprint->text($def['name']);
                    }
                    if ($def['nullable']) {
                        $column->nullable();
                    } else {
                        $column->nullable(false);
                    }
                    $column->collation('hu-HU-x-icu')->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert collation to default (database default)
        $columns = [
            'musics' => [
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
            ],
            'collections' => [
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
                ['name' => 'abbreviation', 'type' => 'string', 'nullable' => true],
                ['name' => 'author', 'type' => 'string', 'nullable' => true],
            ],
            'music_plan_slots' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => true],
                ['name' => 'description', 'type' => 'text', 'nullable' => true],
            ],
            'music_plan_templates' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => true],
                ['name' => 'description', 'type' => 'text', 'nullable' => true],
            ],
            'cities' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ],
            'first_names' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ],
            'music_urls' => [
                ['name' => 'label', 'type' => 'string', 'nullable' => true],
            ],
            'music_plans' => [
                ['name' => 'celebration_name', 'type' => 'string', 'nullable' => true],
                ['name' => 'setting', 'type' => 'string', 'nullable' => true],
                ['name' => 'season_text', 'type' => 'string', 'nullable' => true],
            ],
            'users' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => false],
            ],
        ];

        foreach ($columns as $table => $columnDefs) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($columnDefs) {
                foreach ($columnDefs as $def) {
                    if ($def['type'] === 'string') {
                        $column = $tableBlueprint->string($def['name'], 255);
                    } else {
                        $column = $tableBlueprint->text($def['name']);
                    }
                    if ($def['nullable']) {
                        $column->nullable();
                    } else {
                        $column->nullable(false);
                    }
                    $column->collation('default')->change();
                }
            });
        }
    }
};
