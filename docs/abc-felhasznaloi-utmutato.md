# ABC kottázás

Az **ABC** szöveges kottaírás. A hangokat betűkkel, a ritmust számokkal,
az ütemvonalakat és ismétléseket egyszerű jelekkel írjuk le.

Az alábbi példák mind szerkeszthetők: írd át a kódot, és a kotta azonnal
frissül.

```abc
X:1
T:Első példa
M:4/4
L:1/4
K:C
C D E F | G2 G2 | A G F E | D4 |]
w: El-ső pél-da A-B-C-ben
```

## 1. A kotta váza

Egy egyszerű ABC-kotta elején néhány fejlécsor áll, utána jönnek a hangok.
A `K:` sor zárja le a fejlécet.

| Sor | Mire való? | Példa |
|---|---|---|
| `X:` | azonosító | `X:1` |
| `T:` | cím | `T:Uram, irgalmazz` |
| `M:` | ütemmutató | `M:4/4` |
| `L:` | alap hanghossz | `L:1/4` |
| `K:` | hangnem | `K:G` |

```abc
X:1
T:Alap szerkezet
M:2/4
L:1/4
K:G
G A | B c | d2 |]
```

## 2. Hangok

A hangok nevei: `C D E F G A B`. A nagybetűk mélyebbek, a kisbetűk egy
oktávval magasabbak.

| Jel | Jelentés |
|---|---|
| `C D E F G A B` | mélyebb oktáv |
| `c d e f g a b` | magasabb oktáv |
| `c' d'` | még egy oktávval feljebb |
| `C, D,` | egy oktávval lejjebb |

```abc
X:1
T:Hangmagasság
M:4/4
L:1/4
K:C
C D E F | G A B c | c' b a g | f e d c |]
```

Fontos: a `c2` nem oktávjel, hanem hosszabb hang. Oktávhoz `c'`, hosszhoz
`c2` kell.

## 3. Hanghossz

Az `L:` sor adja meg az alapértéket. Ha `L:1/4`, akkor egy sima `C` negyed.
Ha `L:1/8`, akkor egy sima `C` nyolcad.

| Jel | Jelentés |
|---|---|
| `C` | alapértékű hang |
| `C2` | kétszer olyan hosszú |
| `C4` | négyszer olyan hosszú |
| `C/2` vagy `C/` | fele olyan hosszú |
| `C3/2` | másfélszeres hossz |
| `C>D` vagy `C<D` | pontozott ritmuspár |

```abc
X:1
T:Hanghosszok
M:4/4
L:1/8
K:C
C D E F | G2 A2 B2 c2 |
c4 B2>A2 | G6 z2 |]
```

## 4. Szünetek

A szünet jele `z`. Ugyanúgy kaphat hosszot, mint a hangok.

| Jel | Jelentés |
|---|---|
| `z` | alapértékű szünet |
| `z2` | kétszeres szünet |
| `z/2` | fél alapértékű szünet |
| `x` | láthatatlan szünet |

```abc
X:1
T:Szünetek
M:4/4
L:1/4
K:F
F G z A | B2 z2 | c B A G | F4 |]
```

## 5. Ütemvonalak és lezárás

| Jel | Jelentés |
|---|---|
| `|` | ütemvonal |
| `||` | kettős vonal |
| `|]` | záróvonal |
| `|:` | ismétlés kezdete |
| `:|` | ismétlés vége |
| `:|:` | ismétlés vége és új ismétlés kezdete |

```abc
X:1
T:Ütemvonalak
M:3/4
L:1/4
K:D
D E F | G2 A || B A G | F3 |]
```

## 6. Ismétlések és első-második ház

Az ismétlés jelei a kottában ugyanúgy jelennek meg, mint papíron. Az első és
második házat `[1` és `[2` jelöli.

```abc
X:1
T:Ismétlés
M:4/4
L:1/4
K:G
|: G A B c | d2 B2 |
[1 A G E2 :|[2 A F G2 |]
```

## 7. Módosítójelek

| Jel | Jelentés |
|---|---|
| `^F` | kereszt |
| `^^F` | kettős kereszt |
| `_B` | bé |
| `__B` | kettős bé |
| `=F` | feloldójel |

```abc
X:1
T:Módosítójelek
M:4/4
L:1/4
K:D
D E F G | ^G A =G F |
_B A G F | E4 |]
```

## 8. Kötések és ívek

Kétféle ívet gyakran kell megkülönböztetni:

| Jel | Jelentés |
|---|---|
| `G-G` | kötőív azonos hangok között |
| `(G A B)` | legato ív több hang fölött |

```abc
X:1
T:Kötések
M:4/4
L:1/4
K:C
C D-D F | (G A B c) | c-c B A | G4 |]
```

## 9. Akkordok és akkordjelek

Szögletes zárójelben egyszerre megszólaló hangokat írhatsz. Idézőjelben
akkordjel kerül a kotta fölé.

| Jel | Jelentés |
|---|---|
| `[CEG]` | C-E-G egyszerre |
| `"C"` | C akkordjel |
| `"Dm"` | D-moll akkordjel |

```abc
X:1
T:Akkordok
M:4/4
L:1/4
K:C
"C"[CEG] "F"[FAC] | "G"[GBd] "C"[CEG] |
C D E F | G4 |]
```

## 10. Díszítések

Díszítéseket `!név!` alakban lehet írni. Előkékhez kapcsos zárójel való.

| Jel | Jelentés |
|---|---|
| `!fermata!G` | fermata |
| `!trill!G` | trilla |
| `{AB}c` | előkék a `c` előtt |

```abc
X:1
T:Díszítések
M:3/4
L:1/4
K:G
G A B | !fermata!c2 B | {AB}c A G | G3 |]
```

## 11. Dalszöveg

A szöveg `w:` sorban áll, közvetlenül a hozzá tartozó dallamsor alatt.
A kötőjel szótaghatár.

| Jel a `w:` sorban | Jelentés |
|---|---|
| `Ky-ri-e` | három szótag |
| `*` | egy hang kihagyása |
| `_` | az előző szótag tovább tart |
| `~` | szóköz egy szótagon belül |

```abc
X:1
T:Dalszöveg
M:4/4
L:1/4
K:G
G A B c | d2 c B | A G A B | G4 |]
w: Ky-ri-e e-le-i-son * Al-le-lu-ja
```

Több versszakhoz több `w:` sort is írhatsz.

```abc
X:1
T:Több versszak
M:2/4
L:1/4
K:F
F G | A B | c2 |]
w: El-ső vers-szak
w: Má-so-dik sor is
```

## 12. Többszólamúság

A szólamokat `V:` sorokkal lehet megadni. A kottatestben `[V:1]`, `[V:2]`
vált a szólamok között.

```abc
X:1
T:Két szólam
M:3/4
L:1/4
K:C
V:1 name="Felső"
V:2 name="Alsó" clef=bass
[V:1] c2 d | e2 f | g3 |]
[V:2] C2 G, | C2 F, | C3 |]
```

## 13. Oldaltörés a Cantores.hu szerkesztőben

Vetítéshez használhatsz kézi oldaltörést. A sima `%pagebreak` minden rögzített
képaránynál tör, a számozott változat csak a megadott aránynál.

| Jel | Mikor tör? |
|---|---|
| `%pagebreak` | minden rögzített képarányban |
| `%pagebreak169` | 16:9 vetítésben |
| `%pagebreak43` | 4:3 vetítésben |
| `%pagebreak11` | 1:1 vetítésben |

```abc
X:1
T:Oldaltörés
M:4/4
L:1/4
K:F
F G A B | c2 A2 | G4 |]
w: El-ső di-a-sor
%pagebreak169
c d c B | A G F2 | F4 |]
w: Má-so-dik di-a-sor
```

## 14. Gyakori hibák

| Hiba | Mi történik? | Javítás |
|---|---|---|
| hiányzik a `K:` | nem indul jól a kotta | adj meg hangnemet, például `K:C` |
| `c2`-t oktávnak gondolod | a hang hosszabb lesz, nem magasabb | oktávhoz `c'`, hosszhoz `c2` kell |
| kevés a szótag | elcsúszik a szöveg | használj `*` jelet |
| melizma nincs jelölve | a szótag túl hamar véget ér | használj `_` jelet |
| kötőív és legato összekeveredik | rossz ív jelenik meg | azonos hanghoz `G-G`, dallamívhez `(G A)` |
