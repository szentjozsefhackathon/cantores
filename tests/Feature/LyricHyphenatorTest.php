<?php

use App\Enums\LyricLanguage;
use App\Enums\ScoreFormat;
use App\Livewire\Pages\ScoreEditor;
use App\Models\Score;
use App\Services\HyphenationDictionary;
use App\Services\LyricHyphenator;
use Livewire\Livewire;

function hyphenateLyrics(string $text, LyricLanguage $language, ScoreFormat $format = ScoreFormat::Abc): string
{
    return app(LyricHyphenator::class)->hyphenate($text, $language, $format);
}

it('divides Hungarian words into every syllable a singer needs', function (string $word, string $expected) {
    expect(hyphenateLyrics($word, LyricLanguage::Hungarian))->toBe($expected);
})->with([
    'compound prefix' => ['megáldott', 'meg-ál-dott'],
    'compound noun' => ['Szentlélek', 'Szent-lé-lek'],
    'single vowel at the start' => ['öröm', 'ö-röm'],
    'vowels side by side' => ['Mária', 'Má-ri-a'],
    'digraph after the first vowel' => ['anyánk', 'a-nyánk'],
    'doubled digraph' => ['asszony', 'asz-szony'],
    'doubled digraph at the edge' => ['hosszú', 'hosz-szú'],
    'one syllable' => ['ó', 'ó'],
    'a word in capitals' => ['ALLELUJA', 'AL-LE-LU-JA'],
]);

it('divides Latin as Solesmes does', function (string $word, string $expected) {
    expect(hyphenateLyrics($word, LyricLanguage::Latin))->toBe($expected);
})->with([
    ['Kyrie', 'Ky-ri-e'],
    ['eleison', 'e-le-i-son'],
    ['Agnus', 'A-gnus'],
    ['Christe', 'Chris-te'],
    ['discipulis', 'di-sci-pu-lis'],
    ['sanguis', 'san-guis'],
    ['laudamus', 'lau-da-mus'],
    ['allelúia', 'al-le-lú-ia'],
]);

it('hyphenates the lyric lines of an ABC or Aretino selection and nothing else', function (ScoreFormat $format) {
    $selection = "C D E F|G2 G2|\nw: Dicsérjük az Urat, alleluja!\nW: Dicsőség az Atyának";

    expect(hyphenateLyrics($selection, LyricLanguage::Hungarian, $format))
        ->toBe("C D E F|G2 G2|\nw: Di-csér-jük az U-rat, al-le-lu-ja!\nW: Dicsőség az Atyának");
})->with([ScoreFormat::Abc, ScoreFormat::Aretino]);

it('hyphenates a selection made inside a lyric line as lyrics', function () {
    expect(hyphenateLyrics('Gloria in excelsis Deo', LyricLanguage::Latin, ScoreFormat::Aretino))
        ->toBe('Glo-ri-a in ex-cel-sis De-o');
});

it('keeps lyric marks and leaves syllables already divided as they are', function () {
    expect(hyphenateLyrics('Ky-rie_ * e~le only\-begotten, Christe=eleison', LyricLanguage::Latin))
        ->toBe('Ky-ri-e_ * e~le only\-begotten, Chris-te=e-le-i-son');
});

it('gives every GABC syllable a note group', function () {
    expect(hyphenateLyrics('Kyrie eleison.', LyricLanguage::Latin, ScoreFormat::Gabc))
        ->toBe('Ky()ri()e() e()le()i()son.()');
});

it('keeps the notes a GABC word already has on its last syllable', function () {
    expect(hyphenateLyrics('(c4) Kyrie(f) <i>eleison</i> Christe(g) (::)', LyricLanguage::Latin, ScoreFormat::Gabc))
        ->toBe('(c4) Ky()ri()e(f) <i>e()le()i()son</i>() Chris()te(g) (::)');
});

it('leaves a GABC file header alone', function () {
    expect(hyphenateLyrics("name: Kyrie;\n%%\n(c4) Kyrie", LyricLanguage::Latin, ScoreFormat::Gabc))
        ->toBe("name: Kyrie;\n%%\n(c4) Ky()ri()e()");
});

it('applies a non-standard libhyphen pattern, rewriting the letters at its break', function () {
    $dictionary = HyphenationDictionary::fromLines(['UTF-8', 's1sz/sz=sz,1,3', 'o1n']);

    expect($dictionary->syllables('asszony'))->toBe(['asz', 'szo', 'ny']);
});

it('hyphenates the selection in the notation the score editor is editing', function () {
    $score = Score::factory()->gabc()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->call('hyphenateLyrics', 'Kyrie eleison', 'la')
        ->assertReturned('Ky()ri()e() e()le()i()son()')
        ->set('format', 'abc')
        ->call('hyphenateLyrics', 'Boldogasszony anyánk', 'hu')
        ->assertReturned('Bol-dog-asz-szony a-nyánk');
});

it('gives the selection back unchanged for a language it does not know', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->call('hyphenateLyrics', 'Kyrie eleison', 'xx')
        ->assertReturned('Kyrie eleison');
});

it('offers the hyphenation button in the score editor', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Hyphenate lyrics'))
        ->assertSeeHtml('<path d="M18 8v9a3 3 0 0 1-3 3h-.5" />');
});
