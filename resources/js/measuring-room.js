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

import { tableAscent } from './font-ascents.js';
import { MEASURE_REFERENCE_PX, referenceContext } from './reference-measure.js';

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

/**
 * One abc2svg engraving with its words measured at the reference size.
 *
 * abc2svg measures every word by writing it into a span and reading the span's
 * clientWidth and clientHeight — whole pixels, at the size the word is set in,
 * rounded by each browser its own way. A slide is cut from what that comes to,
 * and every screen showing the deck must cut it the same way; so for as long as
 * the engraving runs the span is stood in for by one that measures at the
 * reference size, fractionally, and scales back. See reference-measure.js.
 *
 * Not inside the booklet's room, which has its own measurer for its own reasons
 * and is left as it is.
 *
 * @template T
 * @param {() => T} engrave
 * @returns {T}
 */
export function withReferenceMeasuring(engrave) {
    if (typeof abc2svg === 'undefined' || !abc2svg.el || typeof document === 'undefined' || abc2svg.el === measurer) {
        return engrave();
    }

    const real = abc2svg.el;
    const createElement = document.createElement;

    abc2svg.el = referenceSpan(real);

    // The fork hangs its first lyric line from each syllable's ink, which it
    // asks of a canvas of its own making — rounded by the browser to 1/64 of
    // the size, each system its own way. For as long as the engraving runs, a
    // canvas it makes answers from the font files instead.
    document.createElement = function (tagName, ...rest) {
        const element = createElement.call(this, tagName, ...rest);

        return String(tagName).toLowerCase() === 'canvas' ? withTableAscents(element) : element;
    };

    try {
        return engrave();
    } finally {
        abc2svg.el = real;
        document.createElement = createElement;
    }
}

/**
 * A canvas whose 2D context measures widths at the reference size and ink
 * heights from the font files — see font-ascents.js — falling back to the
 * browser for a face or a letter the table does not have.
 *
 * @param {HTMLCanvasElement} canvas
 */
function withTableAscents(canvas) {
    const getContext = canvas.getContext.bind(canvas);

    canvas.getContext = (type, ...rest) => {
        const context = getContext(type, ...rest);

        if (type !== '2d' || !context) { return context; }

        const measuring = referenceContext(context);

        return new Proxy(measuring, {
            get(target, property) {
                if (property !== 'measureText') { return Reflect.get(target, property); }

                return (text) => {
                    const metrics = target.measureText(text);
                    const font = cssFontParts(target.font);
                    const ascent = font === null ? null : tableAscent(text, font.size, font.family, font.bold, font.italic);

                    return ascent === null ? metrics : { ...metrics, actualBoundingBoxAscent: ascent };
                };
            },
            set(target, property, value) {
                return Reflect.set(target, property, value);
            },
        });
    };

    return canvas;
}

/**
 * A CSS font shorthand taken apart: whether it is bold or italic, its size in
 * pixels and its family. Null for one that names no pixel size.
 *
 * @param {string} font
 * @return {{bold: boolean, italic: boolean, size: number, family: string}|null}
 */
export function cssFontParts(font) {
    const match = /^(.*?)(\d*\.?\d+)px(?:\s*\/\s*\S+)?\s+(.+)$/.exec(String(font ?? '').trim());

    if (!match) { return null; }

    const modifiers = match[1].toLowerCase().split(/\s+/).filter(Boolean);

    return {
        bold: modifiers.some((word) => word === 'bold' || word === 'bolder' || Number(word) >= 600),
        italic: modifiers.some((word) => word === 'italic' || word === 'oblique'),
        size: Number(match[2]),
        family: match[3],
    };
}

/**
 * What abc2svg is handed in place of its span: the same span, asked at the
 * reference size. The last answer is kept, since abc2svg reads the width and
 * then the height of the same word.
 *
 * @param {HTMLElement} span
 */
function referenceSpan(span) {
    let className = span.className;
    let innerHTML = span.innerHTML;
    let lastKey = null;
    let lastSize = null;

    function measure() {
        const key = `${className}\u0000${innerHTML}`;

        if (key !== lastKey) {
            span.className = className;
            span.innerHTML = innerHTML;
            lastSize = sizeAtReference(span, innerHTML);
            lastKey = key;
        }

        return lastSize;
    }

    return {
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
 * A span's width and height as they would be at the reference size, scaled to
 * the size it is set in.
 *
 * Plain words only. Markup inside the span may carry sizes of its own, which a
 * size set on the span would not move, so it is measured as abc2svg always
 * measured it.
 *
 * @param {HTMLElement} span
 * @param {string} innerHTML
 * @return {[number, number]}
 */
function sizeAtReference(span, innerHTML) {
    const style = getComputedStyle(span);
    const size = parseFloat(style.fontSize);

    if (innerHTML.includes('<') || !(size > 0)) { return [span.clientWidth, span.clientHeight]; }

    // A line height the page set in pixels would stay put while the letters
    // grew; restated as a ratio, it grows with them.
    const lineHeight = parseFloat(style.lineHeight);
    const asked = { fontSize: span.style.fontSize, lineHeight: span.style.lineHeight };

    if (lineHeight > 0) { span.style.lineHeight = String(lineHeight / size); }

    span.style.fontSize = `${MEASURE_REFERENCE_PX}px`;

    const box = span.getBoundingClientRect();

    span.style.fontSize = asked.fontSize;
    span.style.lineHeight = asked.lineHeight;

    const scale = size / MEASURE_REFERENCE_PX;

    return [box.width * scale, box.height * scale];
}
