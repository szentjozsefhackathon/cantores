<?php

use App\Enums\SyllableStress;
use App\Livewire\Pages\PsalmTones;
use App\Services\HungarianStress;
use App\Services\PsalmSyllable;
use App\Services\PsalmTone;
use App\Services\PsalmToneLibrary;
use App\Services\PsalmToneSetter;
use App\Services\PsalmToneSlot;
use Livewire\Livewire;

/**
 * The first tone as it was first sketched: no initium, no flexa.
 */
function sketchedFirstTone(): PsalmTone
{
    return PsalmTone::fromLine('I', '| , at b- as a g- as a | at g f gs- gf d ||');
}

function benedictineFirstTone(): PsalmTone
{
    return PsalmTone::fromLine('I.g', '(Kb) f g | at g , at b- ao g- as a | at g f a- gs g ||');
}

/**
 * @return array{source: string, unsure: list<array{verse: int, syllable: string}>}
 */
function setPsalm(PsalmTone $tone, string $verses, bool $initiumEveryVerse = false): array
{
    return app(PsalmToneSetter::class)->set($tone, $verses, $initiumEveryVerse);
}

it('reads what every slot of a formula is for', function (string $token, string $notes, bool $isTenor, bool $isOpen, bool $isAccent, bool $isOptional, int $noteCount) {
    expect((array) PsalmToneSlot::fromToken($token))
        ->toMatchArray(compact('notes', 'isTenor', 'isOpen', 'isAccent', 'isOptional', 'noteCount'));
})->with([
    'tenor' => ['at', 'at', true, false, false, false, 1],
    'open' => ['ao', 'a', false, true, false, false, 1],
    'accent' => ['b-', 'b', false, false, true, false, 1],
    'optional' => ['as', 'a', false, false, false, true, 1],
    'optional accent' => ['gs-', 'g', false, false, true, true, 1],
    'two notes' => ['gf', 'gf', false, false, false, false, 2],
    'accidental' => ['(#)g', '(#)g', false, false, false, false, 1],
    'upper octave' => ['C-', 'C', false, false, true, false, 1],
    'open neume' => ['go-a', 'ga', false, true, true, false, 2],
    'optional accent marks in either order' => ['g-s', 'g', false, false, true, true, 1],
]);

it('writes the notes around an open note on the first and last of its syllables', function (string $token, int $count, array $notes) {
    $slot = PsalmToneSlot::fromToken($token);

    expect(array_map(fn (int $index): string => $slot->notesFor($index, $count), range(0, $count - 1)))->toBe($notes);
})->with([
    'open note alone' => ['ao', 3, ['a', 'a', 'a']],
    'neume on one syllable' => ['go-a', 1, ['ga']],
    'neume on two syllables' => ['go-a', 2, ['g', 'ga']],
    'neume on three syllables' => ['go-a', 3, ['g', 'g', 'ga']],
    'notes on both sides' => ['ago(#)b', 2, ['ag', 'g(#)b']],
    'accidental once' => ['(#)go', 2, ['(#)g', 'g']],
]);

it('sings the last note of an open neume on its last syllable', function () {
    $tone = PsalmTone::fromLine('I.g', '(Kb) f g | at g , at g- fo | at g f go-a g ||');

    expect(setPsalm($tone, 'nemzedékről nemzedékre.')['source'])
        ->toContain('(Kb) at g f g g ga g ||')
        ->and(setPsalm($tone, 'Uram, te lettél menedékünk.')['source'])
        ->toContain('(Kb) at g f ga g ||');
});

it('reads a tone line into its four formulas', function () {
    $tone = benedictineFirstTone();

    expect($tone->keySignature)->toBe('(Kb)')
        ->and(array_column($tone->initium, 'notes'))->toBe(['f', 'g'])
        ->and(array_column($tone->flexa, 'notes'))->toBe(['at', 'g'])
        ->and(array_column($tone->mediatio, 'notes'))->toBe(['at', 'b', 'a', 'g', 'a', 'a'])
        ->and(array_column($tone->terminatio, 'notes'))->toBe(['at', 'g', 'f', 'a', 'g', 'g']);
});

it('refuses a line that is not a tone', function (string $line) {
    PsalmTone::fromLine('X', $line);
})->throws(InvalidArgumentException::class)->with([
    'missing parts' => ['at b- a | at g f ||'],
    'no terminatio' => ['f g | at g , at b- a | ||'],
    'two tenors' => ['| , at b at a | at g ||'],
    'notes after the end' => ['| , at a | at g || g'],
]);

it('stresses Hungarian words', function (string $text, array $stresses) {
    $syllables = app(HungarianStress::class)->syllables($text);

    expect(array_map(fn (PsalmSyllable $syllable): string => $syllable->text, $syllables))->toBe(array_keys($stresses))
        ->and(array_map(fn (PsalmSyllable $syllable): SyllableStress => $syllable->stress, $syllables))->toBe(array_values($stresses));
})->with([
    'secondary stress in a long word' => ['menedékünk', ['me' => SyllableStress::Primary, 'ne' => SyllableStress::None, 'dé' => SyllableStress::Secondary, 'künk' => SyllableStress::None]],
    'never on the last syllable' => ['Istene', ['Is' => SyllableStress::Primary, 'te' => SyllableStress::None, 'ne' => SyllableStress::None]],
    'article' => ['az Úr', ['az' => SyllableStress::None, 'Úr' => SyllableStress::Primary]],
    'known compound' => ['Szentléleknek', ['Szent' => SyllableStress::Primary, 'lé' => SyllableStress::Secondary, 'lek' => SyllableStress::None, 'nek' => SyllableStress::None]],
    'secondary stress marked by hand' => ['mind·örökké', ['mind' => SyllableStress::Primary, 'ö' => SyllableStress::Secondary, 'rök' => SyllableStress::None, 'ké' => SyllableStress::None]],
    'stress marked by hand' => ["szent'lélek", ['szent' => SyllableStress::Primary, 'lé' => SyllableStress::Primary, 'lek' => SyllableStress::None]],
    'divided by hand' => ['Is-te-nünk,', ['Is' => SyllableStress::Primary, 'te' => SyllableStress::None, 'nünk,' => SyllableStress::None]],
]);

it('weighs closed and long-voweled syllables as long', function () {
    $syllables = app(HungarianStress::class)->syllables('menedékünk');

    expect(array_map(fn (PsalmSyllable $syllable): bool => $syllable->isLong, $syllables))->toBe([false, false, true, true]);
});

it('sets the accents of the mediatio and the terminatio on stressed syllables', function () {
    expect(setPsalm(sketchedFirstTone(), 'Uram, te lettél menedékünk, * nemzedékről nemzedékre.')['source'])
        ->toBe("(g2) at b a g a | at g f gf d ||\nw: Uram,~te~lettél me-ne-dé-künk, (*) nemzedékről nem-ze-dék-re.");
});

it('sings the optional accent of the terminatio on a dactyl', function () {
    expect(setPsalm(sketchedFirstTone(), 'Adjatok hálát az Úrnak, * mert örökké tart irgalma.')['source'])
        ->toBe("(g2) at b a a g a | at g f g gf d ||\nw: Adjatok há-lát az Úr-nak, (*) mert~örök-ké tart ir-gal-ma.");
});

it('joins the notes after the accent onto a stressed last syllable', function () {
    expect(setPsalm(sketchedFirstTone(), 'Ezt mondja az Úr, * nemzedékről nemzedékre.')['source'])
        ->toStartWith('(g2) at b a a ga |')
        ->toContain('w: Ezt mond-ja az Úr, (*)');
});

it('bends the notes left over onto the last syllable of a text too short for the formula', function () {
    expect(setPsalm(sketchedFirstTone(), 'Ámen.')['source'])
        ->toBe("(g2) at gfgfd ||\nw: Á-men.");
});

it('sings the initium and the flexa', function () {
    expect(setPsalm(benedictineFirstTone(), 'Uram, te lettél menedékünk + nemzedékről nemzedékre, * mielőtt a hegyek születtek.')['source'])
        ->toStartWith('(g2) (Kb) f g at g , at ')
        ->toContain('w: U-ram, te~lettél~menedé-künk (+) ');
});

it('sings the initium on the first verse only unless asked to', function () {
    $verses = "Uram, te lettél menedékünk, * nemzedékről nemzedékre.\nMielőtt a hegyek születtek, * öröktől fogva te vagy.";

    $blocks = explode("\n\n", setPsalm(benedictineFirstTone(), $verses)['source']);
    $everyVerse = explode("\n\n", setPsalm(benedictineFirstTone(), $verses, initiumEveryVerse: true)['source']);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0])->toStartWith('(g2) (Kb) f g at ')
        ->and($blocks[1])->toStartWith('(Z) at ')
        ->and($everyVerse[1])->toStartWith('(Z) f g at ');
});

it('bends the initium onto a verse that begins with a one-syllable word', function (string $verse, string $start) {
    expect(setPsalm(benedictineFirstTone(), $verse)['source'])->toStartWith($start);
})->with([
    'stressed monosyllable' => ['Jöjj, Szentlélek Isten, * töltsd be híveid szívét.', '(g2) (Kb) fg at '],
    'unstressed article' => ['Az Úr az én pásztorom, * nem szűkölködöm.', '(g2) (Kb) fg at '],
    'longer word' => ['Uram, te lettél menedékünk, * nemzedékről nemzedékre.', '(g2) (Kb) f g at '],
]);

it('sings a line without a mediatio to the terminatio', function () {
    expect(setPsalm(sketchedFirstTone(), 'nemzedékről nemzedékre.')['source'])
        ->toBe("(g2) at g f gf d ||\nw: nemzedékről nem-ze-dék-re.");
});

it('reports the syllables no stressed syllable could be found for', function () {
    expect(setPsalm(benedictineFirstTone(), "Uram, te lettél menedékünk, * az a\nnemzedékről nemzedékre.")['unsure'])
        ->toBe([['verse' => 1, 'syllable' => 'a']]);
});

it('reads every shipped tone collection', function () {
    $collections = app(PsalmToneLibrary::class)->collections();

    expect($collections)->toHaveKey('bences')
        ->and($collections['bences']['name'])->toBe('Bencés')
        ->and(array_keys($collections['bences']['tones']))->toContain('I.g', 'II.d', 'III.g', 'IV.e', 'V.a', 'VI.f', 'VII.a');
});

it('reads a collection written as an Aretino score', function () {
    $collection = PsalmToneLibrary::parse(
        "%collection: Próba\n%I.g \n(Kb) f g | at g , at b- ao | at g a- go ||\nw: ~ ~ ~ ~ (+) ~ ~ (*)\n\n%II.d\n(K) e f | , at bo- f | at f ||\n",
        'proba.aretino',
    );

    expect($collection['name'])->toBe('Próba')
        ->and(array_keys($collection['tones']))->toBe(['I.g', 'II.d'])
        ->and($collection['tones']['I.g']->preview())->toBe("(g2) (Kb) f g | at g , at b- ao | at g a- go ||\nw: ~ ~ ~ ~ (+) ~ ~ (*)")
        ->and($collection['tones']['II.d']->keySignature)->toBe('(K)')
        ->and($collection['tones']['II.d']->preview())->toBe("(g2) (K) e f | , at bo- f | at f ||\nw: ~ ~ () ~ ~ ~ (*)");
});

it('names the file and line of a malformed tone', function () {
    PsalmToneLibrary::parse("%collection: Próba\n%I.g\nat b- a | at g ||\n", 'proba.aretino');
})->throws(InvalidArgumentException::class, 'proba.aretino:3');

it('wants a name before every formula', function () {
    PsalmToneLibrary::parse("%collection: Próba\nat g | at g , at b- a | at g ||\n", 'proba.aretino');
})->throws(InvalidArgumentException::class, 'proba.aretino:2');

it('spells a doubled digraph as the word does when its syllables share a note', function () {
    expect(setPsalm(sketchedFirstTone(), 'visszatérít minket Urunk, * hosszú asszonyok gyönyörű vissza.')['source'])
        ->toContain('w: visszatérít min-ket U-runk, (*) hosszú~asszonyok~gyö-nyö-rű visz-sza.');
});

it('stresses Szentlélek where its second part begins', function () {
    $tone = PsalmTone::fromLine('VI.f', '(Kb) f g | at g , at g a- fo | at f go- a g- fo ||');

    expect(setPsalm($tone, 'Dicsőség az Atyának és Fiúnak * és Szentlélek Istennek,')['source'])
        ->toEndWith("| at f g a g f f ||\nw: Di-cső-ség~az~Atyának és Fi-ú-nak (*) és Szent-lé-lek Is-ten-nek,");
});

it('underlines only the first of two marked syllables next to each other', function () {
    $tone = PsalmTone::fromLine('VI.f', '(Kb) f g | at g , at g a- fo | at f go- a g- fo ||');

    expect(setPsalm($tone, 'Dicsőség az Atyának és Fiúnak * és Szentlélek Istennek,')['html'])
        ->toBe('<p>Dicsőség az Atyának <u>és</u> Fiúnak * és <u>Szent</u>lélek <u>Is</u>tennek,</p>');
});

it('underlines where the melody leaves an open note sung on several syllables, but not on the last syllable', function () {
    $tone = PsalmTone::fromLine('VII.a', '(K#) f g | at g , at C- bo a- bo | at b- ao go e ||');

    expect(setPsalm($tone, "Dicsőség az Atyának és Fiúnak * és Szentlélek Istennek,\nMiképpen kezdetben, most és mindenkor * és mindörökkön örökké. Ámen.")['html'])
        ->toBe("<p>Dicsőség az <u>A</u>tyának és <u>Fi</u>únak * és <u>Szent</u>lélek <u>Is</u>tennek,</p>\n"
            .'<p>Miképpen kezdetben, <u>most</u> és <u>min</u>denkor * és mindörökkön <u>ö</u>rökké. <u>Á</u>men.</p>');
});

it('shares the syllables between two open notes rather than giving one of them three', function () {
    $tone = PsalmTone::fromLine('VII.d', '(K#) f g | at g , at C- bo a- bo | at b- ao g ao ||');

    expect(setPsalm($tone, 'Meglátogattad és megöntözted a földet, * elhalmoztad bőséges áldással')['source'])
        ->toContain("| at b a a g a a ||\nw: ")
        ->toEndWith('(*) elhalmoztad bő-sé-ges ál-dás-sal');
});

it('lets the accent fall on the first part of Szentlélek when the formula wants it', function () {
    $tone = PsalmTone::fromLine('V.a', '(K##) d f | at f , at b- ao | at b- (g#)go a- fo ||');

    expect(setPsalm($tone, 'Dicsőség az Atyának és Fiúnak * és Szentlélek Istennek,')['html'])
        ->toBe('<p>Dicsőség az Atyának és <u>Fi</u>únak * és <u>Szent</u>lélek <u>Is</u>tennek,</p>');
});

it('writes the accidental of an open note once', function () {
    $tone = PsalmTone::fromLine('V.a', '(K##) d f | at f , at b- ao | at b- (g#)go a- fo ||');

    expect(setPsalm($tone, 'Miképpen kezdetben, most és mindenkor * és mindörökkön örökké. Ámen.')['source'])
        ->toContain('| at b (g#)g g a f ||');
});

it('writes the verses as HTML with the tenor left and the accents underlined', function () {
    $html = setPsalm(benedictineFirstTone(), "Mielőtt a hegyek születtek, † és létrejött a föld és a világ, * öröktől fogva te vagy, Isten.\nvisszatérít minket Urunk, * hosszú <asszonyok> vissza.")['html'];

    expect($html)->toBe(
        "<p>Mielőtt a hegyek szület<u>tek</u>, † és létrejött a <u>föld</u> és a <u>vi</u>lág, * öröktől fogva <u>te</u> vagy, <u>Is</u>ten.</p>\n"
        .'<p>visszatérít <u>min</u>ket <u>U</u>runk, * hosszú &lt;as<u>szo</u>nyok&gt; <u>vis</u>sza.</p>'
    );
});

it('goes on with a verse in the next line when a line ends with a mark', function () {
    $setting = setPsalm(benedictineFirstTone(), "Miképpen kezdetben, most és mindenkor *\nés mindörökkön örökké. Ámen.");

    expect(substr_count($setting['source'], 'w: '))->toBe(1)
        ->and($setting['source'])->toContain('(*)')->toContain('Á-men.');
});

it('starts with the doxology in the verses', function () {
    Livewire::test(PsalmTones::class)
        ->assertSet('verses', "Dicsőség az Atyának és Fiúnak * és Szentlélek Istennek,\nMiképpen kezdetben, most és mindenkor * és mindörökkön örökké. Ámen.")
        ->assertSee('Is-ten-nek,', escape: false);
});

it('sets verses to a chosen tone on its own page, for guests too', function () {
    $this->get(route('psalm-tones'))->assertOk()->assertSee('Bencés');

    Livewire::test(PsalmTones::class)
        ->assertSet('collection', 'bences')
        ->assertSet('tone', 'I.g')
        ->set('tone', 'II.d')
        ->set('verses', 'Uram, te lettél menedékünk, * nemzedékről nemzedékre.')
        ->assertSee('(g2) (K###) e f at')
        ->assertSee('w: ~ ~ ~ ~ (+) ~ ~ ~ (*)', escape: false)
        ->assertSee('/score/preview?d=', escape: false)
        ->assertSee('<p>Uram, te lettél', escape: false)->assertSee('<u>', escape: false)
        ->assertSee('x-ref="preview" wire:ignore', escape: false);
});

it('sets verses to a custom formula and explains a malformed one', function () {
    Livewire::test(PsalmTones::class)
        ->set('verses', 'Uram, te lettél menedékünk, * nemzedékről nemzedékre.')
        ->set('customFormula', '| , at b- as a g- as a | at g f gs- gf d ||')
        ->assertSee('at b a g a | at g f gf d ||')
        ->set('customFormula', 'at b- a | at g ||')
        ->assertSee(__('Unexpected ":token" in the tone. Write it as: initium | flexa , mediatio | terminatio ||', ['token' => '||']));
});

it('takes † for the flexa as well as +', function () {
    expect(setPsalm(benedictineFirstTone(), 'Uram, te lettél menedékünk † nemzedékről nemzedékre, * mielőtt a hegyek születtek.')['source'])
        ->toStartWith('(g2) (Kb) f g at g , at ')
        ->toContain('menedé-künk (+) nemzedékről');
});

it('drops the flexa mark when the tone has no flexa', function () {
    expect(setPsalm(sketchedFirstTone(), 'Uram, te lettél + menedékünk, * nemzedékről nemzedékre.')['source'])
        ->toBe("(g2) at b a g a | at g f gf d ||\nw: Uram,~te~lettél me-ne-dé-künk, (*) nemzedékről nem-ze-dék-re.");
});

it('always gives the tenor a syllable when the text allows it', function () {
    $tone = PsalmTone::fromLine('VI.f', '(Kb) f g | at g , at g a- fs f | at f g- gs a g- fs f ||');

    expect(setPsalm($tone, "Dicsőség az Atyának és Fiúnak, * és Szent'lélek Istennek.")['source'])
        ->toEndWith("| at f g a g f f ||\nw: Di-cső-ség~az~Atyának és Fi-ú-nak, (*) és Szent-lé-lek Is-ten-nek.");
});

it('sings up to three syllables on an open note between the accents, a note for each', function () {
    expect(setPsalm(benedictineFirstTone(), "Dicsőség az 'Atyának és 'Fiúnak, * és Szent'léleknek.")['source'])
        ->toStartWith('(g2) (Kb) f g at b a a a g a a |')
        ->toContain('w: Di-cső-ség~az A-tyá-nak és Fi-ú-nak, (*)');
});

it('writes an open note sung on one syllable closed', function () {
    expect(setPsalm(benedictineFirstTone(), 'Uram, te lettél menedékünk, * nemzedékről nemzedékre.')['source'])
        ->toStartWith('(g2) (Kb) f g at b a g a |')
        ->toContain('w: U-ram, te~lettél me-ne-dé-künk, (*)');
});
