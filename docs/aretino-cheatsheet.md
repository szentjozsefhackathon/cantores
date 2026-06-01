|Kulcsok||
|---|---|
| `(g2)` `(f4)` `(c3)` | G-, F-, C-kulcs |

|Hangmagasság||
|---|---|
| `A B c d e f g a b C D E F G` | 14 pozíció alulról felfelé |

|Kottafej||
|---|---|
| `d`, `d'`, `dt` | punctum, virga, tenor |
| `dw`, `ds` | quilisma, kiskotta (liquescens átírásához) |

| Utótag ||
|---|---|
| `d.`, `d_`, `d-`, `d~` | mora, episema, ictus, plica |
| `df/ga` | `/` = neuma-tagoló |

| Ívek, zárójelek, feliratok | |
|---|---|
| `[a]` `[ag]` `[a b C]` | hang/neuma tipográfiai zárójelben |
| `{ g a b }` | kapcsos összefogó jel a hangok fölött |
| `\arc{ g a b }` `\line{ g a b }` | ív / egyenes összefogó vonal a hangok fölött |
| `}"Felirat"` | az összefogó jel záró felirata |
| `\slur{a g}` `\slurSolid{a g}` | kötőív (szaggatott / folytonos) |
| `c"Felirat"` | felirat a hangjegy fölé |

| Tagolás/ütem | |
|---|---|
| `'` | apró szünetjel |
| `,` | negyedvonal (kis cezúra) |
| `;` | félvonal |
| `\|` | egész vonal |
| `\|0` | üres (láthatatlan) vonal, egész vonal szélességű |
| `\|\|` | kettős vonal (rész vége) |
| `:\| \|: :\|:` | ismétlőjel |
| `\|\|\|` | záróvonal |

| Elosztás/sortörés | |
|---|---|
| `(z)` `(Z)` | sortörés, sorkizárt/balra zárt |
| `= (sp) (sp2)` | fix rés (szorzóval skálázható) |
| `*` | rugalmas rés (sorkizáráshoz) |

| Módosítójelek és előjegyzés | |
|---|---|
| `(b)` `{n)` `(#)` | b, feloldó, kereszt (hangbetű nélkül: `i` magasság) |
| `(fb)` `(dn)` `(G#)` | hanghoz kötött módosítójel |
| `(K:F# C#)` | előjegyzés (minden sor elején ismétlődik) |
| `(Kb)` `(Kbb)` `(K#)` `(K##)` | előjegyzés-gyorsjel (1-2 b / kereszt) |
| `(K:)` `(K)` | előjegyzés törlése |

| Szöveg | |
|---|---|
| `w: Ky-ri-e` | szövegsor (kötőjel = szótaghatár) |
| több `w:` sor | több versszak |
| `W: Dicsőség...` | folyó zsoltárvers / responzórium szövegsor |
| `n: ...` | előző dallamsor folytatása (a `w:` sorok igazítása megmarad) |
| `ro_` `ro__` | szótag-elnyújtó vonal (minden további `_` egy újabb neumára nyújt) |
| `=` | mindig látszó (kötelező) szótaghatár |
| `\-` `\_` | szó szerinti kötőjel / aláhúzás egy szótagon belül |
| `~` | Több szó összekötése egy hanghoz |
| `~~` | Verszakszám kötése (pl. `1.~~Ky-ri-e`) |
| `\R` `\V` `+` `++` | ℟, ℣, kereszt, kettős kereszt jel |
| `{}` `<>` `[]` | félkövér, dőlt, aláhúzott formázás |
| `\red{...}` `\color:green{...}` | színezett szöveg |
| `c"Felirat"` | felirat a hangjegy fölé


| Fejléc | Jelentés |
|---|---|
| `%title: Cím` | cím (középre, félkövéren), továbbiak: rubric, caption, indent |
| `%option: lyricSize=12` | megjelenítő-beállítás a forrásból (soronként egy, ismételhető) |
| `%%` | fejléc lezárása |
