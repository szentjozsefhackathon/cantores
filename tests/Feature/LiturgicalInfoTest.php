<?php

use App\Models\Celebration;
use App\Models\Genre;
use App\Models\MusicPlan;
use App\Models\User;
use App\Services\LiturgicalInfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    mockCelebrations([]);
});

/**
 * Bind a LiturgicalInfoService that returns the given celebration payloads.
 *
 * @param  array<int, array<string, mixed>>  $celebrations
 */
function mockCelebrations(array $celebrations): void
{
    app()->instance(LiturgicalInfoService::class, Mockery::mock(LiturgicalInfoService::class, function (MockInterface $mock) use ($celebrations) {
        $mock->shouldReceive('getCelebrations')->andReturn($celebrations);
    }));
}

/**
 * Build a celebration payload (as the LiturgicalInfoService would) for the given model.
 *
 * @return array<string, mixed>
 */
function celebrationPayload(Celebration $celebration): array
{
    return [
        'name' => $celebration->name,
        'dateISO' => $celebration->actual_date->format('Y-m-d'),
        'season' => $celebration->season,
        'colorId' => $celebration->color_id,
        'colorText' => $celebration->color_text,
        'week' => $celebration->week,
        'dayofWeek' => $celebration->day,
        'readingsId' => $celebration->readings_code,
        'yearLetter' => $celebration->year_letter,
        'yearParity' => $celebration->year_parity,
    ];
}

test('liturgical info can move to previous day', function () {
    Livewire::test('liturgical-info')
        ->set('date', '2026-03-14')
        ->call('previousDay')
        ->assertSet('date', '2026-03-13');
});

test('liturgical info can move to previous sunday from a weekday', function () {
    Livewire::test('liturgical-info')
        ->set('date', '2026-03-18')
        ->call('previousSunday')
        ->assertSet('date', '2026-03-15');
});

test('liturgical info moves one full week back when already on sunday', function () {
    Livewire::test('liturgical-info')
        ->set('date', '2026-03-15')
        ->call('previousSunday')
        ->assertSet('date', '2026-03-08');
});

test('liturgical info renders compact grouped navigation controls', function () {
    Livewire::test('liturgical-info')
        ->assertSeeInOrder([
            'Előző/következő nap',
            'Előző/következő vasárnap',
        ])
        ->assertSeeHtml('aria-label="Előző nap"')
        ->assertSeeHtml('aria-label="Következő nap"')
        ->assertSeeHtml('aria-label="Előző vasárnap"')
        ->assertSeeHtml('aria-label="Következő vasárnap"');
});

test('existing plans show the user own plan and exclude other users plans', function () {
    Genre::factory()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'name' => 'Pünkösd',
        'actual_date' => '2026-06-01',
    ]);

    $own = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'is_private' => true,
        'celebration_id' => $celebration->id,
    ]);
    $foreign = MusicPlan::factory()->create([
        'user_id' => $other->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $celebration->id,
    ]);

    mockCelebrations([celebrationPayload($celebration)]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-06-01')
        ->assertSeeHtml('href="'.route('music-plan-editor', ['musicPlan' => $own->id]).'"')
        ->assertDontSeeHtml('href="'.route('music-plan-editor', ['musicPlan' => $foreign->id]).'"');
});

test('published plans show other users public plans and exclude own and private plans', function () {
    Genre::factory()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'name' => 'Pünkösd',
        'actual_date' => '2026-06-01',
    ]);

    $publishedByOther = MusicPlan::factory()->create([
        'user_id' => $other->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $celebration->id,
    ]);
    $privateByOther = MusicPlan::factory()->create([
        'user_id' => $other->id,
        'genre_id' => null,
        'is_private' => true,
        'celebration_id' => $celebration->id,
    ]);
    $ownPublished = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $celebration->id,
    ]);

    mockCelebrations([celebrationPayload($celebration)]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-06-01')
        ->assertSeeHtml('href="'.route('music-plan-view', ['musicPlan' => $publishedByOther->id]).'"')
        ->assertDontSeeHtml('href="'.route('music-plan-view', ['musicPlan' => $privateByOther->id]).'"')
        ->assertDontSeeHtml('href="'.route('music-plan-view', ['musicPlan' => $ownPublished->id]).'"');
});

test('suggestions button appears only when a related celebration has a matching plan', function () {
    Genre::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $withPlan = Celebration::factory()->liturgical()->create([
        'name' => 'Pünkösd',
        'actual_date' => '2026-06-01',
    ]);
    $withoutPlan = Celebration::factory()->liturgical()->create([
        'name' => 'Szentháromság',
        'actual_date' => '2026-06-01',
    ]);

    MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $withPlan->id,
    ]);

    mockCelebrations([
        celebrationPayload($withPlan),
        celebrationPayload($withoutPlan),
    ]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-06-01')
        ->assertSee('Énekjavaslatok az ünnepre')
        ->assertSee('Még nincsenek énekjavaslatok');
});

test('suggestion existence is computed with a single full celebration scan regardless of card count', function () {
    Genre::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $payloads = collect(range(1, 3))->map(function (int $i) use ($user) {
        $celebration = Celebration::factory()->liturgical()->create([
            'name' => "Ünnep {$i}",
            'actual_date' => '2026-06-01',
        ]);
        MusicPlan::factory()->create([
            'user_id' => $user->id,
            'genre_id' => null,
            'is_private' => false,
            'celebration_id' => $celebration->id,
        ]);

        return celebrationPayload($celebration);
    })->all();

    mockCelebrations($payloads);

    $fullScans = 0;
    DB::listen(function ($query) use (&$fullScans) {
        if (preg_match('/^select \* from ["`]?celebrations["`]?\s*$/i', trim($query->sql))) {
            $fullScans++;
        }
    });

    // Freeze time so mount() targets the seeded date and the component renders exactly once.
    $this->travelTo('2026-06-01');
    Livewire::test('liturgical-info');

    expect($fullScans)->toBe(1);
});

test('suggestion carousel previews songs with incipits and links to all suggestions', function () {
    Storage::fake();
    Genre::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'name' => 'Pünkösd',
        'actual_date' => '2026-06-01',
    ]);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $celebration->id,
    ]);

    $slot = \App\Models\MusicPlanSlot::factory()->create(['name' => 'Kezdőének']);
    $music = \App\Models\Music::factory()->create(['title' => 'Jöjj Szentlélek Úristen']);

    $plan->slots()->attach($slot->id, ['sequence' => 1]);
    $pivot = \App\Models\MusicPlanSlotPlan::where('music_plan_id', $plan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->first();
    \App\Models\MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    $score = \App\Models\Score::factory()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'public_preview' => true,
    ]);
    Storage::put($score->incipit_path, 'fake-png-data');

    mockCelebrations([celebrationPayload($celebration)]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-06-01')
        ->assertSee('Énekjavaslatok az ünnepre')
        ->assertSee('Kezdőének')
        ->assertSee('Jöjj Szentlélek Úristen')
        ->assertSeeHtml(route('scores.public-incipit', $score))
        ->assertSee('Az összes énekjavaslat');
});

test('suggestion carousel includes songs even when they have no incipit', function () {
    Genre::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'name' => 'Pünkösd',
        'actual_date' => '2026-06-01',
    ]);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $celebration->id,
    ]);

    $slot = \App\Models\MusicPlanSlot::factory()->create(['name' => 'Kezdőének']);
    $music = \App\Models\Music::factory()->create(['title' => 'Incipit nélküli ének']);

    $plan->slots()->attach($slot->id, ['sequence' => 1]);
    $pivot = \App\Models\MusicPlanSlotPlan::where('music_plan_id', $plan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->first();
    \App\Models\MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    mockCelebrations([celebrationPayload($celebration)]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-06-01')
        ->assertSee('Énekjavaslatok az ünnepre')
        ->assertSee('Kezdőének')
        ->assertSee('Incipit nélküli ének')
        ->assertSee('Az összes énekjavaslat');
});

test('suggestion carousel is keyed by date so it resets when the day changes', function () {
    Genre::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'name' => 'Pünkösd',
        'actual_date' => '2026-06-01',
    ]);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $celebration->id,
    ]);

    $slot = \App\Models\MusicPlanSlot::factory()->create(['name' => 'Kezdőének']);
    $music = \App\Models\Music::factory()->create(['title' => 'Valamilyen ének']);

    $plan->slots()->attach($slot->id, ['sequence' => 1]);
    $pivot = \App\Models\MusicPlanSlotPlan::where('music_plan_id', $plan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->first();
    \App\Models\MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    mockCelebrations([celebrationPayload($celebration)]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-06-01')
        ->assertSeeHtml('wire:key="suggestion-carousel-0-2026-06-01-all"')
        ->call('nextDay')
        ->assertSet('date', '2026-06-02')
        ->assertSeeHtml('wire:key="suggestion-carousel-0-2026-06-02-all"');
});

test('suggestion carousel orders songs by slot priority and shows collection badges', function () {
    Genre::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'name' => 'Pünkösd',
        'actual_date' => '2026-06-01',
    ]);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'is_private' => false,
        'celebration_id' => $celebration->id,
    ]);

    $opening = \App\Models\MusicPlanSlot::factory()->create(['name' => 'Kezdőének', 'priority' => 1]);
    $offertory = \App\Models\MusicPlanSlot::factory()->create(['name' => 'Felajánlás', 'priority' => 2]);

    $openingSong = \App\Models\Music::factory()->create(['title' => 'Opening song']);
    $offertorySong = \App\Models\Music::factory()->create(['title' => 'Offertory song']);

    $collection = \App\Models\Collection::factory()->create([
        'user_id' => $user->id,
        'is_private' => false,
        'abbreviation' => 'SzVU',
    ]);
    $openingSong->collections()->attach($collection->id, ['order_number' => 7]);

    // Attach the lower-priority slot first to prove ordering is by priority, not insertion order.
    foreach ([[$offertory, $offertorySong], [$opening, $openingSong]] as [$slot, $song]) {
        $plan->slots()->attach($slot->id, ['sequence' => 1]);
        $pivot = \App\Models\MusicPlanSlotPlan::where('music_plan_id', $plan->id)
            ->where('music_plan_slot_id', $slot->id)
            ->first();
        \App\Models\MusicPlanSlotAssignment::create([
            'music_plan_slot_plan_id' => $pivot->id,
            'music_id' => $song->id,
            'music_sequence' => 1,
        ]);
    }

    mockCelebrations([celebrationPayload($celebration)]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-06-01')
        ->assertSeeInOrder(['Opening song', 'Offertory song'])
        ->assertSee('SzVU 7');
});

test('creating a music plan persists the celebration liturgical color', function () {
    Genre::factory()->create();
    $this->actingAs(User::factory()->create());

    mockCelebrations([[
        'name' => 'Pünkösdvasárnap',
        'dateISO' => '2026-05-24',
        'celebrationKey' => 0,
        'season' => 8,
        'seasonText' => 'húsvéti idő',
        'colorId' => '1',
        'colorText' => 'piros',
        'week' => 0,
        'dayofWeek' => 0,
    ]]);

    Livewire::test('liturgical-info')
        ->set('date', '2026-05-24')
        ->call('createMusicPlan', 0);

    expect(Celebration::where('name', 'Pünkösdvasárnap')->first())
        ->color_id->toBe('1')
        ->color_text->toBe('piros');
});
