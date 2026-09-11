const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE);

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        const html = fs.readFileSync(0, 'utf8');
        for (const width of [320, 390, 639, 640, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.setContent(html);
            await page.evaluate(() => document.querySelector('dialog')?.showModal());
            const measurements = await page.evaluate(() => {
                const modal = document.querySelector('dialog[open]') || document.querySelector('main');
                const table = modal.querySelector('table');
                const buttons = [...table.querySelectorAll('tbody button, tbody a[data-flux-button]')].filter(button => button.getClientRects().length);
                const rect = element => element.getBoundingClientRect();
                const selectionCells = [...table.querySelectorAll('tbody tr')]
                    .map(row => row.querySelector('[data-flux-checkbox]')?.closest('td'))
                    .filter(cell => cell && cell.getClientRects().length);
                return {
                    selectionWidths: selectionCells.map(cell => rect(cell).width),
                    overflow: [modal, table, table.parentElement].some(element => element.scrollWidth > element.clientWidth + 1),
                    buttonCount: buttons.length,
                    contained: buttons.every(button => {
                        const cell = button.closest('td');
                        const bounds = rect(button);
                        return bounds.left >= rect(cell).left && bounds.right <= rect(cell).right + 1
                            && bounds.right <= rect(modal).right && bounds.left >= rect(modal).left;
                    }),
                    overlap: buttons.some(button => [...button.closest('tr').querySelectorAll('td')]
                        .filter(cell => cell !== button.closest('td') && cell.getClientRects().length)
                        .some(cell => rect(cell).right > rect(button).left + 1)),
                    iconOnly: buttons.every(button => button.textContent.trim() === '' && (button.getAttribute('aria-label') || button.getAttribute('title'))),
                };
            });
            if (html.includes('toggleSelection') && width >= 640) {
                assert.ok(measurements.selectionWidths.length > 0, 'Fixture has visible selection checkboxes');
            }
            assert.ok(measurements.selectionWidths.every(width => width <= 56), `Selection column wastes space at ${width}px: ${measurements.selectionWidths}`);
            assert.ok(measurements.buttonCount > 0, 'Fixture has action buttons');
            assert.equal(measurements.overflow, false, `Horizontal overflow at ${width}px`);
            assert.equal(measurements.contained, true, `Button outside its column at ${width}px`);
            assert.equal(measurements.overlap, false, `Button overlaps another column at ${width}px`);
            assert.equal(measurements.iconOnly, true, 'Buttons have an icon and accessible label');
            if (process.env.MUSIC_SEARCH_SCREENSHOTS) {
                await page.screenshot({ path: `${process.env.MUSIC_SEARCH_SCREENSHOTS}/music-search-${width}.png` });
            }
        }
        console.log('Music search layout passed at all 7 viewport widths.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
