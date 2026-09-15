<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The front page has to say what this is before it says what it holds, so the
 * positioning is the page's own heading and the calendar below it is one tool
 * among several rather than the title of the site. The pitch behind that
 * heading stays folded: a guest arriving to look something up should reach the
 * tools without scrolling past an argument they did not ask for.
 */
it('leads with what the application is rather than what it holds', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('A saját liturgikus zenei műhelyed')
        ->assertSee('Énekrend, kotta, füzet és vetítés — ugyanabból az anyagból, egy helyen.')
        ->assertSee('Hogyan működik?');
});

it('keeps the full pitch folded away behind the heading', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('<details open', false)
        ->assertSee('Ami nincs készen, azt itt megcsinálod.');
});

it('offers a guest the three tools they can use without an account', function (string $route) {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route($route), false);
})->with(['music-database', 'music-plans', 'score.preview']);

it('keeps the tool row on one line by hiding the captions on a phone', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('hidden text-xs text-zinc-500 sm:block', false);
});

it('shortens the longest tool name so it survives a phone width', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Kottázás')
        ->assertSee('Kottaszerkesztő');
});

it('keeps the planner header together instead of spreading it to the far edge', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('flex flex-col gap-4 md:flex-row md:items-start', false)
        ->assertSee('flex flex-col items-center gap-2 md:items-start', false);
});

it('tells a first time visitor what to do next with the calendar and the plans', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Válassz egy napot, és másold át, ami tetszik.');
});

it('walks a first time visitor along the chain in working order', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder([
            'Összeállítod, mi hangzik el vasárnap',
            'A saját változatod: más hangnem',
            'Ugyanabból az anyagból nyomtatható füzet',
            'És ugyanabból a kivetített kép',
        ]);
});

it('offers a first time visitor both registration and the guide', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Kezdd el a saját műhelyed')
        ->assertSee('Nézd meg, mit tud')
        ->assertSee(route('register'), false);
});

it('puts the positioning above the tools and the calendar below them', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder([
            'A saját liturgikus zenei műhelyed',
            'Kottaszerkesztő',
            'Énekrendtervező',
        ]);
});

it('sends a signed in visitor to their dashboard instead of the front page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
