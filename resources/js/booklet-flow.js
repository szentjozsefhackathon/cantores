/**
 * Filling pages.
 *
 * A booklet flows: several short scores share a sheet, and a long one runs over
 * onto the next. That is only possible because every renderer here can be made
 * to emit its music one staff line at a time — abc2svg does it natively, Aretino
 * has splitRowSVGs, exsurge's chant lines can be measured apart — so a score
 * arrives as a list of blocks rather than one indivisible picture.
 *
 * This module knows nothing about SVG. A block is a height and a couple of flags,
 * which is all packing needs and all that can be tested without a browser.
 *
 * @typedef {object} Block
 * @property {number} height    in the same px units the page is measured in
 * @property {number} [spaceBefore] leading to add when it is not first on a page
 * @property {boolean} [keepWithNext] a title, which must not end a page alone
 * @property {boolean} [startsScore] first block of a score
 * @property {boolean} [breakBefore] the score asked to start a fresh page
 * @property {*} [payload] whatever the caller needs to draw it
 */

/**
 * Pack blocks onto pages of a fixed height.
 *
 * @param {Block[]} blocks
 * @param {number} contentHeight
 * @returns {Array<{items: Array<{block: Block, y: number}>, height: number}>}
 */
export function packPages(blocks, contentHeight) {
    const pages = [];
    let current = newPage();

    const flush = () => {
        if (current.items.length > 0) {
            pages.push(current);
        }
        current = newPage();
    };

    for (let i = 0; i < blocks.length; i++) {
        const block = blocks[i];
        const isFirstOnPage = current.items.length === 0;

        if (block.breakBefore && !isFirstOnPage) {
            flush();
        }

        // A run of keep-with-next blocks and the block they belong to move as
        // one, so a page never ends on a title whose music is overleaf.
        const group = groupFrom(blocks, i);
        const groupHeight = measureGroup(group, current.items.length === 0);

        if (current.items.length > 0 && current.height + groupHeight > contentHeight) {
            flush();
        }

        placeGroup(current, group);
        i += group.length - 1;
    }

    flush();

    return pages;
}

/**
 * A block plus everything glued to it by keepWithNext.
 *
 * A group that is taller than any page still has to go somewhere, so nothing
 * here refuses to place one — it lands on a page of its own and overflows, which
 * the caller can see and the reader can at least read.
 */
function groupFrom(blocks, start) {
    const group = [blocks[start]];

    let i = start;
    while (blocks[i]?.keepWithNext && blocks[i + 1]) {
        group.push(blocks[i + 1]);
        i++;
    }

    return group;
}

function measureGroup(group, atPageTop) {
    return group.reduce(
        (total, block, i) => total + block.height + leading(block, atPageTop && i === 0),
        0,
    );
}

function placeGroup(page, group) {
    group.forEach((block) => {
        page.height += leading(block, page.items.length === 0);
        page.items.push({ block, y: page.height });
        page.height += block.height;
    });
}

/**
 * Space above a block — dropped when it opens a page, so scores do not float
 * down from the top margin by however much separated them mid-page.
 */
function leading(block, atPageTop) {
    return atPageTop ? 0 : (block.spaceBefore ?? 0);
}

function newPage() {
    return { items: [], height: 0 };
}
