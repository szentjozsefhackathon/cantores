| Fejléc | Jelentés |
|---|---|
| `name: Cím;` | darab neve |
| `mode: 1;` | hangnem (1–8, vagy `t` = toni) |
| `initial-style: 1;` | iniciálé mérete (0=nincs, 1=normál, 2=nagy) |
| `office-part: introitus;` | liturgikus rész neve |
| `%%` | fejléc lezárása |

| Kulcsok | |
|---|---|
| `(c1)` `(c2)` `(c3)` `(c4)` | C-kulcs 1–4. vonalon |
| `(f3)` `(f4)` | F-kulcs 3–4. vonalon |
| `(cb3)` | C-kulcs b-előjegyzéssel |

| Hangmagasság | |
|---|---|
| `a b c d e f g h i j k l m` | 13 pozíció alulról felfelé |
| `(clef) betű` | pl. `(c3) f` = do (c-kulcs 3. vonalon, f pozíció) |

| Kottafej | |
|---|---|
| `f` | punctum (alapforma) |
| `f.` | punctum mora (megnyújtott) |
| `fv` | virga |
| `fo` | oriscus |
| `fq` | quilisma |
| `fw` | stropha |
| `fr` | liquescens (kicsi) |
| `fR` | liquescens (nagy) |

| Neuma-csoportok | |
|---|---|
| `fh` | podatus (két hang: alsó–felső) |
| `ghf` | torculus |
| `fhg` | porrectus (legato ívvel) |
| `fg` `gh` | clivis, podatus (explicit) |
| `!` | neumacsoporton belüli kötés kikapcsolása |

| Tagolás/ütem | |
|---|---|
| `,` | negyedvonal (kis cezúra) |
| `;` | félvonal |
| `:` | egész vonal |
| `::` | kettős vonal (rész vége) |
| `:::` | záróvonal |
| `,0` | láthatatlan szünetjel |

| Módosítójelek | |
|---|---|
| `(eb)` | b-módosítójel e hangon |
| `(ey)` | feloldójel e hangon |
| `(e#)` | kereszt e hangon |

| Szöveg | |
|---|---|
| `Ky(f)ri(h)e(g)` | szótag zárójelben lévő hanghoz kötve |
| `<sp>V/</sp>` | speciális karakter (verzikulus) |
| `<i>szöveg</i>` | dőlt szöveg |
| `<b>szöveg</b>` | félkövér szöveg |
| `<alt>szöveg</alt>` | másodlagos szövegsor (pl. fordítás) |

| Sortörés/rés | |
|---|---|
| `(z)` | sortörés |
| `(Z)` | sorkizárt sortörés |
| `(,)` | kis szóköz |
| `(//)` | szóköz |

| Egyéb | |
|---|---|
| `%section` | szakasz kezdete; a füzetben és a vetítésben kiválasztható |
| `%section Címke` | ugyanaz, névvel a szerkesztőben |

### Oldaltörés vetítéshez

Rögzített képarányon (`16:9`, `4:3`, `1:1`) a forrásban egy külön sorba írt
`%pagebreak` új diát kezd. Papír módban minden ilyen sor figyelmen kívül marad.

| Jel | Mikor tör? |
|---|---|
| `%pagebreak` | minden rögzített képarányban |
| `%pagebreak169` | csak 16:9 vetítésben (`43`, `11` ugyanígy) |
| `%pagebreak?` | javaslat: csak akkor kezd új diát, ha a kotta másképp nem férne ki |
| `%pagebreak169?` | ugyanez, egyetlen képarányra |

Ha egy oldal így sem fér ki a diára, az alja nem vész el: ami nem fér ki, az a
következő diára kerül. A vágás sorrendje mindig ez:

1. a `%pagebreak` sorok — ezek mindig vágnak;
2. ami így is hosszú, a saját `%pagebreak?` javaslatainál törik, és csak annyinál,
   amennyi feltétlenül kell;
3. ami még mindig nem fér ki, két kottasor között törik, minden diát megtöltve,
   mielőtt a következő elkezdődne.

Ha a szerkesztő maga vágott, a dia fölött kék tájékoztató jelzi, a vetítés
szerkesztőjében pedig „Automatikus vágás” felirat a dia száma mellett. Ez nem
hiba, csak jelzés: a gép a sorok végénél vág, nem a dallam tagolásánál, és egy
kézzel beírt `%pagebreak169` (vagy a képarányhoz illő társa) oda teszi a törést,
ahová a zene kívánja. Figyelmeztetés csak akkor marad, ha már egyetlen kottasor
is magasabb a diánál — ott kisebb kottaméret a megoldás. A törés utáni részt a gép a fejléccel együtt
külön szedi, ezért annak az első sorában is legyen kulcs, ahogy kézi
`%pagebreak` után is.
