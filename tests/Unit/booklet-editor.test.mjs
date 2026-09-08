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
    assert.match(source, /init\(\)\s*\{[\s\S]*?this\._wire = this\.\$wire;/);
    assert.ok(/this\._wire\.saveOverride\(/.test(source));
    assert.ok(/this\._wire\.resetOverride\(/.test(source));
});
