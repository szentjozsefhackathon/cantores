const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE);

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        const html = fs.readFileSync(0, 'utf8');
        for (const dark of [false, true]) {
            for (const width of [320, 390, 768, 1024, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                await page.setContent(html);
                await page.evaluate(dark => document.documentElement.classList.toggle('dark', dark), dark);
                const layout = await page.evaluate(() => {
                    const sidebar = document.querySelector('aside');
                    const content = sidebar.nextElementSibling;
                    const sized = element => element && element.getClientRects().length > 0;
                    const rect = element => element.getBoundingClientRect();
                    const controls = [...document.querySelectorAll('header a, footer button')].filter(element => element.getClientRects().length);
                    const title = document.querySelector('h1');
                    const byline = document.querySelector('header a[href*="/author"]');
                    const genre = document.querySelector('header [data-music-genre]');
                    const incipit = document.querySelector('header img[x-ref="incipitTrigger"]');
                    const privateScores = document.querySelector('[data-own-scores]');
                    const collections = document.querySelector('[data-collections]');
                    const shell = document.querySelector('[data-page-shell]');
                    return {
                        overflow: document.documentElement.scrollWidth > innerWidth + 1,
                        headings: document.querySelectorAll('h1').length,
                        incipitNearTitle: incipit && rect(incipit).top >= rect(title).bottom && rect(incipit).top - rect(title).bottom < 240,
                        bylineOrder: rect(byline).top >= rect(title).bottom
                            && rect(genre).top >= rect(title).bottom
                            && rect(incipit).top >= rect(genre).bottom,
                        genreIsSmall: rect(genre).height <= 28,
                        thumbnails: [...document.querySelectorAll('header a img, aside a img')].filter(image => rect(image).width > 0 && rect(image).height > 0).length,
                        hasResourceColumn: sized(content),
                        beside: sized(content) && rect(sidebar).right <= rect(content).left,
                        below: sized(content) && rect(content).top >= rect(sidebar).bottom,
                        ownContentLast: rect(privateScores).top >= rect(sidebar).bottom && (! sized(content) || rect(privateScores).top >= rect(content).bottom),
                        collectionsBoxed: rect(sidebar).width <= 480,
                        collectionsScrollFree: collections.scrollHeight <= collections.clientHeight + 1,
                        tables: document.querySelectorAll('table').length,
                        shellWidth: rect(shell).width,
                        linksVisible: [...document.querySelectorAll('a[href^="https://example.com"]')].every(element => rect(element).bottom < 1000),
                        controlsFit: controls.every(element => rect(element).left >= 0 && rect(element).right <= innerWidth),
                    };
                });
                assert.equal(layout.overflow, false, `Overflow at ${width}px, dark=${dark}`);
                assert.equal(layout.headings, 1, 'One primary music title');
                assert.equal(layout.incipitNearTitle, true, 'Incipit identifies the music alongside its title');
                assert.equal(layout.bylineOrder, true, 'Title, then who wrote it and its genre, then the notes it starts with');
                assert.equal(layout.genreIsSmall, true, 'Genre stays a compact chip');
                assert.equal(layout.thumbnails, 2, 'Author portrait and collection cover are visible');
                assert.equal(layout.ownContentLast, true, 'The viewer\'s own scores come after the shared information');
                if (width >= 640) {
                    assert.equal(layout.collectionsBoxed, true, 'Collections stay in a box instead of spanning the page');
                }
                assert.equal(layout.collectionsScrollFree, true, 'The collection list scrolls with the page, not inside itself');
                assert.equal(layout.tables, 0, 'Scores are cards, not table rows');
                assert.equal(layout.shellWidth <= 896, true, `Page content keeps the chrome's width at ${width}px`);
                assert.equal(layout.controlsFit, true, 'Actions stay inside the viewport');
                if (layout.hasResourceColumn) {
                    assert.equal(width >= 1024 ? layout.beside : layout.below, true, `Collections outrank the other resources at ${width}px`);
                    if (width >= 1024) {
                        assert.equal(layout.linksVisible, true, 'Resources are visible without scrolling on desktop');
                    }
                }
                if (process.env.MUSIC_VIEW_SCREENSHOTS && [390, 1440].includes(width)) {
                    await page.screenshot({ path: `${process.env.MUSIC_VIEW_SCREENSHOTS}/music-view-${process.env.MUSIC_VIEW_SCENARIO || 'full'}-${width}-${dark ? 'dark' : 'light'}.png`, fullPage: true });
                }
            }
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
