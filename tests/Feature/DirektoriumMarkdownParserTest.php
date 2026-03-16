<?php

use App\Services\DirektoriumMarkdownParser;

beforeEach(function () {
    $this->parser = new DirektoriumMarkdownParser;
});

it('extracts celebration title from bold uppercase line', function () {
    $markdown = "**ADVENT I. VASÁRNAPJA GY0 V0**\n\n**ZSO:** az adventi vasárnapról.";

    $result = $this->parser->parse($markdown);

    expect($result['celebration_title'])->toBe('ADVENT I. VASÁRNAPJA');
});

it('extracts celebration title with rank code after em-dash', function () {
    $markdown = "**A BOLDOGSÁGOS SZŰZ MÁRIA GY1 V0 SZEPLŐTELEN FOGANTATÁSA — FÜ**\n\n**ZSO:** a főünnepről.";

    $result = $this->parser->parse($markdown);

    expect($result['celebration_title'])->toBe('A BOLDOGSÁGOS SZŰZ MÁRIA SZEPLŐTELEN FOGANTATÁSA');
    expect($result['rank_code'])->toBe('FÜ');
});

it('extracts funeral and votive mass codes from title', function () {
    $markdown = "**ADVENT I. VASÁRNAPJA GY0 V0**\n\nSome text.";

    $result = $this->parser->parse($markdown);

    expect($result['funeral_mass_code'])->toBe('GY0');
    expect($result['votive_mass_code'])->toBe('V0');
});

it('extracts GY2 V2 codes', function () {
    $markdown = "**NAGYBÖJT V. VASÁRNAPJA GY2 V2**\n\nSome text.";

    $result = $this->parser->parse($markdown);

    expect($result['funeral_mass_code'])->toBe('GY2');
    expect($result['votive_mass_code'])->toBe('V2');
});

it('extracts standalone liturgical color viola', function () {
    $markdown = "*viola* **MISE:** köznapi, saját kollekta,\n\nI. adventi pref.";

    $result = $this->parser->parse($markdown);

    expect($result['liturgical_color'])->toBe('viola');
});

it('extracts standalone liturgical color fehér', function () {
    $markdown = '*fehér* **† MISE:** Szeplőtelen Fogantatásról: saját,';

    $result = $this->parser->parse($markdown);

    expect($result['liturgical_color'])->toBe('fehér');
});

it('does not extract color from fehér vagy pattern', function () {
    $markdown = '*fehér vagy* **Rorate–mise:** Szűz Mária közös miséje';

    $result = $this->parser->parse($markdown);

    expect($result['liturgical_color'])->toBeNull();
});

it('detects pro populo from dagger MISE pattern', function () {
    $markdown = '*viola* **† MISE:** advent I. vasárnapjáról: saját';

    $result = $this->parser->parse($markdown);

    expect($result['is_pro_populo'])->toBeTrue();
});

it('does not detect pro populo from regular MISE', function () {
    $markdown = '*viola* **MISE:** köznapi, saját kollekta,';

    $result = $this->parser->parse($markdown);

    expect($result['is_pro_populo'])->toBeFalse();
});

it('detects penitential friday marker', function () {
    $markdown = "† **PÉ** **Köznap GY2 V2**\n\n**ZSO:** az adventi köznapról.";

    $result = $this->parser->parse($markdown);

    expect($result['is_penitential'])->toBeTrue();
    expect($result['fast_level'])->toBe(1);
});

it('removes penitential marker from cleaned markdown', function () {
    $markdown = "† **PÉ** **Köznap GY2 V2**\n\n**ZSO:** az adventi köznapról.";

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->not->toContain('† **PÉ**');
});

it('detects abstinence day combined with penitential friday', function () {
    $markdown = "**Köznap GY2 V1 27.**\n\n**ZSO:** a nagyböjti köznapról. †† **PÉ**\n\n**MISE:** saját, I. pref. Urunk szenvedéséről\n\n*viola Olv.:* Jer 20,10–13; Jn 10,31–42";

    $result = $this->parser->parse($markdown);

    expect($result['is_penitential'])->toBeTrue();
    expect($result['fast_level'])->toBe(2);
    expect($result['funeral_mass_code'])->toBe('GY2');
    expect($result['votive_mass_code'])->toBe('V1');
    expect($result['liturgical_color'])->toBe('viola');
    expect($result['cleaned_markdown'])->not->toContain('†');
    expect($result['cleaned_markdown'])->toContain('*Olv.:* Jer 20,10–13');
    // Verify no stray dagger left
    expect($result['cleaned_markdown'])->not->toMatch('/†/');
});

it('removes stray daggers but preserves pro populo dagger in bold', function () {
    $markdown = "*viola* **† MISE:** köznapi\n\n†† **PÉ**";

    $result = $this->parser->parse($markdown);

    expect($result['is_pro_populo'])->toBeTrue();
    expect($result['is_penitential'])->toBeTrue();
    expect($result['fast_level'])->toBe(2);
    // Pro populo dagger inside ** stays (removed as part of title extraction context)
    // but penitential daggers are cleaned
    expect($result['cleaned_markdown'])->not->toMatch('/(?<!\*\*)†/');
});

it('keeps readings intact in cleaned markdown', function () {
    $markdown = "*viola* **MISE:** köznapi\n\n*Olv.:* Iz 2,1–5; Róm 13,11–14; Mt 24,37–44\n\n(*Röv:* 11,3–7.17.20–27.33b–45)";

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->toContain('*Olv.:* Iz 2,1–5; Róm 13,11–14; Mt 24,37–44');
    expect($result['cleaned_markdown'])->toContain('(*Röv:* 11,3–7.17.20–27.33b–45)');
});

it('removes title line from cleaned markdown', function () {
    $markdown = "**ADVENT I. VASÁRNAPJA GY0 V0**\n\n**ZSO:** az adventi vasárnapról.";

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->not->toContain('**ADVENT I. VASÁRNAPJA');
    expect($result['cleaned_markdown'])->toContain('**ZSO:**');
});

it('removes standalone color marker from cleaned markdown', function () {
    $markdown = '*viola* **† MISE:** advent I. vasárnapjáról';

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->not->toMatch('/^\*viola\*/m');
    expect($result['cleaned_markdown'])->toContain('**† MISE:**');
});

it('removes day number + weekday abbreviation lines', function () {
    $markdown = "**8. HÉ**\n\n**A BOLDOGSÁGOS SZŰZ MÁRIA GY1 V0 SZEPLŐTELEN FOGANTATÁSA — FÜ**";

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->not->toContain('**8. HÉ**');
});

it('extracts GY/V codes from mixed-case Köznap line and sets title', function () {
    $markdown = "**Köznap GY2 V2**\n\n**ZSO:** az adventi köznapról.\n\n*viola* **MISE:** köznapi";

    $result = $this->parser->parse($markdown);

    expect($result['funeral_mass_code'])->toBe('GY2');
    expect($result['votive_mass_code'])->toBe('V2');
    expect($result['celebration_title'])->toBe('Köznap');
    expect($result['cleaned_markdown'])->not->toContain('Köznap GY2 V2');
    expect($result['cleaned_markdown'])->toContain('**ZSO:**');
});

it('extracts GY/V codes from Köznap line with trailing day number', function () {
    $markdown = "**Köznap GY2 V2 2.**\n\n**ZSO:** az adventi köznapról.";

    $result = $this->parser->parse($markdown);

    expect($result['funeral_mass_code'])->toBe('GY2');
    expect($result['votive_mass_code'])->toBe('V2');
    expect($result['cleaned_markdown'])->not->toContain('Köznap GY2 V2 2.');
});

it('returns null for missing fields', function () {
    $markdown = "**ZSO:** az adventi köznapról.\n\n*Olv.:* Iz 2,1–5; Mt 8,5–11";

    $result = $this->parser->parse($markdown);

    expect($result['celebration_title'])->toBeNull();
    expect($result['liturgical_color'])->toBeNull();
    expect($result['funeral_mass_code'])->toBeNull();
    expect($result['votive_mass_code'])->toBeNull();
    expect($result['rank_code'])->toBeNull();
    expect($result['is_pro_populo'])->toBeFalse();
    expect($result['is_penitential'])->toBeFalse();
    expect($result['fast_level'])->toBe(0);
});

it('parses the full example markdown correctly', function () {
    $markdown = <<<'MD'
**ZSO:** I. Ed.: a vasárnapról, Bi.: Vasárnap, I. Ed. után.

**NAGYBÖJT V. VASÁRNAPJA GY0 V0 22.**

**ZSO:** a nagyböjti vasárnapról. **VA**

**† MISE:** Nagyböjt V. vasárnapjáról: saját.

*viola* (Dicsőség nincs.) Hitvallás, saját pref.

A IV. Eucharisztikus ima nem mondható, sem a különleges alkalmakra valók.

*Olv.:* Ez 37,12–14; Róm 8,8–11; Jn 11,1–45

(*Röv:* 11,3–7.17.20–27.33b–45)

Ma a Szentföld javára van gyűjtés.

*Választható heti olv.: Tetszés szerint nagyböjt V. heté- ben – kiváltképp B és C évben, amikor nem a Lázárról szóló evangéliumot olvassák fel:* **2Kir 4,18b–21.32–37; Jn 11,1–45**
MD;

    $result = $this->parser->parse($markdown);

    expect($result['celebration_title'])->toBe('NAGYBÖJT V. VASÁRNAPJA');
    expect($result['funeral_mass_code'])->toBe('GY0');
    expect($result['votive_mass_code'])->toBe('V0');
    expect($result['liturgical_color'])->toBe('viola');
    expect($result['is_pro_populo'])->toBeTrue();
    expect($result['cleaned_markdown'])->toContain('*Olv.:* Ez 37,12–14');
    expect($result['cleaned_markdown'])->toContain('(*Röv:* 11,3–7.17.20–27.33b–45)');
    expect($result['cleaned_markdown'])->not->toContain('**NAGYBÖJT V. VASÁRNAPJA');
    expect($result['cleaned_markdown'])->not->toContain('**VA**');
});

it('extracts title and codes when title has trailing content on same line', function () {
    $markdown = <<<'MD'
**NAGYBÖJT IV. VASÁRNAPJA GY0 V0** *Lætare-vasárnap* **15.**

**ZSO:** a nagyböjti vasárnapról. **VA**

*viola* **† MISE:** Nagyböjt IV. vasárnapjáról: saját. (Dicsőség nincs.) Hitvallás, saját pref. A IV. Eucharisztikus ima nem mondható, sem a különleges alkalmakra valók. *Olv.:* 1Sám 16,1b. 6–7.10–13a; Ef 5,8–14; Jn 9,1–41 (*Röv:* 9,1. 6–9.13–17.34–38)

Következő vasárnap a Szentföld javára lesz gyűjtés. A hirdetésekben említsük meg.

**Eger:** Ma van a főpásztor kinevezésének évfordulója (2007).

*Választható heti olv.: Tetszés szerint nagyböjt IV. hetében – kiváltképp B és C évben, amikor nem a vakon született meggyógyításáról szóló evangéliumot olvassák fel:* **Mik 7,7–9; Jn 9,1–41**
MD;

    $result = $this->parser->parse($markdown);

    expect($result['celebration_title'])->toBe('NAGYBÖJT IV. VASÁRNAPJA');
    expect($result['funeral_mass_code'])->toBe('GY0');
    expect($result['votive_mass_code'])->toBe('V0');
    expect($result['liturgical_color'])->toBe('viola');
    expect($result['is_pro_populo'])->toBeTrue();
    // Title portion removed, but Lætare-vasárnap stays
    expect($result['cleaned_markdown'])->not->toContain('**NAGYBÖJT IV. VASÁRNAPJA');
    expect($result['cleaned_markdown'])->toContain('Lætare-vasárnap');
    // Bold number and day abbreviation removed
    expect($result['cleaned_markdown'])->not->toContain('**15.**');
    expect($result['cleaned_markdown'])->not->toContain('**VA**');
    // Readings intact
    expect($result['cleaned_markdown'])->toContain('*Olv.:* 1Sám 16,1b');
    expect($result['cleaned_markdown'])->toContain('(*Röv:* 9,1. 6–9.13–17.34–38)');
});

it('extracts color that appears before Olv on a separate line', function () {
    // Sometimes the color is on a line before the readings
    $markdown = "*viola*\n\n*Olv.:* Iz 2,1–5; Róm 13,11–14; Mt 24,37–44";

    $result = $this->parser->parse($markdown);

    expect($result['liturgical_color'])->toBe('viola');
    expect($result['cleaned_markdown'])->toContain('*Olv.:*');
    expect($result['cleaned_markdown'])->not->toMatch('/^\*viola\*$/m');
});

it('extracts color from inline italic span like *viola Olv.:*', function () {
    $markdown = '*viola Olv.:* Iz 2,1–5; Róm 13,11–14; Mt 24,37–44';

    $result = $this->parser->parse($markdown);

    expect($result['liturgical_color'])->toBe('viola');
    expect($result['cleaned_markdown'])->toContain('*Olv.:*');
    expect($result['cleaned_markdown'])->not->toContain('viola');
});

it('removes headings containing idő', function () {
    $markdown = "# NAGYBÖJTI IDŐ – MÁRCIUS\n\n**NAGYBÖJT V. VASÁRNAPJA GY0 V0**";

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->not->toContain('NAGYBÖJTI IDŐ');
    expect($result['celebration_title'])->toBe('NAGYBÖJT V. VASÁRNAPJA');
});

it('fixes Minden missing space before uppercase', function () {
    $markdown = 'MindenUrunk születésének ünnepéről.';

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->toContain('Minden Urunk');
});

it('fixes minden missing space before lowercase non-compound', function () {
    $markdown = 'mindenadventi köznapról.';

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->toContain('minden adventi');
});

it('does not break minden compounds like mindenki', function () {
    $markdown = 'mindenki számára elérhető.';

    $result = $this->parser->parse($markdown);

    expect($result['cleaned_markdown'])->toContain('mindenki');
});

it('joins split bold uppercase title lines', function () {
    $markdown = "**URUNK SZÜLETÉSÉNEK GY1 V0**\n**HÍRÜLADÁSA (GYÜMÖLCSOLTÓ BOLDOGASSZONY) — FÜ SZE**";

    $result = $this->parser->parse($markdown);

    expect($result['celebration_title'])->toContain('URUNK SZÜLETÉSÉNEK');
    expect($result['celebration_title'])->toContain('HÍRÜLADÁSA');
    expect($result['funeral_mass_code'])->toBe('GY1');
    expect($result['votive_mass_code'])->toBe('V0');
    expect($result['rank_code'])->toBe('FÜ');
});

it('extracts and removes Zsolozsma week heading', function () {
    $markdown = "### **Zsolozsma II. zsh.**\n\n*viola* **MISE:** köznapi";

    $result = $this->parser->parse($markdown);

    expect($result['zsolozsma_week'])->toBe('II. zsh.');
    expect($result['cleaned_markdown'])->not->toContain('Zsolozsma');
    expect($result['cleaned_markdown'])->toContain('**MISE:**');
});

it('extracts Zsolozsma heading with kötet info', function () {
    $markdown = "# **Zsolozsma I. kötet, I. zsh.**\n\nSome text.";

    $result = $this->parser->parse($markdown);

    expect($result['zsolozsma_week'])->toBe('I. kötet, I. zsh.');
    expect($result['cleaned_markdown'])->not->toContain('Zsolozsma');
});

it('strips day abbreviation from end of title', function () {
    $markdown = '**URUNK SZÜLETÉSÉNEK HÍRÜLADÁSA — FÜ SZE**';

    $result = $this->parser->parse($markdown);

    expect($result['celebration_title'])->not->toMatch('/SZE$/');
    expect($result['rank_code'])->toBe('FÜ');
});
