# Psalm Tones: Fitting Hungarian Verses to a Gregorian Formula

## Context

A cantor who wants a psalm in a booklet today writes every verse out by hand:
which syllables go on the tenor, which on the cadence notes, where two notes share
a syllable. The rules are mechanical enough for the machine to do it, and the
syllables are already there (`LyricHyphenator::syllables()`).

The input is a tone and verses marked the usual way:

```
Uram, te lettél menedékünk, * nemzedékről nemzedékre.
```

The output is Aretino, ready to paste into a score:

```aretino
(g2) at b a g a | at g f gf d ||
w: Uram,~te~lettél me-ne-dé-künk, nemzedékről nem-ze-dék-re.
```

Hungarian only in the first version. Latin needs an accent dictionary, not
hyphenation, so it sits behind the same stress interface and comes later.

## Tone collections

There are more than eight tones. *Éneklő Egyház* has a Hungarian and a Roman
series, the Benedictine series may differ from both, and a tone has several
terminations (differentiae). So a tone always belongs to a **collection**, and
it is named by its mode and its final: `I.g`, `I.D`, `VIII.G`.

Each collection is one text file in `resources/psalm-tones/`. The files are
reference data kept under version control, so they are not stored in the
database. Each file is a valid Aretino score, so the whole collection can be
opened and checked in the score editor. The file `bences.aretino` looks like
this:

```
%collection: Bencés
%I.g
(Kb) f g | at g , at b- ao g- ao | at g f a- go ||
w: ~ ~ ~ ~ (+) ~ ~ ~ ~ ~ (*)

%II.d
(K###) e f | at f , at bo- f | at e f g- fo ||
w: ~ ~ ~ ~ (+) ~ ~ ~ (*)
```

The key signatures are deliberate: `(K###)` keeps every tenor on the `a` line.

- **Name comment:** `%` and the mode in Roman numerals, a dot, and the final.
  Other differentiae of the same mode are separate entries (`I.g`, `I.a`, …).
  Every formula line needs one before it.
- **Formula line:** an optional key signature (`(Kb)`, `(K###)`, `(K:…)`),
  then four parts separated by bar tokens that stand on their own:
  `initium | flexa , mediatio | terminatio ||`. A part may be empty.
- **Lyrics line (optional):** a `w:` line after the formula, with `~` for every
  note and `(+)`, `(*)` under the flexa and mediant bars. The tone preview on
  the screen shows it; without one the preview generates the same line.
- An accidental inside a part (`(#)g`) belongs to the slot that follows it.
- A malformed line throws an `InvalidArgumentException` with the file name and
  line number. A test parses every file shipped with the app.

The same line format is what the screen accepts as a **custom formula**, so an
ad-hoc termination needs no file edit.

## The tone

A tone has four formulas:

| Part | Sung on | Example (`Bencés I.g`) |
|---|---|---|
| `initium` | the first syllables of the first half | `f g` |
| `flexa` | the end of the part before `+` | `at g` |
| `mediatio` | the end of the first half, before `*` | `at b- a as g- as a` |
| `terminatio` | the end of the second half | `at g f a- gs g` |

The key signature from the line is written once at the start of the output. The
initium is sung on the first verse only. An `initiumEveryVerse` option covers
canticles, which sing it on every verse. The initium fills slots left to right,
one syllable each; on a verse that begins with a one-syllable word, stressed or
not, its notes are bent together onto that word (*Jöjj, …*, *Az Úr …*). The tenor of the following formula (flexa or mediatio) takes
over after it.

### The slots of a formula

A formula is split on spaces into **slots**. One slot is one note group, which
gets one syllable or none.

| Slot | Meaning |
|---|---|
| `at` | **tenor.** It takes every syllable left over, with the words joined by `~`. There is at most one tenor per formula. |
| `xo` | **open note.** It takes one to three syllables, like a short tenor, and is written as a closed note once for each of them. Bencés I.g: `at b- ao g- ao`, so *'A-tyá-nak-és 'Fi-ú-nak*. In a neume, the notes before the open note go with the first syllable and those after it with the last: `go-a` is sung `ga`, `g ga` or `g g ga`, an accent whose last note is always bent. |
| `x-` | **accent.** An accented syllable belongs here. |
| `xs` | **optional.** It takes a syllable only when there is one to spare, and is left out otherwise. |
| `xs-` | **optional accent.** When it is left out, its accent passes to the next slot. |
| `x`, `gf`, … | **fixed.** It takes exactly one syllable. A group of several notes puts them all on that one syllable. |

The `s` and `-` marks describe the formula. They are removed from the fitted
output, where a used optional note is an ordinary note.

How the 1st-tone terminatio fits each word shape:

- On a dactyl (`ÍS-te-ne`), `gs-` takes `Is`, `gf` takes `te` and `d` takes `ne`.
- On a trochee (`DÉK-re`), `gs-` is left out. Its accent passes to `gf`, which
  takes `dék`, and `d` takes `re`.

## Hungarian stress

`HungarianStress` gives every syllable an accent level and a weight:

- **Primary accent** falls on the first syllable of every word.
- **Secondary accent** falls on the 3rd, 5th, … syllables of a long word
  (`MÉ-ne-DÉ-künk`, `NEM-ze-DÉK-re`).
- **No accent** goes on articles and short particles (`a`, `az`, `s`, `és`,
  `meg` as a separate word, …), which come from a short list.
- **Weight:** a syllable is *long* when its vowel is long (`á é í ó ő ú ű`) or
  it ends in a consonant (`dék`, `künk`), and *short* otherwise (`ne`, `te`).
  A long syllable carries a two-note group easily.

Compound boundaries would place secondary accents better (`szent-lé-lek`). The
hyphenation dictionary breaks at them but does not say which breaks they are, so
the rules count odd syllables only. `HungarianStress::COMPOUNDS` lists the
compounds of the liturgy (for now `Szentlélek` in every form) and gives their
second part a secondary stress, so a formula may accent either part. Overrides
cover what the rules still miss:

- `·` before a syllable gives it a secondary stress: `mind·örökké`.
- `'` before a syllable forces an accent: `szent'lélek`.
- A word hyphenated by hand (`Is-te-nünk`) is taken as written.

## Fitting

Each half-verse is fitted on its own, right to left, because the cadence is fixed
at the end and the tenor absorbs whatever is left at the start. An exhaustive
search finds the cheapest alignment of slots to syllables. A formula has few
slots, so this is cheap.

The moves:

1. A fixed slot takes the next syllable.
2. An optional slot takes the next syllable, or is skipped.
3. A slot **merges** onto the syllable of its neighbour, and its notes join that
   syllable's group. This is how a half-verse ending on an accented monosyllable
   (`…az Úr`) still gets the notes after the accent.
4. The tenor takes all remaining syllables, possibly none.

Costs (starting values, to be tuned against real psalters in the tests):

| Situation | Cost |
|---|---|
| Accent slot on an unaccented syllable | 10 |
| Accent slot on a secondary accent | 1 |
| Non-accent, non-tenor slot on a primary accent | 3 |
| Merge onto a long syllable | 5 |
| Merge onto a short syllable | 8 |
| A group of several notes on a short syllable | 1 |
| Empty tenor | 100 (the tenor is always sung; empty only when nothing else fits) |
| Optional slot used | 0.1 (when costs are otherwise equal, leave it out) |
| Each syllable an open note takes beyond its first | 0.5, plus the stress cost of that syllable |
| The third syllable of an open note | +0.75 (three syllables on one open note are rarely right; still cheaper than a merge) |
| A primary stress inside an open note rather than at its start | 0.5 |

The cheapest alignment wins. On a tie, the alignment that puts more syllables on
the tenor wins.

### Worked examples (these become the first tests)

Mediatio `at b- as a g- as a` on `Ú-ram te let-tél MÉ-ne-DÉ-künk`:
`a`←künk, `as` skipped, `g-`←dé, `a`←ne, `as` skipped, `b-`←me, tenor ← *Uram te
lettél*.

Terminatio `at g f gs- gf d` on `NEM-ze-DÉK-ről NEM-ze-DÉK-re`:
`d`←re, `gf` (with the accent passed on from the skipped `gs-`)←dék, `f`←ze,
`g`←nem, tenor ← *nemzedékről*.

## Shape of the code

All in `app/Services`, which has no subfolders:

- `PsalmTone`: a readonly value object holding the name, the line, the key
  signature and the four formulas, built by `fromLine()`.
- `PsalmToneSlot`: one note group of a formula (`notes`, `isTenor`, `isAccent`,
  `isOptional`, `noteCount`).
- `PsalmToneLibrary`: reads `resources/psalm-tones/*.aretino`. It offers
  `collections()`, `tone()` and the static `parse()`.
- `HungarianStress`: turns a half-verse into `list<PsalmSyllable>` (text, word
  index, `App\Enums\SyllableStress`, `isLong`), using
  `LyricHyphenator::syllables()`.
- `PsalmFormulaFitter`: the search over settings. It returns the cost and the
  placements.
- `PsalmToneSetter`: the entry point. It splits the verses on `+`, `*` and line
  ends, fits each part, and writes the Aretino melody and `w:` lines. The `*` in
  the text becomes a `|` bar in the melody and the `+` (or `†`) a `,`. In the
  `w:` line they are written as `(*)` and `(+)`, which the renderer sets under
  the bar. The last note before each bar is dotted (`g.`). A flexa mark is
  dropped when the tone has no flexa. Each verse is a block of its own, and every
  block after the first starts with `(Z)`. When syllables of one word share a
  note, a doubled digraph is spelt as in the word (`vissza`, not `viszsza`).

## The screen

The tone setter has its own Livewire page, `App\Livewire\Pages\PsalmTones` at `/psalm-tones` (`psalm-tones`), which
is open to guests like `/score/preview`.

- **Left side:**
  - a collection select;
  - a tone select, with the chosen tone's formula rendered as a small score;
  - a "custom formula" input that takes the line format and overrides the select;
  - a textarea for the verses, marked with `*` and `+`;
  - the `initiumEveryVerse` option.
- **Right side:** the result rendered by the Aretino renderer the guide page
  already uses, the Aretino source beneath it with a copy button, and
  **Open in score editor**, which links to `/score/preview?d=…`, built by
  `ScoreEditor::shareUrl()`, the helper the editor's own share link now uses.
- The result is recomputed on a debounced change of the text or the tone.
  Fitting is cheap, so it runs on the server on every change.
- Syllables the fitter was unsure of (the cheapest alignment still cost more
  than a threshold) are listed under the result with the verse they are in, so
  the user knows where a `'` override may be needed.

## Tests

- `tests/Feature/PsalmToneSetterTest.php` uses datasets of
  *(formula, half-verse) → expected Aretino*. The examples above come first, then
  a set of verses checked against a printed Hungarian psalter, which is how the
  costs get tuned.
- `HungarianStress` gets its own dataset: accent levels and weights per word.
- `PsalmToneLibraryTest` parses every shipped collection file and checks the
  error report on a malformed line.
- A Livewire test of the page: choosing a tone and typing a verse yields the
  expected source.

## Later

- The *Éneklő Egyház* Hungarian and Roman series as further collection files.
- More liturgical compounds, or general compound-aware secondary accents.
- Latin accents.
- Inserting a fitted psalm straight into a music plan or booklet.
