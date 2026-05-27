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

it('abc sections contain complete examples', function () {
    $component = Livewire::test(AbcGuide::class);
    $sections = $component->get('sections');

    $abcSections = array_filter($sections, fn (array $section): bool => $section['type'] === 'abc');

    expect($abcSections)->not->toBeEmpty();

    foreach ($abcSections as $section) {
        expect($section['content'])
            ->toContain('K:')
            ->toContain('|');
    }
});

it('links the score editor abc guide button to the local guide', function () {
    get(route('score.preview'))
        ->assertSuccessful()
        ->assertSee(route('abc.guide', absolute: false), escape: false)
        ->assertDontSee('abcplus.sourceforge.net', escape: false);
});

it('documents abc pitch and duration without confusing c2 for an octave', function () {
    $guide = (string) file_get_contents(base_path('docs/abc-felhasznaloi-utmutato.md'));
    $cheatsheet = (string) file_get_contents(base_path('docs/abc-cheatsheet.md'));

    expect($guide)
        ->toContain('c2`-t oktávnak gondolod')
        ->toContain('oktávhoz `c\'`, hosszhoz `c2` kell')
        ->toContain('## 1. A kotta váza')
        ->not->toContain('Mire való az ABC?')
        ->not->toContain('modernizált metzigót')
        ->not->toContain('ABC 2.2 leírás');

    expect($cheatsheet)
        ->toContain("| `c' d'` | kétvonalas oktáv")
        ->not->toContain('`c2 d2`| kétvonalas oktáv');
});
