<?php

use App\Support\BookletStyles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The staff-to-lyrics gap, and the gap between stacked lyric lines, become the
 * booklet's rather than each author's.
 *
 * A booklet has imposed its face on every score it prints since it had one, and
 * left the spacing that face needs to whichever author engraved the score. That
 * was half a decision: the two numbers are exactly the ones a face makes right
 * or wrong, and three faces genuinely want three different balances — judged by
 * eye rather than derived, which is why they are stored per style rather than
 * computed. See App\Support\BookletStyles.
 *
 * Existing booklets are backfilled from the face they are already set in, so a
 * booklet in Alegreya comes out at the hymnal's numbers and one in EB Garamond
 * at the graduale's. A booklet set in a face no style claims — Inter or Barlow
 * Condensed — keeps its face and takes the default's spacing under it.
 *
 * This does change how existing booklets print: a gap a cantor tuned in the
 * score editor and saw in their booklet is now the booklet's, until they say
 * otherwise on that score's own row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = BookletStyles::defaults(BookletStyles::DEFAULT);

        Schema::table('booklets', function (Blueprint $table) use ($columns): void {
            $table->float('abc_lyric_first_skip')->default($columns['abc_lyric_first_skip']);
            $table->float('abc_lyric_skip')->default($columns['abc_lyric_skip']);
        });

        foreach (BookletStyles::keys() as $style) {
            $defaults = BookletStyles::defaults($style);

            DB::table('booklets')
                ->whereIn('text_font', $this->fontsFor($style))
                ->update([
                    'abc_lyric_first_skip' => $defaults['abc_lyric_first_skip'],
                    'abc_lyric_skip' => $defaults['abc_lyric_skip'],
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table): void {
            $table->dropColumn(['abc_lyric_first_skip', 'abc_lyric_skip']);
        });
    }

    /**
     * The faces this style claims — its own, plus, for the default, every face
     * no style claims at all.
     *
     * @return list<string>
     */
    private function fontsFor(string $style): array
    {
        $fonts = DB::table('booklets')->distinct()->pluck('text_font')->all();

        return array_values(array_filter(
            array_map(fn ($font): string => (string) $font, $fonts),
            fn (string $font): bool => BookletStyles::forFont($font) === $style,
        ));
    }
};
