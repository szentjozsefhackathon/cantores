/**
 * Text measured the same way on every screen.
 *
 * Every screen engraves a deck for itself, and must cut it into the same slides
 * as every other screen showing it: the deck's ratio is the one thing they all
 * share, so the same rules at that ratio have to come to the same answer. What
 * kept them apart was the measuring. A browser reports a word's ink, and in some
 * places its width, in whole pixels at the size it is asked about, and rounds
 * differently on different systems: at a lyric's 67 px a pixel is two per cent
 * of a letter, and the same Kyrie came to a page three units taller on Android
 * than on Windows. A page a few units short of the slide was then one slide on
 * one screen and two on the next.
 *
 * So every word is measured at MEASURE_REFERENCE_PX and scaled to the size it
 * is set in. The rounding is then under a tenth of a pixel at the size set, and
 * Windows, Android and Linux measured that Kyrie to the same thousandth.
 */
export const MEASURE_REFERENCE_PX = 1000;

const PX_SIZE = /(\d*\.?\d+(?:e[+-]?\d+)?)px/i;

/**
 * A CSS font, restated at the reference size, with the factor that takes what
 * is measured in it back to the size it named. A font named in anything but
 * pixels is left as it is.
 *
 * @param {string} font
 * @return {{font: string, scale: number}}
 */
export function referenceFont(font) {
    const value = String(font ?? '');
    const match = PX_SIZE.exec(value);
    const size = match ? Number(match[1]) : NaN;

    if (!(size > 0)) { return { font: value, scale: 1 }; }

    return {
        font: value.slice(0, match.index) + `${MEASURE_REFERENCE_PX}px` + value.slice(match.index + match[0].length),
        scale: size / MEASURE_REFERENCE_PX,
    };
}

const METRICS = [
    'width',
    'actualBoundingBoxLeft',
    'actualBoundingBoxRight',
    'actualBoundingBoxAscent',
    'actualBoundingBoxDescent',
    'fontBoundingBoxAscent',
    'fontBoundingBoxDescent',
];

/**
 * A 2D context that measures at the reference size and answers at the size it
 * was asked about, and does everything else exactly as the context it wraps.
 *
 * The font it is given is the font it draws in; only measureText steps up to
 * the reference size, and back. So it can stand in for a context an engine
 * also draws with.
 *
 * @param {CanvasRenderingContext2D} context
 * @return {CanvasRenderingContext2D}
 */
export function referenceContext(context) {
    return new Proxy(context, {
        get(target, property) {
            if (property === 'measureText') {
                return (text) => {
                    const asked = target.font;
                    const { font, scale } = referenceFont(asked);

                    target.font = font;

                    try {
                        const measured = target.measureText(text);
                        const scaled = {};

                        METRICS.forEach((metric) => {
                            if (metric in measured) { scaled[metric] = measured[metric] * scale; }
                        });

                        return scaled;
                    } finally {
                        target.font = asked;
                    }
                };
            }

            const value = Reflect.get(target, property, target);

            return typeof value === 'function' ? value.bind(target) : value;
        },
        set(target, property, value) {
            return Reflect.set(target, property, value, target);
        },
    });
}

let sharedContext = null;

/**
 * One measuring context for the whole page, or null where there is no canvas.
 *
 * @return {CanvasRenderingContext2D|null}
 */
export function measuringContext() {
    if (sharedContext) { return sharedContext; }
    if (typeof document === 'undefined') { return null; }

    const context = document.createElement('canvas').getContext('2d');

    sharedContext = context ? referenceContext(context) : null;

    return sharedContext;
}
