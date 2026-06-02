<?php

use App\Models\Celebration;

uses(Tests\TestCase::class);

test('maps liturgical color names to border classes', function (string $colorText, string $expected) {
    expect(Celebration::borderColorClassForColorText($colorText))->toBe($expected);
})->with([
    ['piros', 'border-red-500! dark:border-red-400!'],
    ['fehér', 'border-zinc-100! dark:border-zinc-100!'],
    ['zöld', 'border-green-500! dark:border-green-400!'],
    ['lila', 'border-purple-500! dark:border-purple-400!'],
    ['lila|fehér', 'border-purple-800! dark:border-purple-700!'],
    ['lila|fekete', 'border-zinc-900! dark:border-zinc-400!'],
    ['rózsaszín', 'border-pink-500! dark:border-pink-400!'],
    ['rózsaszín|lila', 'border-pink-500! dark:border-pink-400!'],
]);

test('color name matching is case insensitive', function () {
    expect(Celebration::borderColorClassForColorText('PIROS'))
        ->toBe('border-red-500! dark:border-red-400!');
});

test('falls back to neutral for unknown or empty color', function (?string $colorText) {
    expect(Celebration::borderColorClassForColorText($colorText))
        ->toBe('border-neutral-300! dark:border-neutral-600!');
})->with([null, '', 'turquoise']);

test('uses stored color text for the celebration border class', function () {
    $celebration = Celebration::factory()->make(['color_text' => 'zöld']);

    expect($celebration->liturgicalBorderColorClass())
        ->toBe('border-green-500! dark:border-green-400!');
});

test('falls back to season when no color is stored', function (int $season, string $expected) {
    $celebration = Celebration::factory()->make(['color_text' => null, 'season' => $season]);

    expect($celebration->liturgicalBorderColorClass())->toBe($expected);
})->with([
    'advent (violet)' => [0, 'border-purple-500! dark:border-purple-400!'],
    'christmas (white)' => [4, 'border-zinc-100! dark:border-zinc-100!'],
    'ordinary (green)' => [5, 'border-green-500! dark:border-green-400!'],
    'lent (violet)' => [6, 'border-purple-500! dark:border-purple-400!'],
    'holy week (red)' => [7, 'border-red-500! dark:border-red-400!'],
]);

test('falls back to neutral for custom celebrations without color or season', function () {
    $celebration = Celebration::factory()->make(['color_text' => null, 'season' => null]);

    expect($celebration->liturgicalBorderColorClass())
        ->toBe('border-neutral-300! dark:border-neutral-600!');
});
