<?php

use App\Models\Genre;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function attachAssignment(MusicPlan $plan, MusicPlanSlot $slot, Music $music): MusicPlanSlotAssignment
{
    $plan->slots()->attach($slot->id, ['sequence' => 1]);

    $pivot = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $plan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->first();

    return MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_plan_id' => $plan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);
}

test('extended card renders the plan slot, music and assignment count', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    $slot = MusicPlanSlot::factory()->create(['name' => 'Kezdőének']);
    $music = Music::factory()->create(['user_id' => $owner->id, 'title' => 'Téged Isten dicsérünk']);

    attachAssignment($plan, $slot, $music);

    Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan])
        ->assertSet('assignmentCount', 1)
        ->assertSee('Kezdőének')
        ->assertSee('Téged Isten dicsérünk');
});

test('assignment count reflects loaded data without a separate query on render', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $slot = MusicPlanSlot::factory()->create();

    attachAssignment($plan, $slot, Music::factory()->create(['user_id' => $owner->id]));
    attachAssignment($plan, MusicPlanSlot::factory()->create(), Music::factory()->create(['user_id' => $owner->id]));

    Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan])
        ->assertSet('assignmentCount', 2);
});

test('card eager loads scores so incipit lookups do not scale per music', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);

    foreach (range(1, 4) as $i) {
        $music = Music::factory()->create(['user_id' => $owner->id]);
        \App\Models\Score::factory()->create([
            'user_id' => $owner->id,
            'music_id' => $music->id,
            'public_preview' => true,
        ]);
        attachAssignment($plan, MusicPlanSlot::factory()->create(), $music);
    }

    DB::enableQueryLog();

    Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan])
        ->assertSet('assignmentCount', 4);

    $scoreQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], '"scores"'))
        ->count();

    DB::disableQueryLog();

    expect($scoreQueries)->toBeLessThanOrEqual(2);
});

test('genre-icon renders the organist icon for an organist genre as plain markup', function () {
    $genre = Genre::firstOrCreate(['name' => 'organist']);

    $html = Blade::render('<x-genre-icon :genre-id="$genreId" />', ['genreId' => $genre->id]);

    expect($html)
        ->toContain('data-flux-icon')
        ->toContain('viewBox="0 0 390 600"') // the custom organist icon
        ->not->toContain('wire:id') // proves it is no longer a nested Livewire component
        ->not->toContain('wire:snapshot');
});

test('genre-icon falls back to the default icon without a genre', function () {
    $html = Blade::render('<x-genre-icon :genre-id="null" />');

    expect($html)
        ->toContain('data-flux-icon')
        ->not->toContain('viewBox="0 0 390 600"')
        ->not->toContain('wire:id');
});

test('card offers booklet creation to the owner when asked for booklet actions', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan, 'showBookletActions' => true])
        ->assertSee('Füzet készítése')
        ->assertSee(route('booklets.store'));
});

test('card links to an existing booklet of the plan', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $booklet = \App\Models\Booklet::factory()->create([
        'user_id' => $owner->id,
        'music_plan_id' => $plan->id,
    ]);

    Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan, 'showBookletActions' => true])
        ->assertSee('Füzet megnyitása')
        ->assertSee(route('booklets.edit', $booklet));
});

test('card hides booklet actions unless they are asked for', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan])
        ->assertDontSee('Füzet készítése');
});

test('booklet buttons on the card are square icon buttons at the same size as the view and edit ones', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);

    $html = Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan, 'showBookletActions' => true])
        ->html();

    $bookletButton = Str::after($html, '<form method="POST" action="'.route('booklets.store').'"');

    expect(Str::before($bookletButton, '</form>'))
        ->toContain('w-10')
        ->not->toContain('px-2')
        ->not->toContain('<span>'); // an empty label span would be a flex item and push the icon off centre
});

test('the create-projection button wears a plus icon the open-projection one does not', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    \App\Models\Projection::factory()->create([
        'user_id' => $owner->id,
        'music_plan_id' => $plan->id,
    ]);

    $html = Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan, 'showBookletActions' => true])
        ->html();

    $createButton = Str::before(Str::after($html, '<form method="POST" action="'.route('projections.store').'"'), '</form>');

    expect($createButton)->toContain('M12 6v7'); // the plus stroke of presentation-plus
    expect(Str::before($html, '<form method="POST" action="'.route('projections.store').'"'))
        ->toContain('m7 21 5-5 5 5') // the open-projection button is a plain presentation screen
        ->not->toContain('M12 6v7');
});

test('every action on the plan lives in a toolbar column down the card\'s right edge', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);

    $html = Livewire::actingAs($owner)
        ->test('music-plan-card-extended', ['musicPlan' => $plan, 'showBookletActions' => true])
        ->html();

    $toolbar = Str::after($html, 'flex flex-col items-center');

    expect($toolbar)
        ->toContain(route('music-plan-view', ['musicPlan' => $plan]))
        ->toContain(route('music-plan-editor', ['musicPlan' => $plan]))
        ->toContain(route('booklets.store'))
        ->toContain(route('projections.store'));

    // the toolbar is the card's last column, so nothing of the plan follows it
    expect(Str::before($html, 'flex flex-col items-center'))
        ->toContain($plan->celebration_name ?? '–')
        ->not->toContain(route('booklets.store'));
});
