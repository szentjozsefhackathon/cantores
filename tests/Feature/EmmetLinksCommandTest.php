<?php

use App\Models\Collection;
use App\Models\Music;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<int, string>  $numbers
 * @param  array<string, array{lyrics: array<int, string>, records: array<int, string>}>  $recordings  record languages keyed by song number
 */
function fakeEmmetSongs(array $numbers, array $recordings = []): void
{
    Http::fake([
        'emmet.emmanuelkozosseg.hu/songs.json' => Http::response([
            'books' => [['id' => 'emm_hu', 'name' => 'Jézus él']],
            'songs' => array_map(fn (string $number) => [
                'books' => [
                    ['id' => 'emm_hu', 'number' => $number, 'lang' => 'hu'],
                    ['id' => 'emm_fr', 'number' => '999', 'lang' => 'fr'],
                ],
                'lyrics' => array_map(
                    fn (string $lang) => ['lang' => $lang, 'title' => 'Title'],
                    $recordings[$number]['lyrics'] ?? ['hu'],
                ),
                ...(isset($recordings[$number]) ? ['records' => array_map(
                    fn (string $lang) => ['type' => 'file', 'url' => 'https://emmet.emmanuelkozosseg.hu/mp3/x.mp3', 'lang' => $lang, 'purpose' => 'listening'],
                    $recordings[$number]['records'],
                )] : []),
            ], $numbers),
        ]),
    ]);
}

function jelMusic(Collection $collection, string $orderNumber): Music
{
    $music = Music::factory()->create();
    $music->collections()->attach($collection->id, ['order_number' => $orderNumber]);

    return $music;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->collection = Collection::factory()->create(['abbreviation' => 'JÉL']);
});

it('links music to its Emmet song page by order number', function () {
    fakeEmmetSongs(['9', 'I2']);
    $numbered = jelMusic($this->collection, '9');
    $prayer = jelMusic($this->collection, 'i2');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])->assertSuccessful();

    expect($numbered->urls()->pluck('url')->all())->toBe(['https://emmet.emmanuelkozosseg.hu/emm-hu/9'])
        ->and($prayer->urls()->pluck('url')->all())->toBe(['https://emmet.emmanuelkozosseg.hu/emm-hu/I2'])
        ->and($numbered->urls()->first()->label)->toBe('text')
        ->and($numbered->urls()->first()->user_id)->toBe($this->user->id);
});

it('links the recordings tab when Emmet has recordings of the song', function () {
    fakeEmmetSongs(['1', '9'], ['1' => ['lyrics' => ['hu', 'en'], 'records' => ['hu']]]);
    $recorded = jelMusic($this->collection, '1');
    $silent = jelMusic($this->collection, '9');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])
        ->expectsOutputToContain('3 links created')
        ->assertSuccessful();

    expect($recorded->urls()->where('label', 'audio')->pluck('url')->all())
        ->toBe(['https://emmet.emmanuelkozosseg.hu/emm-hu/1/hu/rec/'])
        ->and($recorded->urls()->where('label', 'text')->count())->toBe(1)
        ->and($silent->urls()->where('label', 'audio')->count())->toBe(0);
});

it('uses the language of the recording for the recordings tab', function () {
    fakeEmmetSongs(['I1'], ['I1' => ['lyrics' => ['hu', 'la'], 'records' => ['la']]]);
    $music = jelMusic($this->collection, 'I1');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])->assertSuccessful();

    expect($music->urls()->where('label', 'audio')->value('url'))
        ->toBe('https://emmet.emmanuelkozosseg.hu/emm-hu/I1/la/rec/');
});

it('falls back to the main lyrics language when no lyrics match the recording', function () {
    fakeEmmetSongs(['2'], ['2' => ['lyrics' => ['hu'], 'records' => ['fr']]]);
    $music = jelMusic($this->collection, '2');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])->assertSuccessful();

    expect($music->urls()->where('label', 'audio')->value('url'))
        ->toBe('https://emmet.emmanuelkozosseg.hu/emm-hu/2/hu/rec/');
});

it('skips music whose number is not in Emmet and reports it', function () {
    fakeEmmetSongs(['501a']);
    $mass = jelMusic($this->collection, '501');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])
        ->expectsOutputToContain('Not found in Emmet: 501')
        ->assertSuccessful();

    expect($mass->urls()->count())->toBe(0);
});

it('does not duplicate links on a second run', function () {
    fakeEmmetSongs(['9']);
    $music = jelMusic($this->collection, '9');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])->assertSuccessful();
    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])
        ->expectsOutputToContain('0 links created, 1 already present')
        ->assertSuccessful();

    expect($music->urls()->count())->toBe(1);
});

it('writes nothing on a dry run', function () {
    fakeEmmetSongs(['9']);
    $music = jelMusic($this->collection, '9');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id, '--dry-run' => true])
        ->expectsOutputToContain('1 links would be created')
        ->assertSuccessful();

    expect($music->urls()->count())->toBe(0);
});

it('ignores music in other collections', function () {
    fakeEmmetSongs(['9']);
    $other = Collection::factory()->create(['abbreviation' => 'EMMETTEST']);
    $music = jelMusic($other, '9');

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])->assertSuccessful();

    expect($music->urls()->count())->toBe(0);
});

it('fails when Emmet cannot be reached', function () {
    Http::fake(['emmet.emmanuelkozosseg.hu/*' => Http::response('', 500)]);

    $this->artisan('cantores:emmet-links', ['--user' => $this->user->id])->assertFailed();
});

it('requires a user', function () {
    $this->artisan('cantores:emmet-links')->assertFailed();
});
