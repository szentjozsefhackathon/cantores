<?php

use App\Models\Author;
use App\Models\Music;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

it('renders the music card blade component with its core fields', function () {
    $this->actingAs(User::factory()->create());

    $author = Author::factory()->create(['name' => 'Test Composer']);
    $music = Music::factory()->create([
        'title' => 'Veni Creator',
        'subtitle' => 'Hymn',
        'custom_id' => 'KEK-123',
    ]);
    $music->authors()->attach($author);

    $html = Blade::render('<x-music.card :music="$music" />', ['music' => $music->fresh()]);

    expect($html)
        ->toContain('Veni Creator')
        ->toContain('Hymn')
        ->toContain('KEK-123')
        ->toContain('Test Composer');
});

it('renders the music card blade component with a relevance score', function () {
    $this->actingAs(User::factory()->create());

    $music = Music::factory()->create(['title' => 'Adoro Te']);

    $html = Blade::render(
        '<x-music.card :music="$music" :score="15" :score-reasons="$reasons" />',
        [
            'music' => $music->fresh(),
            'reasons' => [
                ['label' => __('Same readings'), 'points' => 5],
            ],
        ]
    );

    expect($html)
        ->toContain(__('Why this relevance score?'))
        ->toContain(__('Same readings'))
        ->toContain('+5');
});
