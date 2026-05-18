<?php

use App\Livewire\Pages\AretinoGuide;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('guest can access the aretino guide page', function () {
    get(route('aretino.guide'))->assertSuccessful();
});

it('authenticated user can access the aretino guide page', function () {
    $user = User::factory()->create();
    actingAs($user);

    get(route('aretino.guide'))->assertSuccessful();
});

it('renders markdown sections and aretino code blocks', function () {
    Livewire::test(AretinoGuide::class)
        ->assertSee('Aretino')
        ->assertSee('aretinoMiniEditor', escape: false);
});

it('parses sections into markdown and aretino types', function () {
    $component = Livewire::test(AretinoGuide::class);

    $sections = $component->get('sections');

    expect($sections)->not->toBeEmpty();

    $types = array_column($sections, 'type');
    expect($types)->toContain('markdown');
    expect($types)->toContain('aretino');
});

it('aretino sections contain valid content', function () {
    $component = Livewire::test(AretinoGuide::class);
    $sections = $component->get('sections');

    $aretinoSections = array_filter($sections, fn ($s) => $s['type'] === 'aretino');

    foreach ($aretinoSections as $section) {
        expect($section['content'])->not->toBeEmpty();
    }
});

