<?php

use App\Enums\MusicTagType;
use App\Models\Music;
use App\Models\MusicTag;
use App\Models\User;

test('music tag can be created with name and type', function () {
    $tag = MusicTag::create([
        'name' => 'Introitus',
        'type' => MusicTagType::PartOfMass->value,
    ]);

    expect($tag->id)->not->toBeNull();
    expect($tag->name)->toBe('Introitus');
    expect($tag->type)->toBe(MusicTagType::PartOfMass);
});

test('music tag has correct icon and color for type', function () {
    $tag = MusicTag::create([
        'name' => 'Organ',
        'type' => MusicTagType::Instrument->value,
    ]);

    expect($tag->icon())->toBe('music');
    expect($tag->color())->toBe('blue');
    expect($tag->typeLabel())->toBe('Instrument');
});

test('music can have multiple tags', function () {
    $music = Music::factory()->create();
    $tag1 = MusicTag::create([
        'name' => 'Introitus',
        'type' => MusicTagType::PartOfMass->value,
    ]);
    $tag2 = MusicTag::create([
        'name' => 'Organ',
        'type' => MusicTagType::Instrument->value,
    ]);

    $music->tags()->attach([$tag1->id, $tag2->id]);

    expect($music->tags)->toHaveCount(2);
    expect($music->tags->pluck('id'))->toContain($tag1->id, $tag2->id);
});

test('tag can be removed from music', function () {
    $music = Music::factory()->create();
    $tag = MusicTag::create([
        'name' => 'Advent',
        'type' => MusicTagType::Season->value,
    ]);

    $music->tags()->attach($tag->id);
    expect($music->tags)->toHaveCount(1);

    $music->tags()->detach($tag->id);
    $music->refresh();
    expect($music->tags)->toHaveCount(0);
});

test('duplicate tags with same name and type cannot be created', function () {
    MusicTag::create([
        'name' => 'Introitus',
        'type' => MusicTagType::PartOfMass->value,
    ]);

    expect(function () {
        MusicTag::create([
            'name' => 'Introitus',
            'type' => MusicTagType::PartOfMass->value,
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('editor can create music tags', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    expect($editor->can('create', MusicTag::class))->toBeTrue();
});

test('non-editor cannot create music tags', function () {
    $user = User::factory()->create();

    expect($user->can('create', MusicTag::class))->toBeFalse();
});

test('editor can update music tags', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $tag = MusicTag::create([
        'name' => 'Introitus',
        'type' => MusicTagType::PartOfMass->value,
    ]);

    expect($editor->can('update', $tag))->toBeTrue();
});

test('editor can delete music tags', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $tag = MusicTag::create([
        'name' => 'Introitus',
        'type' => MusicTagType::PartOfMass->value,
    ]);

    expect($editor->can('delete', $tag))->toBeTrue();
});

test('music tag has correct label', function () {
    $tag = MusicTag::create([
        'name' => 'Introitus',
        'type' => MusicTagType::PartOfMass->value,
    ]);

    expect($tag->label())->toBe('Introitus');
});

test('all music tag types have icons and colors', function () {
    foreach (MusicTagType::cases() as $type) {
        expect($type->icon())->not->toBeEmpty();
        expect($type->color())->not->toBeEmpty();
        expect($type->label())->not->toBeEmpty();
    }
});
