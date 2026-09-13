| Akkordok | |
|---|---|
| `[C]` `[Am]` `[F#m7]` | akkord a szövegben (szótag fölé kerül) |
| `[C/E]` | szlash-akkord (basszushang) |
| `[H]`, `[B]` | A cantores.hu kapcsolójával lehet állítani, hogy hogy értelmezzük! |

| Metaadat-direktívák | |
|---|---|
| `{title: Cím}` vagy `{t: Cím}` | cím |
| `{subtitle: Alcím}` vagy `{st:}` | alcím / előadó |
| `{artist: Szerző}` | szerző/előadó |
| `{key: G}` | hangnem |
| `{tempo: 120}` | tempó |
| `{time: 4/4}` | ütemmutatő |
| `{capo: 2}` | capo |

| Szakasz-direktívák | |
|---|---|
| `{start_of_verse}` / `{end_of_verse}` | versszak blokk |
| `{start_of_chorus}` / `{end_of_chorus}` | refrén blokk |

| Formázás | |
|---|---|
| `{comment: szöveg}` vagy `{c:}` | megjegyzés (megjelenik a kottában) |

| Egyéb | |
|---|---|
| `#` | megjegyzés (sor elején, nem jelenik meg) |

| Vetítés | |
|---|---|
| **Oldalarány** | A szerkesztő eszköztárában: `Papír` a szokásos lapszerű nézet, `16:9`, `4:3` és `1:1` a vetített dia. |
| `%pagebreak` | minden rögzített képaránynál új dia |
| `%pagebreak169` | csak 16:9 vetítésben |
| `%pagebreak43` | csak 4:3 vetítésben |
| `%pagebreak11` | csak 1:1 vetítésben |

`Papír` módban minden `%pagebreak` sor figyelmen kívül marad — a dalszöveg egyetlen
egységként jelenik meg.

Rögzített képarányra váltva a lap a vetítéshez igazodik: keskenyített
betűtípusra (Barlow Condensed), egy hasábra és a kivetítőről is olvasható
méretre. Ezek a beállítások képarányonként külön tárolódnak, így a papírra
szánt elrendezés érintetlen marad.

```
{title: Példa}
[C]El-ső di-a [G]szö-ve-ge
%pagebreak169
[Am]Má-so-dik di-a [F]szö-ve-ge
```
