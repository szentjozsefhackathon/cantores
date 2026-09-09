/**
 * Cloudflare's api.js renders every `.cf-turnstile` placeholder once, when the script
 * itself loads. Livewire's wire:navigate swaps the page body without re-running an
 * already loaded script, so the placeholder on the page we navigate to (login → register,
 * for instance) stays empty, no hidden cf-turnstile-response input is created, and the
 * form posts without a token. Render those leftover placeholders after every navigation.
 */

/**
 * Widget options that Turnstile reads from data attributes on implicit rendering,
 * mapped from their dataset key to the explicit render() parameter name.
 *
 * @type {Record<string, string>}
 */
const OPTION_ATTRIBUTES = {
    sitekey: 'sitekey',
    action: 'action',
    cdata: 'cData',
    theme: 'theme',
    size: 'size',
    language: 'language',
    appearance: 'appearance',
    retry: 'retry',
    retryInterval: 'retry-interval',
    refreshExpired: 'refresh-expired',
    responseFieldName: 'response-field-name',
    tabindex: 'tabindex',
};

/**
 * Options naming a global function rather than carrying a value.
 *
 * @type {Record<string, string>}
 */
const CALLBACK_ATTRIBUTES = {
    callback: 'callback',
    expiredCallback: 'expired-callback',
    timeoutCallback: 'timeout-callback',
    errorCallback: 'error-callback',
    beforeInteractiveCallback: 'before-interactive-callback',
    afterInteractiveCallback: 'after-interactive-callback',
    unsupportedCallback: 'unsupported-callback',
};

/**
 * Rebuild the render() parameters from the data attributes the Blade component wrote.
 */
export function turnstileOptionsFrom(widget, scope = globalThis) {
    const dataset = widget.dataset ?? {};
    const options = {};

    for (const [key, option] of Object.entries(OPTION_ATTRIBUTES)) {
        if (dataset[key] !== undefined) {
            options[option] = dataset[key];
        }
    }

    for (const [key, option] of Object.entries(CALLBACK_ATTRIBUTES)) {
        const handler = dataset[key] !== undefined ? scope[dataset[key]] : undefined;
        if (typeof handler === 'function') {
            options[option] = handler;
        }
    }

    return options;
}

/**
 * Render every placeholder that has no widget in it yet. Returns how many were rendered.
 */
export function renderPendingTurnstileWidgets(doc = globalThis.document, turnstile = globalThis.turnstile, scope = globalThis) {
    if (! doc || ! turnstile || typeof turnstile.render !== 'function') {
        return 0;
    }

    let rendered = 0;

    for (const widget of doc.querySelectorAll('.cf-turnstile')) {
        if (widget.dataset?.turnstileRendered === 'true' || widget.querySelector?.('iframe')) {
            continue;
        }

        if (widget.dataset) {
            widget.dataset.turnstileRendered = 'true';
        }

        turnstile.render(widget, turnstileOptionsFrom(widget, scope));
        rendered += 1;
    }

    return rendered;
}

/**
 * Listen for Livewire navigations. The first event is the initial page load, where
 * api.js does its own implicit rendering, so it is skipped to avoid a double widget.
 */
export function listenForNavigation(target = globalThis.document) {
    if (! target || typeof target.addEventListener !== 'function') {
        return;
    }

    let initialLoadSeen = false;

    target.addEventListener('livewire:navigated', () => {
        if (! initialLoadSeen) {
            initialLoadSeen = true;

            return;
        }

        renderPendingTurnstileWidgets();
    });
}

listenForNavigation();
