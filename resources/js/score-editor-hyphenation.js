/**
 * Hyphenating the lyrics selected in the score editor.
 *
 * The text is divided on the server, from the language's Hunspell dictionary;
 * this side only reads the selection out of whichever editor the format uses
 * and writes the answer back over it, as an edit that undo takes back in one
 * step.
 */

const LANGUAGE_STORAGE_KEY = 'score-editor.lyric-language';

export const LYRIC_LANGUAGES = ['hu', 'la'];

/** Chant is mostly Latin; everything else is mostly sung in Hungarian. */
export function defaultLyricLanguage(format, remembered = null) {
    if (LYRIC_LANGUAGES.includes(remembered)) {
        return remembered;
    }

    return format === 'gabc' ? 'la' : 'hu';
}

function rememberedLyricLanguage() {
    try {
        return window.localStorage.getItem(LANGUAGE_STORAGE_KEY);
    } catch {
        return null;
    }
}

function rememberLyricLanguage(language) {
    try {
        window.localStorage.setItem(LANGUAGE_STORAGE_KEY, language);
    } catch {
        // A private window keeps no choice; the default comes back next time.
    }
}

/**
 * The CodeMirror view behind an editor element, or null for a textarea.
 * `<aretino-editor>` has no public member for it.
 */
function codeMirrorView(editor) {
    return editor?.view ?? editor?._view ?? null;
}

/** The selected range and its text, or null when nothing is selected. */
export function readSelection(editor) {
    if (!editor) { return null; }

    const view = codeMirrorView(editor);
    const value = view ? view.state.doc.toString() : editor.value;
    const from = view ? view.state.selection.main.from : editor.selectionStart;
    const to = view ? view.state.selection.main.to : editor.selectionEnd;

    if (typeof from !== 'number' || to <= from) { return null; }

    return { from, to, text: value.slice(from, to) };
}

/**
 * Writes `text` over the range, leaving it selected. Fails, writing nothing,
 * when the range no longer holds what was selected.
 */
export function replaceSelection(editor, { from, to, text: expected }, text) {
    const view = codeMirrorView(editor);

    if (view) {
        if (view.state.sliceDoc(from, to) !== expected) { return false; }
        view.dispatch({
            changes: { from, to, insert: text },
            selection: { anchor: from, head: from + text.length },
            userEvent: 'input',
        });
        view.focus();
        return true;
    }

    if (editor.value.slice(from, to) !== expected) { return false; }
    editor.focus();
    editor.setSelectionRange(from, to);
    // insertText keeps the browser's undo history; setRangeText is the fallback.
    if (!globalThis.document?.execCommand?.('insertText', false, text)) {
        editor.setRangeText(text, from, to, 'end');
        editor.dispatchEvent(new Event('input', { bubbles: true }));
    }
    editor.setSelectionRange(from, from + text.length);
    return true;
}

export function hyphenationMixin() {
    return {
        lyricLanguage: 'hu',
        hyphenationSelection: null,
        hyphenating: false,
        hyphenationFailed: false,

        hyphenationEditor() {
            return {
                abc: this.$refs.abcEditor,
                aretino: this.$refs.aretinoEditor,
            }[this.$wire.format] ?? this.$refs.contentTextarea;
        },

        openHyphenation() {
            this.hyphenationSelection = readSelection(this.hyphenationEditor());
            this.hyphenationFailed = false;
            this.lyricLanguage = defaultLyricLanguage(this.$wire.format, rememberedLyricLanguage());
            this.$flux.modal('lyric-hyphenation').show();
        },

        async hyphenateSelection() {
            const selection = this.hyphenationSelection;
            if (!selection || this.hyphenating) { return; }

            this.hyphenating = true;
            this.hyphenationFailed = false;
            rememberLyricLanguage(this.lyricLanguage);

            try {
                const hyphenated = await this.$wire.hyphenateLyrics(selection.text, this.lyricLanguage);
                if (!replaceSelection(this.hyphenationEditor(), selection, hyphenated)) {
                    this.hyphenationFailed = true;
                    return;
                }
                this.$flux.modal('lyric-hyphenation').close();
            } catch (error) {
                console.error('[score-editor] hyphenation failed:', error);
                this.hyphenationFailed = true;
            } finally {
                this.hyphenating = false;
            }
        },
    };
}
