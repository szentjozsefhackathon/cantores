const CONDITIONAL_BLOCK_RATIO_SUFFIXES = { '16/9': '169', '4/3': '43', '1/1': '11' };
const KNOWN_CONDITIONAL_SUFFIXES = new Set(Object.values(CONDITIONAL_BLOCK_RATIO_SUFFIXES));

export function applyConditionalBlocks(content, ratio) {
    const targetSuffix = CONDITIONAL_BLOCK_RATIO_SUFFIXES[ratio];
    return content.replace(/%\[(\S+)(\s)([\s\S]*?)%\]/g, (match, condition, sep, inner) => {
        if (!KNOWN_CONDITIONAL_SUFFIXES.has(condition)) { return match; }
        if (condition !== targetSuffix) { return match; }
        // Replace %[ and condition with spaces, keep sep (preserves newlines),
        // keep inner unchanged, replace %] with spaces — total char count identical.
        return ' '.repeat(2 + condition.length) + sep + inner + '  ';
    });
}
