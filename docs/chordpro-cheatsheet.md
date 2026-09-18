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
| `%section` | szakasz kezdete; a füzetben és a vetítésben kiválasztható; a sor magából a dalszövegből törlődik |
| `%section Címke` | ugyanaz, névvel a szerkesztőben |

| Vetítés | |
|---|---|
| **Oldalarány** | A szerkesztő eszköztárában: `Papír` a szokásos lapszerű nézet, `16:9`, `4:3` és `1:1` a vetített dia. |
| `%pagebreak` | minden rögzített képaránynál új dia |
| `%pagebreak169` | csak 16:9 vetítésben |
| `%pagebreak43` | csak 4:3 vetítésben |
| `%pagebreak11` | csak 1:1 vetítésben |
| `%pagebreak?` | javaslat: csak akkor kezd új diát, ha a szöveg másképp nem férne ki |
| `%pagebreak169?` | ugyanez, egyetlen képarányra (`43`, `11` ugyanígy) |

`Papír` módban minden `%pagebreak` sor figyelmen kívül marad — a dalszöveg egyetlen
egységként jelenik meg.

Rögzített képarányon a dalszöveg nem szakad meg a dia alján: ami nem fér ki, az a
következő diára kerül. A vágás sorrendje mindig ez:

1. a `%pagebreak` sorok — ezek mindig vágnak;
2. ami így is hosszú, a saját `%pagebreak?` javaslatainál törik, és csak annyinál,
   amennyi feltétlenül kell;
3. ami még mindig nem fér ki, versszakhatáron törik. Egy versszak nem szakad
   ketté, hacsak az egyben tartása nem kerülne egy egész diába;
4. ha mégis ketté kell szakadnia, a leírt sorok határán szakad — egy sor tördelt
   darabjai együtt maradnak, és a szakaszfelirat a hozzá tartozó sorral.

A hosszú sorok maguktól tördelődnek a következő sorba, és az akkord a saját
szótagjával marad. Ha egyetlen leírt sor magasabb az egész diánál, az inkább
kettétörik, mint hogy a vége lelógjon; figyelmeztetés csak akkor marad, ha már
egyetlen tördelt sor sem fér ki — ott kisebb betűméret a megoldás.

Rögzített képarányra váltva a lap a vetítéshez igazodik: keskenyített
betűtípusra (Barlow Condensed), egy hasábra és a kivetítőről is olvasható
méretre. Az eszköztár ilyenkor 144 pt-ig engedi a betűméretet, a hasábok
beállítása pedig eltűnik: a dián mindig egy hasáb van. Ezek a beállítások
képarányonként külön tárolódnak, így a papírra szánt elrendezés érintetlen marad.

```
{title: Példa}
[C]El-ső di-a [G]szö-ve-ge
%pagebreak169
[Am]Má-so-dik di-a [F]szö-ve-ge
```
