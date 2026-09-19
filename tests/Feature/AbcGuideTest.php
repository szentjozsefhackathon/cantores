<?php

use App\Livewire\Pages\AbcGuide;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('guest can access the abc guide page', function () {
    get(route('abc.guide'))->assertSuccessful();
});

it('authenticated user can access the abc guide page', function () {
    $user = User::factory()->create();
    actingAs($user);

    get(route('abc.guide'))->assertSuccessful();
});

it('renders markdown sections and abc code blocks', function () {
    Livewire::test(AbcGuide::class)
        ->assertSee('ABC')
        ->assertSee('abcMiniEditor', escape: false);
});

it('parses sections into markdown and abc types', function () {
    $component = Livewire::test(AbcGuide::class);

    $sections = $component->get('sections');

    expect($sections)->not->toBeEmpty();

    $types = array_column($sections, 'type');
    expect($types)->toContain('markdown');
    expect($types)->toContain('abc');
});
