<?php

/*
 * A rule about markup, kept as a test because the failure it stands for is
 * invisible in every other one.
 *
 * Alpine writes a style bound as a string with setAttribute('style', …), which
 * throws away everything already on the element's style attribute — including
 * the `display: none` that x-show had just put there. Both projection pages
 * re-read where the picture lands on the wall once a second, so every style
 * binding on them re-fires once a second, and an element x-show had hidden came
 * back a beat later: the title card the room was looking at was drawn beside the
 * slide it had replaced, and the slide the wall was showing came back out from
 * under the card. Bound as an object, Alpine sets the properties one at a time
 * and leaves the display alone.
 */
it('binds every style in the projection pages as an object rather than a string', function (string $view): void {
    $markup = file_get_contents(dirname(__DIR__, 2)."/resources/views/livewire/pages/{$view}.blade.php");

    preg_match_all('/x-bind:style="(.)/', $markup, $bindings);

    expect($bindings[1])->not->toBeEmpty("{$view} binds no styles at all, so this test is watching nothing");

    foreach ($bindings[1] as $opening) {
        expect($opening)->toBe('{', "{$view} binds a style as a string, which will undo an x-show on the same element");
    }
})->with(['projection-remote', 'projection-presenter']);
