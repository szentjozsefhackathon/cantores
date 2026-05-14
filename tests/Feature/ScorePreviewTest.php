<?php

use App\Livewire\Pages\ScoreEditor;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function makeSharePayload(array $data): string
{
    $json = json_encode($data);
    $b64 = base64_encode($json);

    return str_replace(['+', '/', '='], ['-', '_', ''], $b64);
}

function makeGzipSharePayload(array $data): string
{
    $json = (string) json_encode($data);
    $compressed = gzdeflate($json, 9);

    return rtrim(strtr(base64_encode((string) $compressed), '+/', '-_'), '=');
}

it('guest can load shared score preview from url', function () {
    $d = makeSharePayload([
        'title' => 'Ave Maria',
        'format' => 'abc',
        'content' => "X:1\nT:Ave Maria\nK:C\nC D E F|",
        'settings' => ['abc' => ['auto' => ['abcLyricSize' => 14]]],
    ]);

    get(route('score.preview', ['d' => $d]))->assertOk();
});

it('populates fields from shared url data', function () {
    $d = makeSharePayload([
        'title' => 'Kyrie',
        'format' => 'gabc',
        'content' => "name: Kyrie;\n%%\n(c4) Ky(e)ri(f)e(g)",
        'settings' => [],
    ]);

    Livewire::withQueryParams(['d' => $d])
        ->test(ScoreEditor::class)
        ->assertSet('title', 'Kyrie')
        ->assertSet('format', 'gabc')
        ->assertSet('content', "name: Kyrie;\n%%\n(c4) Ky(e)ri(f)e(g)")
        ->assertSet('isSharedLink', true);
});

it('falls back to abc format for invalid format in shared data', function () {
    $d = makeSharePayload([
        'title' => 'Test',
        'format' => 'invalid',
        'content' => 'some content',
        'settings' => [],
    ]);

    Livewire::withQueryParams(['d' => $d])
        ->test(ScoreEditor::class)
        ->assertSet('format', 'abc');
});

it('guest cannot save a shared score', function () {
    $d = makeSharePayload([
        'title' => 'Stolen Score',
        'format' => 'abc',
        'content' => "X:1\nT:Test\nK:C\nC D|",
        'settings' => [],
    ]);

    Livewire::withQueryParams(['d' => $d])
        ->test(ScoreEditor::class)
        ->call('save')
        ->assertForbidden();

    expect(Score::query()->where('title', 'Stolen Score')->exists())->toBeFalse();
});

it('authenticated user can save a shared score as a new score', function () {
    $user = User::factory()->create();
    actingAs($user);

    $d = makeSharePayload([
        'title' => 'Shared Hymn',
        'format' => 'abc',
        'content' => "X:1\nT:Shared Hymn\nK:C\nC D E F|",
        'settings' => [],
    ]);

    Livewire::withQueryParams(['d' => $d])
        ->test(ScoreEditor::class)
        ->assertSet('isSharedLink', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Score::query()->where('title', 'Shared Hymn')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('handles malformed base64 share data gracefully', function () {
    Livewire::withQueryParams(['d' => 'not!!valid!!base64'])
        ->test(ScoreEditor::class)
        ->assertSet('title', '')
        ->assertSet('content', '')
        ->assertSet('isSharedLink', true);
});

it('populates fields from gzip-compressed shared url data', function () {
    $d = makeGzipSharePayload([
        'title' => 'Compressed Score',
        'format' => 'gabc',
        'content' => "name: Test;\n%%\n(c4) A(e)men(f)",
        'settings' => ['gabc' => ['auto' => ['lyricSize' => 16]]],
    ]);

    Livewire::withQueryParams(['d' => $d])
        ->test(ScoreEditor::class)
        ->assertSet('title', 'Compressed Score')
        ->assertSet('format', 'gabc')
        ->assertSet('content', "name: Test;\n%%\n(c4) A(e)men(f)")
        ->assertSet('isSharedLink', true);
});

it('createShareUrl returns a valid url that can be parsed back', function () {
    $user = User::factory()->create();
    actingAs($user);

    $data = [
        'title' => 'Round-trip Score',
        'format' => 'abc',
        'content' => "X:1\nT:Test\nK:C\nC D E F|",
        'settings' => ['abc' => ['auto' => ['abcLyricSize' => 12]]],
    ];

    $url = Livewire::test(ScoreEditor::class)->instance()->createShareUrl($data);

    expect($url)->toBeString()->toContain(route('score.preview'));

    $parsed = parse_url($url);
    parse_str($parsed['query'] ?? '', $params);

    Livewire::withQueryParams(['d' => $params['d']])
        ->test(ScoreEditor::class)
        ->assertSet('title', 'Round-trip Score')
        ->assertSet('format', 'abc')
        ->assertSet('content', "X:1\nT:Test\nK:C\nC D E F|");
});
