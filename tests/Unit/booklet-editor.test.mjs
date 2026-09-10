import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../../resources/js/booklet-editor.js', import.meta.url), 'utf8');

// Alpine answers $wire with the Livewire component closest to whichever element
// the method was called from — not the one the data belongs to. Every row of the
// booklet is now a component of its own, so a knob turned in a score's toolbar
// calls the editor's own method with $wire pointing at that row, and the row knows
// nothing of saving overrides: "Public method [saveOverride] not found". The wire
// is taken once, in init(), where the answer is the editor, and used everywhere.
test('the editor talks to its own half of the server, not to whichever row called it', () => {
    const asked = source.match(/this\.\$wire/g) ?? [];

    assert.equal(asked.length, 1, 'a call went straight to $wire instead of the wire kept at init');
    assert.match(source, /init\(\)\s*\{\s*wire = this\.\$wire;/);
    assert.ok(/\bwire\.saveOverride\(/.test(source));
    assert.ok(/\bwire\.resetOverride\(/.test(source));
});

// And it is kept in a closure rather than on the component, because a wire put
// into Alpine's state is not the wire that comes back out. See the test below
// for what Alpine does to it.
test('the wire is held outside the component, where Alpine cannot reach it', () => {
    assert.match(source, /Alpine\.data\('bookletEditor', \(config = \{\}\) => \{[\s\S]*?let wire = null;[\s\S]*?return \{/);
    assert.ok(! /this\._wire/.test(source), 'the wire was put back on the component');
});

// Why it cannot be kept on the component, in the words of the two libraries
// themselves: Alpine's reactive setter runs toRaw() over everything assigned
// into a component's state, and Livewire's wire answers every property it does
// not recognise with a function that would call a method of that name on the
// server. So toRaw() asks the wire for __v_raw, is handed a function, takes it
// for the raw object behind the proxy, and stores that instead — leaving a stub
// with no saveOverride on it. Every override was then lost on its way to the
// database while the preview went on showing it.
test('a wire assigned into reactive state is not the wire that comes back', () => {
    // Alpine's toRaw, verbatim.
    const toRaw = (observed) => observed && toRaw(observed['__v_raw']) || observed;

    // Livewire's wire, as far as an unrecognised property is concerned.
    const wire = new Proxy({}, {
        get: (target, property) => (...params) => ({ called: property, params }),
    });

    assert.notEqual(toRaw(wire), wire);
    assert.equal(typeof toRaw(wire).saveOverride, 'undefined');
});

// The imposition is named in the request rather than performed in the browser:
// the pages go up in reading order at their own size whichever item of the menu
// was chosen, and the server nests them onto sheets, where the paper sizes
// already live. So the booklet is engraved once however it is to be printed.
test('the chosen imposition is sent with the pages, not applied to them', () => {
    assert.match(source, /async exportPdf\(imposition = 'full'\)/);
    assert.match(source, /body: JSON\.stringify\(\{ pages: svgs, imposition \}\)/);
});

// What the paper allows is the server's answer, pushed on every change, so that
// choosing A4 greys the two imposed items out and choosing A5 brings them back
// without the browser keeping an idea of paper sizes of its own.
test('what can be imposed is taken from the server, not worked out here', () => {
    assert.match(source, /impositions: config\.impositions \?\? \['full'\]/);
    assert.match(source, /if \(detail\.impositions\) \{ this\.impositions = detail\.impositions; \}/);
    assert.match(source, /imposes\(imposition\) \{\s*return this\.impositions\.includes\(imposition\);/);

    // And an item that is greyed out downloads nothing if it is clicked anyway.
    assert.match(source, /if \(!this\.imposes\(imposition\)\) \{ return; \}/);

    assert.ok(! /\b(148|210|297|105)\b/.test(source), 'a paper size was written into the editor');
});

// The paper travels the same way, for the name of the file that lands in the
// downloads folder: an A5 booklet and the A6 version of it are otherwise two
// files called the same thing.
test('the downloaded file is named for the paper it was engraved for', () => {
    assert.match(source, /pageSize: config\.pageSize \?\? ''/);
    assert.match(source, /if \(detail\.pageSize\) \{ this\.pageSize = detail\.pageSize; \}/);
    assert.match(source, /\+ \(this\.pageSize === '' \? '' : '\.' \+ this\.pageSize\)/);
});
