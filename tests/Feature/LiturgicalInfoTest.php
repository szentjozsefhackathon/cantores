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
