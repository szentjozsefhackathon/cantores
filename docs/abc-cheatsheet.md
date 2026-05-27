| Fejléc | Jelentés |
|---|---|
| `K:G` | hangnem (kötelező, lezárja a fejlécet) |
| `T:Cím` | cím |
| `C:Szerző` | szerző/zeneszerző |
| `M:4/4` | ütemmutató: négy negyed egy ütemben |
| `M:C` | common time, azaz 4/4 |
| `M:C\|` | alla breve, azaz 2/2 |
| `M:6/8` | hat nyolcad egy ütemben |
| `L:1/8` | alapértékű hanghossz |
| `Q:120` | tempó (ütés/perc), pl. `Q:1/4=120` |

| Hangmagasság | |
|---|---|
| `C D E F G A B` | kis oktáv (nagy betű) |
| `c d e f g a b` | egyvonalas oktáv (kis betű) |
| `c' d'` | kétvonalas oktáv (aposztróf = egy oktávval feljebb) |
| `C,` | kontra-oktáv (vessző = egy oktávval lejjebb) |

| Hanghossz | |
|---|---|
| `A` | alapértékű hang (L: szerint) |
| `A2` | kétszeres hossz |
| `A/2` vagy `A/` | feles hossz |
| `A3/2`, `A>`, `A<` | pontozott |
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
| `(ABC)` | legato ív |
| `A-B` | kötőív |
| `.A` | staccato |
| `TA` | trilla |
| `HA` | fermata |
| `{fg}` | előkék |

| Akkordok és többszólamúság | |
|---|---|
| `[CEG]` | akkord (egyszerre szóló hangok) |
| `V:1` `V:2` | szólam jelölés (külön sorban) |
| `%%score (Felso Also)` | két megnevezett szólam egy kottasoron |
| `%%score (S A) (T B)` | SATB két kottasoron: fent SA, lent TB |
| `C E G c & E, G, C E` | ideiglenes alsó szólam egy ütemben |

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
