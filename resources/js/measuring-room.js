/**
 * Where the engravers measure, walled off from the page they are measured for.
 *
 * Both engines measure by drawing into the live document and reading back what
 * the browser made of it. abc2svg writes a font rule into a stylesheet for every
 * staff system it sets and sizes each lyric in a span styled by it; exsurge's
 * chant is dropped in whole, with a <style> of its own, so its lines can be
 * measured. Every one of those is a stylesheet change, and a stylesheet change
 * in the document is one the browser answers across the whole document — one of
 * abc2svg's rules is a bare `tspan{white-space:pre}`, which puts every lyric of
 * every page on screen up for restyling.
 *
 * On a booklet just opened there is nothing on screen and that costs nothing.
 * On a booklet laid out again, the pages being replaced are still there, so a
 * relayout was paying for the old booklet at every system of the new one — and
 * paying more each time, since abc2svg never takes a rule back out and every
 * render names its fonts afresh.
 *
 * A shadow root keeps it all inside: rules written there match only there, and
 * the room is laid out on its own. Fonts are the document's, so what is
 * measured is still what is drawn.
 *
 * And abc2svg's measuring is remembered, string by face, across renders: most of
 * the knobs on a booklet move things apart rather than change what they are set
 * in, and a relayout asked the browser for every syllable's width all over again.
 */

/** Wide enough for any page the booklet is laid out at. */
const ROOM_WIDTH_PX = 2400;

/** @type {{ outer: HTMLElement, host: HTMLElement, text: HTMLElement, styles: HTMLStyleElement } | null} */
let room = null;

/**
 * The room, built once and put back if the body it stood in was replaced — a
 * wire:navigate swaps the body, and the engines would go on measuring into a
 * detached element that answers zero for everything.
 */
function ensureRoom() {
    if (!room) {
        const outer = document.createElement('div');
        outer.setAttribute('aria-hidden', 'true');
        outer.style.cssText = `position:absolute;top:0;left:-10000px;width:${ROOM_WIDTH_PX}px;`
            + 'opacity:0;pointer-events:none;contain:strict;';

        const shadow = outer.attachShadow({ mode: 'open' });
        const styles = document.createElement('style');
        const host = document.createElement('div');
        const text = document.createElement('div');
        shadow.append(styles, host, text);

        room = { outer, host, text, styles };
    }

    if (!room.outer.isConnected) {
        document.body.appendChild(room.outer);
    }

    return room;
}

/** An element to draw a chant into and measure it, off-screen but laid out. */
export function measuringHost() {
    return ensureRoom().host;
}

/**
 * How many measured strings are remembered before the lot is dropped. A booklet
 * of thirty hymns measures a couple of thousand; this is many relayouts of one.
 */
const MEASURE_CACHE_LIMIT = 50000;

/** Width and height of a string in a face, as the browser measured them. */
const measured = new Map();

/** What each of abc2svg's font classes sets, read off the rules it writes. */
const fontOfClass = new Map();

/** The span abc2svg handed over, and what it is handed back in its place. */
let span = null;
let measurer = null;

/**
 * A face that finished loading changes what everything set in it measures, and
 * what was measured in the fallback before it arrived is wrong from then on.
 */
let fontsWatched = false;

function watchFonts() {
    if (fontsWatched) { return; }

    fontsWatched = true;
    document.fonts?.addEventListener?.('loadingdone', () => measured.clear());
}

/**
 * One class list, written as the declarations it stands for.
 *
 * abc2svg names its classes afresh for every tune it engraves — `.f3a57` is the
 * same 12 px serif as last render's `.f3a12` — so it is the declaration that says
 * whether a string was measured before, not the name. A class no rule was seen
 * for, such as `box`, stands for itself.
 */
function faceOf(classList) {
    return classList.split(/\s+/).filter(Boolean)
        .map((name) => fontOfClass.get(name) ?? name)
        .join('|');
}

/** Markup with the class names in it written out the same way. */
function markupKey(markup) {
    return markup.replace(/class="([^"]*)"/g, (match, classList) => `class="${faceOf(classList)}"`);
}

/**
 * Stands in for abc2svg's span, answering from memory what it has measured
 * before and asking the real span only what it has not.
 *
 * abc2svg measures one string at a time — sets the class, sets the text, reads
 * the width — and every read makes the browser lay the span out again. A booklet
 * asks that a couple of thousand times per layout, most of them the same few
 * syllables, and a knob that moves only the distances between things asks all
 * of them again at exactly the sizes they were before.
 */
function createMeasurer() {
    let className = '';
    let innerHTML = '';

    function measure() {
        const key = `${faceOf(className)}\u0000${markupKey(innerHTML)}`;
        let size = measured.get(key);

        if (!size) {
            span.className = className;
            span.innerHTML = innerHTML;
            size = [span.clientWidth, span.clientHeight];

            if (measured.size >= MEASURE_CACHE_LIMIT) { measured.clear(); }
            measured.set(key, size);
        }

        return size;
    }

    return {
        // abc2svg puts a span without a parent back onto the body.
        get parentElement() { return span.parentElement; },
        get className() { return className; },
        set className(value) { className = String(value); },
        get innerHTML() { return innerHTML; },
        set innerHTML(value) { innerHTML = String(value); },
        get clientWidth() { return measure()[0]; },
        get clientHeight() { return measure()[1]; },
    };
}

/**
 * The room's stylesheet, as abc2svg is given it: the rules still go in, so the
 * real span is styled when it is asked, and each lone class rule is noted on the
 * way past so the measurer can tell which face a class name stands for.
 *
 * @param {CSSStyleSheet} sheet
 * @param {string[]} noted the class names this engraving has written
 */
function recordingSheet(sheet, noted) {
    return {
        get cssRules() { return sheet.cssRules; },
        insertRule(rule, index) {
            const lone = /^\s*\.([\w-]+)\s*\{([^}]*)\}\s*$/.exec(rule);

            if (lone) {
                fontOfClass.set(lone[1], lone[2].trim());
                noted.push(lone[1]);
            }

            return sheet.insertRule(rule, index);
        },
        deleteRule(index) { return sheet.deleteRule(index); },
    };
}

/**
 * Run one abc2svg engraving with its text measured inside the room, and from
 * memory wherever it was measured before.
 *
 * abc2svg has a span to measure with only when the page made one before the
 * library loaded — without it the engine sizes text from its own tables, and
 * there is nothing here to move. The span is kept inside a wrapper because
 * abc2svg puts a span without a parent element back onto document.body.
 *
 * The rules the engraving wrote are taken out afterwards, and the class names
 * with them. With %%fullsvg every font class carries a name that engraving alone
 * uses, and abc2svg writes all of them again after each system it flushes, so
 * nothing later can want them.
 *
 * @template T
 * @param {() => T} engrave
 * @returns {T}
 */
export function withAbcMeasuring(engrave) {
    if (typeof abc2svg === 'undefined' || !abc2svg.el || typeof document === 'undefined') {
        return engrave();
    }

    const { text, styles } = ensureRoom();

    // A page that loads again makes a span of its own.
    if (!measurer || abc2svg.el !== measurer) {
        span = abc2svg.el;
        measurer ??= createMeasurer();
        abc2svg.el = measurer;
    }

    if (span.parentElement !== text) {
        text.appendChild(span);
    }

    watchFonts();

    const sheet = styles.sheet;
    const kept = sheet.cssRules.length;
    const noted = [];

    // A <style> abc2svg made for itself in the document before the room took
    // over; the room's own is handed over wrapped, and has no remove().
    abc2svg.styles?.remove?.();

    abc2svg.styles = { sheet: recordingSheet(sheet, noted) };

    try {
        return engrave();
    } finally {
        while (sheet.cssRules.length > kept) {
            sheet.deleteRule(sheet.cssRules.length - 1);
        }

        noted.forEach((name) => fontOfClass.delete(name));
    }
}
