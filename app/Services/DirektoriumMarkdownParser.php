<?php

namespace App\Services;

class DirektoriumMarkdownParser
{
    private const COLORS = ['viola', 'fehér', 'piros', 'zöld', 'rózsaszín'];

    private const DAY_ABBREVIATIONS = ['VAS', 'VA', 'HÉ', 'KE', 'SZE', 'CSÜ', 'CS', 'PÉ', 'SZO'];

    /**
     * @return array{
     *     celebration_title: ?string,
     *     liturgical_color: ?string,
     *     funeral_mass_code: ?string,
     *     votive_mass_code: ?string,
     *     rank_code: ?string,
     *     is_pro_populo: bool,
     *     is_penitential: bool,
     *     fast_level: int,
     *     zsolozsma_week: ?string,
     *     cleaned_markdown: string,
     * }
     */
    public function parse(string $markdown): array
    {
        $result = [
            'celebration_title' => null,
            'liturgical_color' => null,
            'funeral_mass_code' => null,
            'votive_mass_code' => null,
            'rank_code' => null,
            'is_pro_populo' => false,
            'is_penitential' => false,
            'fast_level' => 0,
            'zsolozsma_week' => null,
            'cleaned_markdown' => $markdown,
        ];

        $cleaned = $markdown;

        // === PREPROCESSING ===

        // Remove # headings containing "idő" (PDF artifacts like "# NAGYBÖJTI IDŐ – MÁRCIUS")
        $cleaned = preg_replace('/^#{1,6}\s+.*(?:idő|IDŐ).*$/mu', '', $cleaned);

        // Fix "Minden" missing space after the word (PDF extraction artifact)
        // e.g. "Mindenadventi" → "Minden adventi", "MindenUrunk" → "Minden Urunk"
        // Handles both uppercase and lowercase Minden/minden, skips known Hungarian compounds
        $cleaned = preg_replace(
            '/\b(M|m)(inden)(?!ki|ek|en\b|es|t\b|hol|ütt|féle|nap|kor|képp|ható|ség|nel|ből|ről|nek|ben|be\b|re\b|hez)([a-záéíóöőúüűA-ZÁÉÍÓÖŐÚÜŰ])/u',
            '$1$2 $3',
            $cleaned
        );

        // Join split bold uppercase title lines (PDF artifact)
        // **URUNK SZÜLETÉSÉNEK GY1 V0**\n**HÍRÜLADÁSA...** → **URUNK SZÜLETÉSÉNEK GY1 V0 HÍRÜLADÁSA...**
        $cleaned = preg_replace(
            '/(\*\*[A-ZÁÉÍÓÖŐÚÜŰ][A-ZÁÉÍÓÖŐÚÜŰ0-9\s.,\-–—\/()†]+)\*\*\s*\n\s*\*\*([A-ZÁÉÍÓÖŐÚÜŰ][A-ZÁÉÍÓÖŐÚÜŰ0-9\s.,\-–—\/()†]+\*\*)/u',
            '$1 $2',
            $cleaned
        );

        // Remove GY/V codes from evening mass heading – they would be misidentified as the day's main codes
        // e.g. "*viola* **Esti szentmise: GY0 V0**" → "*viola* **Esti szentmise:**"
        $cleaned = preg_replace(
            '/(\*\*Esti szentmise:?)\s+GY[012]\s+V[012]\*\*/u',
            '$1**',
            $cleaned
        );

        // Extract and remove Zsolozsma week heading (rendered as H1/H3 from PDF)
        // e.g. ### **Zsolozsma II. zsh.** or # **Zsolozsma I. kötet, I. zsh.**
        if (preg_match('/^#{1,6}\s+\*\*Zsolozsma\s+(.+?)\*\*\s*$/mu', $cleaned, $zsoMatch)) {
            $result['zsolozsma_week'] = trim($zsoMatch[1]);
            $cleaned = preg_replace('/^#{1,6}\s+\*\*Zsolozsma\s+.+?\*\*\s*$/mu', '', $cleaned, 1);
        }

        // === MAIN EXTRACTION ===

        // Extract celebration title: bold all-uppercase text (10+ uppercase chars)
        // May appear mid-line: **NAGYBÖJT IV. VASÁRNAPJA GY0 V0** *Lætare-vasárnap* **15.**
        // Or on its own line: **A BOLDOGSÁGOS SZŰZ MÁRIA GY1 V0 SZEPLŐTELEN FOGANTATÁSA — FÜ**
        $titlePattern = '/\*\*([A-ZÁÉÍÓÖŐÚÜŰ][A-ZÁÉÍÓÖŐÚÜŰ0-9\s.,\-–—\/()†]{9,}?)\s*\*\*/u';
        if (preg_match($titlePattern, $cleaned, $match)) {
            $rawTitle = trim($match[1]);

            // Extract GY code from title
            if (preg_match('/(GY[012])/', $rawTitle, $gyMatch)) {
                $result['funeral_mass_code'] = $gyMatch[1];
            }

            // Extract V code from title
            if (preg_match('/\b(V[012])\b/', $rawTitle, $vMatch)) {
                $result['votive_mass_code'] = $vMatch[1];
            }

            // Extract rank code after em-dash (may be followed by a day abbreviation like SZE)
            $daysForRank = implode('|', self::DAY_ABBREVIATIONS);
            if (preg_match('/\s*—\s*(FÜ|Ü|E|e)(?:\s+(?:'.$daysForRank.'))?\s*$/', $rawTitle, $rankMatch)) {
                $result['rank_code'] = $rankMatch[1];
            }

            // Clean the title: remove GY/V codes, rank, trailing numbers, em-dash, day abbreviations
            $displayTitle = $rawTitle;
            $displayTitle = preg_replace('/\s*GY[012]/', '', $displayTitle);
            $displayTitle = preg_replace('/\s*\bV[012]\b/', '', $displayTitle);
            $displayTitle = preg_replace('/\s*—\s*(FÜ|Ü|E|e)(?:\s+(?:'.$daysForRank.'))?\s*$/', '', $displayTitle);
            $displayTitle = preg_replace('/\s+\d+\.\s*$/', '', $displayTitle);
            $daysJoined = implode('|', self::DAY_ABBREVIATIONS);
            $displayTitle = preg_replace('/\s+('.$daysJoined.')\s*$/', '', $displayTitle);
            $result['celebration_title'] = trim($displayTitle);

            // Remove the matched **TITLE** portion from markdown
            $cleaned = preg_replace('/'.preg_quote($match[0], '/').'/', '', $cleaned, 1);
        }

        // Extract GY/V codes from bold mixed-case lines like **Köznap GY2 V2** or **Köznap GY2 V2 2.**
        // These are not all-uppercase so the title pattern doesn't match them
        // Use [^*]+? to avoid crossing bold marker boundaries (e.g. **PÉ** **Köznap GY2 V2**)
        $koznapPattern = '/\*\*([^*]+?)\s+(GY[012])\s+(V[012])(?:\s+\d+\.)?\s*\*\*/u';
        if (preg_match($koznapPattern, $cleaned, $koznapMatch)) {
            if (! $result['funeral_mass_code']) {
                $result['funeral_mass_code'] = $koznapMatch[2];
            }
            if (! $result['votive_mass_code']) {
                $result['votive_mass_code'] = $koznapMatch[3];
            }
            // Use the text before GY/V codes as celebration title if none was found yet
            if (! $result['celebration_title']) {
                $result['celebration_title'] = trim($koznapMatch[1]);
            }
            // Remove the matched portion from markdown
            $cleaned = preg_replace('/'.preg_quote($koznapMatch[0], '/').'/', '', $cleaned, 1);
        }

        // Broad fallback: any bold block containing GY/V codes with arbitrary trailing content
        // Handles mixed-case titles like **SZŰZ MÁRIA, ISTEN ANYJA (Újév) GY0 V0 — FÜ — parancsolt ünnep!**
        if (! $result['funeral_mass_code']) {
            $broadPattern = '/\*\*([^*]+?)\s+(GY[012])\s+(V[012])([^*]*)\*\*/u';
            if (preg_match($broadPattern, $cleaned, $broadMatch)) {
                $result['funeral_mass_code'] = $broadMatch[2];
                $result['votive_mass_code'] = $broadMatch[3];

                if (! $result['celebration_title']) {
                    $result['celebration_title'] = trim($broadMatch[1]);
                }

                // Extract rank code from trailing content (e.g. "— FÜ — parancsolt ünnep!")
                if (! $result['rank_code'] && preg_match('/—\s*(FÜ|Ü|E|e)(?:\s|—|$)/u', $broadMatch[4], $rankMatch)) {
                    $result['rank_code'] = $rankMatch[1];
                }

                $cleaned = preg_replace('/'.preg_quote($broadMatch[0], '/').'/', '', $cleaned, 1);
            }
        }

        // Extract pro populo: **† MISE:** or **†\s*MISE:**
        if (preg_match('/\*\*†\s*MISE:\*\*/u', $cleaned)) {
            $result['is_pro_populo'] = true;
        }

        // Detect penitential/fast level BEFORE removal (order matters: ††† > †† > † PÉ)
        if (preg_match('/†\s*\*\*PÉ\*\*/u', $cleaned)) {
            $result['is_penitential'] = true;
        }
        if (preg_match('/†††/u', $cleaned)) {
            $result['fast_level'] = 3;
        } elseif (preg_match('/††/u', $cleaned)) {
            $result['fast_level'] = 2;
        } elseif ($result['is_penitential']) {
            $result['fast_level'] = 1;
        }

        // Remove penitential marker from markdown
        $cleaned = preg_replace('/†\s*\*\*PÉ\*\*\s*/u', '', $cleaned);

        // Remove any remaining lone dagger symbols (penitential/fast artifacts)
        // Preserve daggers inside bold markers like **† MISE:**
        $cleaned = preg_replace('/(?<!\*\*)†+\s*/u', '', $cleaned);

        // Extract liturgical color: standalone italic color NOT followed by "vagy"
        // Matches both *viola* (standalone) and *viola ...* (color at start of italic span, e.g. *viola Olv.:*)
        $colorsJoined = implode('|', self::COLORS);
        $colorPattern = '/^\*('.$colorsJoined.')(?:\*|\s)(?!vagy)/mu';
        if (preg_match($colorPattern, $cleaned, $colorMatch)) {
            $result['liturgical_color'] = $colorMatch[1];
            // Remove *color* if standalone, or replace *color with * if part of larger italic
            $standaloneColorPattern = '/^\*('.$colorsJoined.')\*\s*/mu';
            if (preg_match($standaloneColorPattern, $cleaned)) {
                $cleaned = preg_replace($standaloneColorPattern, '', $cleaned, 1);
            } else {
                // Part of larger italic span like *viola Olv.:* → replace *viola with *
                $inlineColorPattern = '/^\*('.$colorsJoined.')\s+/mu';
                $cleaned = preg_replace($inlineColorPattern, '*', $cleaned, 1);
            }
        }

        // === CLEANUP ===

        // Transform "*color vagy*" alternative mass marker into "*v.* *color*"
        // e.g. "*fehér vagy* **Szent Miklósról:**" → "*v.* *fehér* **Szent Miklósról:**"
        $cleaned = preg_replace(
            '/\*('.$colorsJoined.')\s+vagy\*/u',
            '*v.* *$1*',
            $cleaned
        );

        // Remove bold standalone day numbers like **15.** or **2.**
        $cleaned = preg_replace('/\*\*\d+\.\*\*/u', '', $cleaned);

        // Remove bold day abbreviations like **VA**, **HÉ**, **KE**, etc.
        $daysJoinedAll = implode('|', self::DAY_ABBREVIATIONS);
        $cleaned = preg_replace('/\*\*('.$daysJoinedAll.')\*\*/u', '', $cleaned);

        // Remove day number + weekday combination lines like **7. VAS** or **8. HÉ**
        $cleaned = preg_replace('/\*\*\d+\.\s*('.$daysJoinedAll.')\*\*/u', '', $cleaned);

        // Collapse multiple blank lines into at most two newlines
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned);
        $result['cleaned_markdown'] = trim($cleaned);

        return $result;
    }
}
