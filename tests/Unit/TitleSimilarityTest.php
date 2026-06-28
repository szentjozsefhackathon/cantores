<?php

use App\Support\TitleSimilarity;

it('normalizes case, accents, punctuation and parentheticals', function () {
    expect(TitleSimilarity::normalize('Áldjad én lelkem! (Dicsőség)'))->toBe('aldjad en lelkem')
        ->and(TitleSimilarity::normalize('Őrző őrök [refrén]'))->toBe('orzo orok');
});

it('scores identical normalized titles as 1.0', function () {
    expect(TitleSimilarity::ratio('Áldjad én lelkem', 'áldjad én lelkem!'))->toBe(1.0)
        ->and(TitleSimilarity::ratio('A halotti sírnak ajtaján', 'A halotti sírnak ajtaján… (Dicsőség)'))->toBe(1.0);
});

it('scores unrelated titles well below the merge threshold', function () {
    expect(TitleSimilarity::ratio('Alfa', 'Omega'))->toBeLessThan(0.55);
});

it('scores an empty title as 0.0', function () {
    expect(TitleSimilarity::ratio('', 'Bármi'))->toBe(0.0);
});
