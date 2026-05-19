| Fejléc | Jelentés |
|---|---|
| `X:1` | sorszám (kötelező, egész szám) |
| `T:Cím` | cím |
| `C:Szerző` | szerző/zeneszerző |
| `M:4/4` | ütemmutatő (`C` = common, `C\|` = alla breve) |
| `L:1/8` | alapértékű hanghossz |
| `Q:120` | tempó (ütés/perc), pl. `Q:1/4=120` |
| `K:G` | hangnem (lezárja a fejlécet) |

| Hangmagasság | |
|---|---|
| `C D E F G A B` | kis oktáv (nagy betű) |
| `c d e f g a b` | egévonalas oktáv (kis betű) |
| `c' d'` vagy `c2 d2`| kétvonalas oktáv (aposztróf vagy vesszővel feljebb) |
| `C,` | kontra-oktáv (vessző = egy oktávval lejjebb) |

| Hanghossz | |
|---|---|
| `A` | alapértékű hang (L: szerint) |
| `A2` | kétszeres hossz |
| `A/2` vagy `A/` | feles hossz |
| `A3/2` | pontozott |
| `z` | szünet (hossza ugyanúgy módosítható) |
| `x` | láthatatlan szünet |

| Módosítójelek | |
|---|---|
| `^A` | kereszt (fisz) |
| `^^A` | kettős kereszt |
| `_A` | bé (ász) |
| `__A` | kettős bé |
| `=A` | feloldójel |

| Tagolás/ütem | |
|---|---|
| `\|` | ütemvonal |
| `\|\|` | kettős vonal |
| `\|]` | záróvonal |
| `\|:` `:\|` | ismétlőjel (nyitó / záró) |
| `:\|:` | kétirányú ismétlő |
| `[1` `[2` | volta zárójelek (1., 2. ház) |

| Kötések és díszítések | |
|---|---|
| `(ABC)` | legato ív (slur) |
| `A-B` | kötőív (tie) |
| `~A` | rögtönzött díszítés |
| `.A` | staccato |
| `TA` | trill |
| `{fg}` | gracenotes (előkék) |

| Akkordok és többszólamúság | |
|---|---|
| `[CEG]` | akkord (egyszerre szóló hangok) |
| `[V:1]` `[V:2]` | hangszólamok inline váltása |
| `V:1` `V:2` | hangszólam-fejléc (sor elején) |

| Szöveg | |
|---|---|
| `w: Ky-ri-e e-le-i-son` | szövegsor (kötőjel = szótaghatár) |
| `*` | egy hang kihagyása a szövegben |
| `_` | szótaghosszabbítás (melizma) |
| több `w:` sor | több versszak |

| Egyéb | |
|---|---|
| `%` | megjegyzés (sor végéig) |
| `%%MIDI program 41` | MIDI / kiterjesztett parancs |
| `+` | sorzáró folytatójel (hosszú sor tördelése) |
