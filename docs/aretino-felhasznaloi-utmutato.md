# Aretino — felhasználói útmutató

> Magyar katolikus gregorián notáció szöveges formátumban.
> Verzió: 1.0 · Utolsó frissítés: 2026-05-18

```aretino
(g2) g h i g. hi h g e_d_ , g hi a'g g. ||
w: Al-le-lu-ja, al-le-lu-ja, al-le-lu-ja.
```

Ez az útmutató lépésről lépésre, példákkal mutatja be az **Aretino** kottaformátum használatát.
A formátum/tördelési algoritmus még változhat, a visszajelzéseket köszönettel fogadjuk!

Ezen az oldalon kipróbálható, gyakorolható a formátum használata. Az összes beállítási lehetőséggel ellátott szerkesztő a [Kottaszerkesztő](/score/preview) oldalunkon található.

Az Aretino kottaformátum szabadon felhasználható, kérjük, nyilvános anyagokban hivatkozzanak honlapunkra: [Cantores.hu](https://cantores.hu)

---

## Tartalomjegyzék

1. [Mi az Aretino?](#1-mi-az-aretino)
2. [Elvi háttér — a "modernizált metzigót" átírás](#2-elvi-háttér--a-modernizált-metzigót-átírás)
3. [A Guido font, mint szellemi előd](#3-a-guido-font-mint-szellemi-előd)
4. [Miért lép tovább az Aretino?](#4-miért-lép-tovább-az-aretino)
5. [Első kotta](#5-első-kotta)
6. [Fejléc](#6-fejléc)
7. [Kulcsok](#7-kulcsok)
8. [Hangmagasság](#8-hangmagasság)
9. [Kottafej-típusok](#9-kottafej-típusok)
10. [Módosító utótagok (mora, episema, ictus, liquescens)](#10-módosító-utótagok)
11. [Ligatúrák — neumák](#11-ligatúrák--neumák)
12. [Neuma-tagoló rés (`/`)](#12-neuma-tagoló-rés)
13. [Vonalak és tagolójelek](#13-vonalak-és-tagolójelek)
14. [Sorvégi kiegyenlítés, manuális elosztás és sortörés](#14-sorvégi-kiegyenlítés-manuális-elosztás-és-sortörés)
15. [Módosítójelek (b, kereszt, feloldó)](#15-módosítójelek)
16. [Szöveg és versszakok](#16-szöveg-és-versszakok)
17. [Hosszabb példák](#17-hosszabb-példák)
18. [A szerkesztő használata](#18-a-szerkesztő-használata)
19. [Gyakori hibák és tippek](#19-gyakori-hibák-és-tippek)

---

## 1. Mi az Aretino?

Az **Aretino** egy szöveges formátum a magyar katolikus gregorián gyakorlat
lejegyzésére — Dobszay László és Szendrei Janka konvencióját követi:

- **ötvonalas** kottarendszer,
- alapértelmezett **violinkulcs**,
- hagyományos **kerek** kottafejek (nem kvadrát),
- de **gregorián ritmika** és jelölésrendszer: mora, episema, likveszcencia,
  quilisma, ligatúrák.

A név Guido d'Arezzóra (latinul *Guido Aretinus*) utal — a régi *Guido HU/EN*
TTF betűkészlet szellemi utódja, de attól független, szemantikus formátum.

### Tervezési filozófia röviden

| Elv | Mit jelent a gyakorlatban? |
|---|---|
| **Pozíció = hangmagasság** | A betűkód határozza meg, melyik vonalon/vonalközben van a hang. |
| **Szemantikus jelölők** | Az episema, mora stb. utótag-karakterek, nem mágikus glyph-fájlok. |
| **Külön sor a szövegnek** | A `w:` előtagú sor önállóan értelmezhető. |

---

## 2. Elvi háttér — a "modernizált metzigót" átírás

Az Aretino nem a semmiből nőtt ki: egy közel négy évtizedes magyar
gregorián-átírási iskola örököse. A szakmai alapot Dobszay László
[A gregorián átírásról](https://egyhazzene.hu/wp-content/uploads/2018/12/gregorian_atiras.pdf) című tanulmánya (és Szendrei Janka paleográfiai
munkái) fektették le. Az alábbiakban röviden összefoglaljuk azokat az
elveket, amelyekből az Aretino mai formája is következik.

### Az átírás alapproblémája

Dobszay éles megfogalmazása szerint a gregorián átírás valódi nehézsége
**nem** a vonalak száma vagy a kulcsok kérdése — ezek korokon át
változtak, és a lényeg érintése nélkül változtathatóak ma is. A
tulajdonképpeni kérdés **szemléleti**:

> "A korai neumaírások nagy része nem a hangok sorát akarta jelölni…
> hanem azt, hogy a szöveg egyes szótagjait miféle dallamalakzaton kell
> kiejteni. Ennek a dallamalakzatnak jelzése a notáció, s mint egységes
> jel fejezi ki a 3–4 hangos figurát."

Másként szólva: a gregorián dallamot az énekli jól, aki **egy szótagra
eső dallamalakzatot egységként** képzeli el és szólaltatja meg — nem
hangról hangra építi össze a melizmát. Az a jó modern átírás tehát,
amely az olvasónak ezt a „neumatikus” hallásmódot sugallja.

### Két klasszikus zsákutca

1. **Kvadrát-kotta reprodukciója** — történeti, de a mai énekestől
   böngészést követel; a négy vonal és a C-/F-kulcs még a kisebb baj,
   a vertikálisan írt pes, a porrectus íves vonala, a rombikus hangfejek
   viszont félrevezetik a modern olvasót.
2. **Nyolcad–negyed értékes „modern” átirat** — nyilvánvalóvá teszi,
   hogy átiratról van szó, de a metrikus ritmusértékek **hamis ritmikai
   képzeteket** kapcsolnak a dallamhoz.

A 20. század végére mindkét megoldást felváltotta egy harmadik — a
modern, **szár nélküli kerek kottafejek + kötőjelek** módszere. Dobszay
ezt is élesen bírálja: ez a kottakép a dallamot egyes hangok hosszú
sorozataként jeleníti meg, és ugyanezt a hallásképet ülteti az énekesbe
is. Az elterjedéséhez két ok vezetett: (1) a leíróban nem él erősen
a dallamalakzat-szerinti hallás; (2) a számítógépes kottaírás triviálisan
elő tudja állítani ezt a primitív kottaképet.

### A megoldás iránya — a metzigót notáció

Van egy középkori notációs rendszer, amely a „modern” szemléletmóddal
egybehangzóan értelmezi a két tengelyt (vízszintes = idő, függőleges =
hangmagasság), **és** közben megtartja a hangcsoportok vizuális
összetartozását: a **metzigót notáció**. A budapesti gregorián-kutatók
(mindenekelőtt Szendrei Janka) ezt vették alapul, és modern kerek
kottafejekkel kombinálva dolgozták ki azt a rendszert, amelyet ma
"budapesti átírás" vagy "modernizált metzigót" néven említünk.

### A modernizált metzigót tíz pontja (Dobszay)

A tanulmány IV. fejezete tíz pontban foglalja össze az elveket. Az
Aretino lényegében ezt a rendszert kódolja szöveges formátumban:

1. **Vízszintes = idő, függőleges = hangmagasság.** (A modern szemlélettel
   egybehangzóan.)
2. **Tetszőleges kottafej**, természetesen modern **kerek** is lehet.
3. **Egy neumába tartozó hangokat** olyan **közel írjuk** egymáshoz,
   amennyire az olvashatóság engedi.
4. **A neuma egységét a relatíve legmagasabb hang baloldalán lefelé
   bocsátott szár fejezi ki.** Ilyen szárat kap tehát:
   - a pes 2., a clivis 1., a torculus középső, a porrectus 1. és 3.,
     a scandicus 3., a climacus 1. hangja — **mindig a környezetében
     legmagasabb hang**.
   - Ha a neumában az irány változik, annyiszor ismételjük a szárat,
     ahány új csúcs adódik.
5. **Lefelé lépő hangcsoportokat** (clivis stb.) **vékony függőleges
   vagy enyhén ferde összekötő vonallal** kapcsoljuk össze. Szekundnál
   elhagyható, **nagyobb hangközöknél kötelező**.
6. **Hosszabb melizmák belső tagolása** kétféleképpen:
   - vagy újabb szárakkal (más-más artikulációt jelezve),
   - vagy egy **kottafejnyi üres rés** beiktatásával a kis csoportok között.
7. **Hangkettőzések** szabadon használhatók; ha a kettőzött hangból indul
   lefelé a mozgás, a második hangra szár jöhet.
8. **Vessző (plica)** kétféleképpen: kis belső elválasztó, vagy egy
   hanghoz ragasztva annak szeparálása a csoporton belül.
9. **Liquescencia** jelölhető csökkentett méretű hanggal — vagy ha ilyen
   jelünk nincs, plicával ellátott hanggal.
10. **A vonalak száma és a kulcsok kérdése független** a rendszer
    lényegétől.

A 4–5. pont a rendszer szíve: a **csúcson lefelé bocsátott szár** és az
**ereszkedő hangokat összekötő ívelt/ferde vonal**. Ettől válik a
gregorián notáció egyszerre **modernül olvashatóvá és neumatikussá**.

---

## 3. A Guido font, mint szellemi előd

A modernizált metzigót átírás első, gyakorlatilag használható
számítógépes megvalósítása **Bali János** munkája volt: a **Guido**
nevű TTF betűkészlet (a régi *Guido HU/EN* font). A név Guido d'Arezzo
(*Guido Aretinus*) — a hangrendszer és a négy vonalas notáció középkori
atyja — előtt tiszteleg; az Aretino innen örökli a nevét is.

### Hogyan működött a Guido?

A Guido **nem program, hanem fontkészlet** — a felhasználó egy
sima szövegszerkesztőben (Word, OpenOffice stb.) a betűtípust Guido-ra
állította, és karakterről karakterre gépelte a kottát. A főbb elvek:

- **Nem készre húzott kottavonalra dolgozott**: a hangjegyek
  hozták magukkal a hozzájuk tartozó kottavonal-darabot.
- **Számbillentyűk = punktum** (egyvonalas c-től kétvonalas á-ig);
  páros szám = vonalon ülő hang, páratlan = vonalközben.
- **Shift** ugyanazon a billentyűn = **plica** (vesszős kis jel).
- **Számsor alatti betű** = **virga** (szárral ellátott hang).
- **Lefelé lépő szekund/terc/kvart** = külön billentyűsorok kombinációi.
- A **szöveget külön sorba** írta a felhasználó, és **szóközökkel
  igazította** a hangok alá — a szótag nem tapadt a hanghoz.

Egy tipikus forrássor a Guido-ban így nézett ki:

```
<-4--4t---tT4--t4--tg3--tG2--5zZ5--4uU6---7uU6Z5T4
```
![Guido példa](/guido-pelda.png)

Minden pozíción levő minden hang minden változatához más és más billentyűleütés tartozott:
`4 t` = pes, `t T 4` = szekund clivis összekötő vonallal, `5 z Z 5` =
torculus, `4 u U 6` = másik torculus, stb. Ráadásul bizonyos jelöléseket így sem lehetett megoldani (egyszerűen nem volt elég karakter).

Ugyanez Aretino-val, egyszerűen megadjuk a hangok neveit és minden automatikus:

```aretino
(g2) g gh hg H/g hf he hih gji jjihg
```

### Mit hozott a Guido?

Forradalmian sokat:

- **bárki gépén futott**, telepítés szinte nulla,
- pillanatokon belül lehetett vele kottát szedni,
- **megőrizte a metzigót notáció elveit** — a szárak, az ívelt
  összekötő vonalak, a plica mind elérhetők voltak,
- és olcsó volt: nem kellett hozzá szakember, nyomdai metszés.

---

## 4. Miért lép tovább az Aretino?

A Guido **karakter-szintű, manuális tipográfia** — a felhasználó
a billentyűzet segítségével „rajzolást” végez: minden jelet maga rak ki, minden
távolságot szóközzel állít, melyik szár melyik hanghoz tartozik, azt
neki kell tudnia és odapozícionálnia. Ez **a betűtípus alapú technika
korlátja**, nem hiba — de a következménye, hogy:

- a kottakép **statikus**: ha a szótagok átrendeződnek vagy a kotta
  átméretezésre kerül, az igazítás összeomlik;
- **automatizmus nincs**: szárakat, összekötő vonalakat, neuma-távolságot
  mind kézzel kell beállítani;
- **a szöveg és a hangok között nincs szemantikai kapcsolat**:
  a "Ky-ri-e" szótagjai csak vizuálisan állnak a hangok alatt;
- **nincs sortörés, nincs justify**: minden sor manuálisan szabva;
- **a forrás nem értelmezhető**: a `<-4--4t---tT4` karaktersorozat
  csak a Guido betűkészlet pozícióinak rajza, nem szemantikus jelölés, nagyon körülményes szerkeszteni

Az **Aretino** ugyanazt a notációs hagyományt — a modernizált metzigót
átírást — **szemantikus, szöveges
formátumként** valósítja meg. Ami ezzel jár:

| Szempont | Guido (TTF font) | Aretino (szemantikus formátum) |
|---|---|---|
| **Reprezentáció** | egy hangjegy minden variácója külön karakter | a hangjegyek mindig ugyanazt jelentik, módosítójelek vannak |
| **Virga, szárazás** | a felhasználó rakja ki, mindig kézzel | **automatikus**: a megjelenítő minden helyi maximumra szárat tesz, de manuálisan is lehet |
| **Lefelé összekötő vonal** | külön billentyű-kombináció | **automatikus**: a megjelenítő rajzolja minden ereszkedőre, hangköz szerint igazítva |
| **Neuma-távolság** | manuális — szóközökkel és kötőjelekkel | **automatikus**: a megjelenítő helyezi el a neumákat |
| **Sorvégi kiegyenlítés** | nincs | **automatikus** és manuális sorkiegyenlítés (`(z)` direktívák) |
| **Szótag–hang igazítás** | szóközzel a felhasználó | **automatikus**: szótag a megfelelő neuma alá |
| **Több versszak** | mindegyik külön sor, kézzel igazítva | `w:` sorok, mindegyik igazítva |
| **Skálázás** | font-pontméret változtatja | tetszőleges, automatikus újratördeléssel, lehetővé téve a különböző megjelenítési méreteket |
| **Tördelés/újraszedés** | nincs — a sor csak addig fér, ameddig betűzi | **automatikus sortörés** a margónál, kulcs ismétlésével |
| **Forrás hordozhatósága** | TTF-fontfüggő, font nélkül értelmezhetetlen | tiszta UTF-8 szöveg, a betűtípus nélkül is olvasható és szerkeszthető |
| **Bővíthetőség** | gyakorlatilag lehetetlen | korlátlan |

### A koncepció összefoglalása

A Guido azt mondja meg, hogyan nézzen ki a gregorián kotta. Az Aretino azt próbálja leírni, mit jelent a kotta zeneileg: hol vannak a szótagok, neumák, hangsúlyok, dallami kapcsolatok és notációs jelenségek. Ez olyan különbség, mint egy kottakép lemásolása és egy valódi zenei forrás rögzítése között. Az egyik szép képet ad, a másikból a gép is érti, hogy mi történik a dallamban, és szebb képet eredményez.

Ráadásul a kotta esztétikai megjelenése és a szárazási elvek betartása a Guido esetében a szerkesztőtől függ, az Aretino lehetővé teszi, hogy esztétikus és a szabályoknak megfelelő kottaképet készítsünk automatikusan, csak a hangok megadásával.

### Mit őriz az Aretino a hagyományból?

Mindent, ami a Dobszay–Szendrei iskola lényege:

- **kerek kottafejek**, ötvonalas rendszer, modern kulcsok (10. pont);
- **virga-szár** (4. pont) — automatikusan, minden helyi maximumra;
- **ereszkedő összekötő vonal** (5. pont) — automatikusan, hangköz szerint;
- **neuma-tagolás kottafejnyi réssel** (6. pont) — a `/` operátor;
- **mora, episema, liquescentia, quilisma** (8–9. pont) —
  utótag-karakterek formájában;
- **a vonalak és kulcsok kérdésének függetlensége** (10. pont) —
  kulcs cserélhető, és nem érinti a `a–m` jelölést.

Az Aretino tehát **nem szakít** a hagyománnyal — éppen ellenkezőleg:
a **lényeget** automatizálja, így a felhasználónak csak a zenei
tartalomra kell figyelnie.

---

## 5. Első kotta

```aretino
(g2) d f g h.
```

Ez egy violinkulcsot (G a 2. vonalon), majd négy punctumot rajzol —
az utolsóra morával (nyújtóponttal).

Egy minimum-példa szöveggel és fejléccel:

```aretino
;title: Első kísérlet
%%
(g2) d f g h.
w:   Pró-ba kot-ta.
```

A három fő építőelem:

1. **Fejléc** — elhagyható, `;kulcs: érték` sorok, `%%` zárja.
2. **Dallam-sor** — hangok, ligatúrák, vonalak. Az első zárójeles elem
   a kulcs (`(g2)`, `(f4)`, `(c3)`).
3. **Szöveg-sor** — `w:` előtaggal, közvetlenül a dallam alatt.

---

## 6. Fejléc

A fejléc-sorok pontosvesszővel kezdődnek (`;kulcs: érték`), és a `%%` lezárja:

| Kulcs | Leírás |
|---|---|---|
| `title` | Cím, középre igazítva, félkövér. |
| `caption` | Felirat, jobbra igazítva, dőlt. |
| `indent` | Behúzás az első sor elején. Ha értéket adsz (pl. `I.d`), kis betűvel jelenik meg. |


```aretino
;title: Kezdő fohász
;caption: Vesperás
;indent: VII.
%%
(g2) h h h g h j i g h. ||
w: Is-te-nem, hall-gass hí-vá-som-ra!
```

A fejléc **elhagyható**, lehet rögtön dallamsorral kezdeni.

---

## 7. Kulcsok

A kulcsot zárójelben adod meg: betű + sorszám.

| Forrás | Kulcs | Megjegyzés |
|---|---|---|
| `(g2)` | G-kulcs a 2. vonalon | Violinkulcs |
| `(f4)` | F-kulcs a 4. vonalon | Basszuskulcs |
| `(c3)` | C-kulcs a 3. vonalon | Kis szögletes C-jel (nem hagyományos altkulcs). |

A kulcs általában a dallamsor első eleme. Sor közben is válthatsz kulcsot:

```aretino
(g2) d f g h  (c3) e g h (f4) i h g
```

Sortörés után a megjelenítő automatikusan kirajzolja az
aktuális kulcsot.

---

## 8. Hangmagasság

A hangokat **a–m** kisbetűk jelölik. A betű mindig ugyanazt a sort/vonalközt
jelenti, **függetlenül a kulcstól**:

```aretino
a b c d e f g h i j k l m
w: a b c d e f g h i j k l m
```

Tehát G-kulcsban `c` az 1. vonalon C-hang, `g` a 3. vonalon B-hang stb.
F-kulcsban ugyanaz a `c` az 1. vonalon E-hang lesz (mert a kulcs változik,
de a vonal-pozíció nem).

### Emelt oktáv — aposztrof

```aretino
a' b' c' d' e' f' g' h'
w: a' b' c' d' e' f' g' h'
```

---

## 9. Kottafej-típusok

A kottafej alapformáját egy **utótag-karakter** módosítja a betű után:

| Forrás | Név | Rajz |
|---|---|---|
| `d` | **punctum** | töltött kerek kottafej, szár nélkül |
| `D` | **virga** | punctum bal oldalán lefelé mutató szárral (nagybetű!) |
| `dw` | **quilisma** | csíkozott, cikkcakkos kontúrú kottafej |
| `dt` | **tenor-hang** | üres kottafej, két oldalán függőleges vonalkák |
| `ds` | **kiskotta** | kis méretű kottafej |

### Példák

```aretino
(g2) d D dw dt ds
```

Bal → jobb: punctum, virga, quilisma, tenor-hang — mind ugyanazon a magasságon (D).

A **virga** gyakran ligatúra-csúcsokon jelenik meg automatikusan (lásd a [Ligatúrák](#11-ligatúrák--neumák) szakaszt), de manuálisan is használhatjuk arra, hogy hosszabb melizmák belső tagolódását jelezzük.

A **quilisma** mindig ligatúrában fordul elő:

```aretino
(g2) dfwg
```

Itt `f` után `w` jelöli, hogy az `f` quilisma.

---

## 10. Módosító utótagok

A kottafej után, **szóköz nélkül**, kombinálható utótagok:

| Utótag | Név | Jelentés |
|---|---|---|
| `.` | **mora** (nyújtópont) | jobbra a kottafejtől, hosszú hangot jelez |
| `_` | **episema** | rövid vízszintes vonal a kottafej fölött. Egymást követő episema-kat a rendszer összevon. |
| `-` | **ictus** | kis függőleges vonal a kottafej fölött (a vonalközben) |
| `~` | **liquescens** | kis „farok” a kottafej jobb felső sarkán |

```aretino
(g2) d d. d_ d- d~ d_e_d_
```

---

## 11. Ligatúrák — neumák

Az **egymás után, szóköz nélkül** írt hangbetűk egy ligatúrát (neumát)
alkotnak. Ez az Aretino egyik leglényegesebb mechanizmusa.

| Forrás | Név | Jelentés |
|---|---|---|
| `df` | **podatus** | felfelé lépő kettős, alsóról a felsőre vivő ívvel |
| `fd` | **clivis** | lefelé lépő kettős, ívelt kalligrafikus vonallal |
| `dfd` | **torculus** | hármas: fel-le |
| `fdf` | (völgy hármas) | le-fel — egyedi ligatúraként rajzolódik |
| `dfgf` | hosszabb neuma | tetszőleges hosszú ligatúra |

### Egy hang vs. ligatúra

```aretino
df fd dfd fdf dfgf
```

- `d f g` → három **különálló** punctum (szóköz választja el).
- `df g` → egy **podatus** (`df` egybe), majd egy különálló `g`.
- `dfg` → egy **hármas ligatúra** (torculus jellegű, fel-fel).

```aretino
d f g | df g | dfg
```


### Automatikus virga csúcsokon

A megjelenítő minden ligatúra-csúcsra (helyi maximumra) automatikusan
virga-szárat tesz — ezt nem kell kézzel jelölni. Például `dfd` (torculus)
esetén az `f` (csúcs) automatikusan virga-szárral rajzolódik.

```aretino
dfd ihgfghghjijigh
```

## 12. Neuma-tagoló rés

A `/` (per-jel) egy **kis lélegzetnyi** rést tesz egy ligatúrán belül —
gyakorlatilag a melizmán belüli csoportosítást teszi láthatóvá. A `/` előtti
és utáni hangok továbbra is **egy ligatúrához** tartoznak, csak vizuálisan
elválaszthatók.

```aretino
(g2) fefdc.efdc./feg.gggee/cededdc. c

```

Szóközt **nem** írhatsz a `/` köré ligatúrán belül — az a ligatúrát
megszakítaná, és külön neumákat csinálna belőle.

---

## 13. Vonalak és tagolójelek

| Forrás | Név | Funkció |
|---|---|---|
| `,` | rövid vonal (negyedvonal) | kis cezúra, lélegzet |
| `;` | félvonal | tagmondat vége |
| `\|` | egész vonal | mondat vége |
| `\|\|` | kettős vonal | rész vége |
| `:\| \|: :\|:` | ismétlőjel | ismétlés |
| `\|\|\|` | záróvonal | klasszikus záró |
| `'` | apró szünetjel | lélegzetvétel |

```aretino
' , ; | || :| |||
```

A vonalak írhatók zárójelben is: `(,)`, `(;)`, `(|)`, `(||)`, `(:|)`, `(|||)` — a hatás
ugyanaz, de a zárójeles forma a hagyományos GABC-felhasználóknak ismerős
lehet.

Ha a szövegben zárójelben szerepel valami, azt a következő ütemvonal alá rendezzük, a lenti példában a `(*)` jelöli, hogy a rövid vonal alá kell írni egy * jelet.

```aretino
(g2) (K:fb#) h h h f h i j ih h_ , h h h f g hg e d. d. ; f f f g e g f_ , e d e e e g f d. d. || (Z) ht i ht g ht ||
w: Men-je-tek, és vi-gyé-tek hí-rül: (*) föl-tá-madt az Úr, al-le-lu-ja! Néz-zé-tek ü-res sír-ját, a-hol nyu-go-dott, al-le-lu-ja!
```

---

## 14. Sorvégi kiegyenlítés, manuális elosztás és sortörés

Az Aretino megjelenítő igyekszik kedvezően elosztani a neumákat/szótagokat, néha mégis szükség lehet manuális beavatkozásra.

### Csillag `*` — sorkizárt sorok elosztása

A `*` egy üres, „rugalmas” szakasz, amellyel befolyásolhatjuk, hogy egy sorkizárt szakasz hol legyen szellősebb:

```aretino
(g2) d f * g h * g (z) f d  (||)
```

Több `*` is használható egy sorban; a maradék helyet egyenlően elosztja
köztük.

### Spacer `(sp)` és `=` — fix méretű rés

Ha **fix** szélességű rést akarsz (nem rugalmas, mint a `*`), használd
a `(sp)` direktívát. Szorzóval is megadható: `(sp2)` = 2× alapszélesség,
`(sp0.5)` = fél szélesség, illetve az `(sp)`-nek azonos jelentésű a `=` jel.

```aretino
(g2) d f (sp2) g = h ==== f
```

### Explicit sortörés `(z)` és `(Z)`

A megjelenítő maga is tördel: ha egy sor nem fér ki, a következő hangot új
sorba viszi. Explicit sortörést is kérhetsz:

| Forrás | Hatás |
|---|---|
| `(z)` | sortörés-javaslat, a kötés **kiegyenlítve** (justified) — a sor a margóig kitölt |
| `(Z)` | sortörés-javaslat, **nem** kiegyenlítve — a sor balra zárt |

A `(z)` formát használd ott, ahol a frázis-vége természetesen indokol
sortörést.

```aretino
(g2) g h i j (z) g h i j (Z) g h i j ||
```


---

## 15. Módosítójelek

| Forrás | Név | Jelentés |
|---|---|---|
| `(bx)` | aktuális b | egyszeri b a `i` hang magasságán (3. vonal, B-hang) |
| `(by)` | feloldó | a megelőző alteráció feloldása |
| `(b#)` | kereszt | félhanggal emelt |

A `b` előtt a hang betűje adja meg a magasságot: `(ebx)` = b az E hangon,
`(fbx)` = b az F-en stb. Ha csak `(bx)`, akkor a B-hangon jelenik meg
(3. vonal, `i` magasság).

### Példa

```aretino
(g2) (ibx) (sp) (iby) (sp) (ib#) (sp) : h (ibx) hih fgh. g(ibx)hih
```

A módosítójeleket a következő neumával egyben tartjuk. (Neumán belül is használható módosítójel.)

### Előjegyzés — `(K:...)`

Az előjegyzést a kulcs után helyezzük el. A megjelenítő minden új sor
elején automatikusan kiteszi a kulcsot követően, akkor is, ha a kulcs
csak a darab elején van leírva.

| Forrás | Jelentés |
|---|---|
| `(K:bx)` | b-előjegyzés a 3. vonalon (B-hang, `i` magasság) |
| `(K:ebx)` | b az E hangon |
| `(K:bx ebx)` | több módosítójel — szóközzel elválasztva |
| `(K:)` | előjegyzés törlése |

```aretino
;title: Példa előjegyzéssel
%%
(g2) (K:mb# jb# ) d e f g h i j k (||)
```

Az `(K:bx)` minden új sor elején megismétlődik. Egy újabb `(K:…)` token
megváltoztatja az előjegyzést onnantól (helyben is megjelenik, és a
következő sorok elején is az új jel szerepel). `(K:)` törli az
előjegyzést.

---

## 16. Szöveg és versszakok

### Szótagok illesztése

A megjelenítő **automatikusan illeszti** a szótagokat a hangokhoz: minden
szótag a megfelelő neuma (vagy különálló punctum) középpontja alá kerül.
A kötőjeles tagolás (`Ky-ri-e`) a szótaghatárt jelöli, a kötőjelek
a hangok között automatikusan megjelennek, ha van hozzá hely.

A szabály egyszerű: **egy szótag — egy neuma vagy egy különálló hang**.
Egy ligatúra (pl. `df`) egyetlen egységnek számít, így egy szótag jut rá;
egy különálló punctum (pl. `d` szóközökkel a két oldalán) szintén egy
egységnek számít, és egy szótag jut rá.

```aretino
(g2) dghfe ed , g hg ghj h hghgfg(ibx)ihig fhgfgfe e:
w: Hús-vét ün-ne-pe e-lőtt tör-tént:
```

A tördelő algoritmus megpróbálja a gazdaságosan és esztétikusan elhelyezni a szöveget. Ez azt jelenti, hogy ha megoldható, a szótagokat megpróbálja összevonni. Az egyes szótagokat punctum alatt középre rendezi, neumák alatt, tenorhang alatt és mora-s punctum alatt pedig balra rendezi.

### Több versszak

A dallamsor alá több `w:` sor is írható — minden új sor egy versszak:

```aretino
(g2) d c d f g f e d. ,
w: Vic-ti-mae pas-cha-li lau-des
w: A hús-vé-ti szent Bá-rány-nak
```

### Több szó ugyanarra a hangra (`~`)

Ha több szót (szótagot) kell **egyetlen hangra** írni — például recitáló
tenor-hang alatt —, kösd össze őket `~` jellel szóköz nélkül:

```aretino
(g2) f g ht h g h :
w: szent vagy, mindenség~Ura, Is-te-ne!
```

A `~` jel arra is használható, hogy átugorjunk hangokat, kihagyjunk szöveget:

```aretino
f g ; h g
w: ~ ~ szö-veg
```

### Verszak számozás

Hogy a szöveg tördelését ne zavarják a verszakok számai, R., V. egyéb jelölés, a `~~` jellel kell öszekötnünk. Első versszak esetén manuális térközt kellhet alkalmaznunk.

```aretino
(g2) = g g g h g gj j ' jt
w: 1.~~Ki-rá-lyok-nak Ki-rá-lya (†) és~Atyja...
```

### Speciális karakterek
Néhány karaktert speciálisan jelenítünk meg.

```aretino
c d e f
w: R/ V/ + ++
``` 

### Szövegformázás (dőlt, félkövér)

A szöveges sorokba egyszerű formázó jelöléseket is tehetünk:

- `<i>szöveg</i>` — *dőlt* (italic)
- `<b>szöveg</b>` — **félkövér** (bold)

A formázás tetszőleges szótagokra alkalmazható, és a szótaghatáron át is érvényes marad, amíg a záró tag meg nem jelenik.

```aretino
(g2) g h i g. hi h g e_d_ , g hi a'g g. ||
w: <b>℟.:</b>~~Al-le-lu-ja, <i>al-le-lu-ja</i>, al-le-lu-ja.
```

---

## 17. Hosszabb példák

### Egyszerű Kyrie

```aretino
;cím: Uram, irgalmazz (XVI.)
%%
(g2) (K:ibx) h h h g h fg h ||
w: U-ram, ir-gal-mazz né-künk! (<i>3x</i>)

h h h g h fg h ||
w: Krisz-tus, ke-gyel-mezz né-künk! (<i>3x</i>)

h h h g h fg h ||
w: U-ram, ir-gal-mazz né-künk! (<i>2x</i>)

h g i g f gh h ||
w: U-ram, ir-gal-mazz né-künk!
```

**Mit lehet itt megfigyelni?**

- A kulcsot csak az első sorban kell megadni — a megjelenítő az új rendszerek elejére automatikusan kiteszi.
- Üres sorok **új szakaszt** indítanak.
- Az ütemvonal alá rendezünk szöveget.

### Antifóna zsoltárdallammal

```aretino
;title: Hints meg engem
%%
(g2) (K:mb#) d e g f gh h , i j i h i h ge d | d e gfgh h , i g ge ggfg h g e d d ||
w: Hints meg en-gem U-ram, i-zsóp-pal és meg-tisz-tu-lok, moss meg en-gem, és fe-hé-rebb le-szek a hó-nál.

f g ht gs ht g h i- gs g | ht i h gs- gf e ||
w: ~ ~ ~ † (*)
```
---

## 18. A szerkesztő használata

Az Aretino formátum a [kottaszerkesztőben](/score/preview) elérhető — a formátumválasztóban
válaszd ki az **Aretino** opciót. Élő előnézet jelenik meg a forrás alatt.

### A beállító-sáv elemei

| Vezérlő | Mit állít? |
|---|---|
| **Nagyítás** | Az előnézet zoom-szintje (csak megjelenítés). |
| **Kotta méret** | A vonalrendszer fizikai mérete pontban (alapérték 100). |
| **Szöveg méret** | A liturgikus szöveg betűmérete. |
| **Szöveg betűtípusa** | Előre megadott, szabadon felhasználható betűtípusok |
| **Oldalarány** | Az exportált oldal képaránya (`auto` = adat-méret szerinti). |
| **Sorok távolsága** | A kottarendszerek közötti függőleges szóköz állítása (0 = alapértelmezett). |

### Oldaltörés

Ha az oldalarányt `16:9`, `4:3` vagy `1:1` értékre állítod, a forrásban `%pagebreak` utasítással
új oldalt (diát) kezdhetsz. A töréspont csak a megfelelő arányhoz érvényes:

| Utasítás | Mikor törés? |
|---|---|
| `%pagebreak` | minden rögzített aránynál |
| `%pagebreak169` | csak 16:9 arányban |
| `%pagebreak43` | csak 4:3 arányban |
| `%pagebreak11` | csak 1:1 arányban |

`auto` módban az összes `%pagebreak` sor figyelmen kívül marad — a kotta egyetlen egységként jelenik meg.

### Export

A kotta SVG és PNG formátumban exportálható és képként másolható is. Így szöveg- és kiadványszerkesztőben akár professzionális nyomtatással is előállítható.

---

## 19. Gyakori hibák és tippek

### "A ligatúra nem áll össze"

**Tünet:** két hang közé szándékodnál hosszabb rés került.
**Ok:** szóköz van a hangok között.
**Megoldás:** írd egybe — `d f` (két punctum) helyett `df` (podatus).

### "Túl sok virga-szár jelent meg"

**Tünet:** ligatúra-csúcsokon kéretlenül szárak jelennek meg.
**Ok:** ez a normál viselkedés — az auto-virga minden csúcsra szárat tesz.
**Megoldás:** ha tudatosan nem akarsz virga-csúcsot, írd át a ligatúrát
külön punctum-okra (szóközzel) ott, ahol nem akarod az automatikust.

### "A szöveg nem igazodik a hangokhoz"

**Tünet:** szótagok nem a megfelelő egység alá kerülnek.
**Ok:** valószínűleg a szótag-tagolás nem stimmel — vagy hiányoznak a
kötőjelek (`Kyrie` helyett `Ky-ri-e`), vagy ott írtál ligatúrát, ahol
külön hangokat kéne (vagy fordítva).
**Megoldás:** ellenőrizd, hogy minden szótaghoz **pontosan egy** neuma
vagy különálló hang tartozik-e. Ha egy szótag alá több hang kell, írd
őket ligatúrába (szóköz nélkül egybe).

### "A módosító nem jelenik meg a megfelelő hangon"

**Tünet:** mora vagy episema nem ott jelenik meg, ahol kellene.
**Ok:** az utótag-karakter csak az **előtte álló** hangra vonatkozik.
**Megoldás:** `df.` → az `f`-hez tartozik a mora. Ha a `d`-hez szeretnéd,
írd `d.f`-nek (de figyelj: a szóköz nélküli ligatúrában a `d.` előbbi
hangot módosít, és a `f` a következő hang).

### "Üres sor lett a kotta közepén"

**Tünet:** váratlan kötőjel a két szakasz között, vagy szétesik az
illesztés.
**Ok:** üres sor **új szakaszt** indít a parserben.
**Megoldás:** ha nem akarsz új szakaszt, ne hagyj üres sort a dallam
és szöveg között.

## 20. Visszajelzés, hibajelentés

Az Aretino formátumot és a megjelenítő szoftvert a Szent József Hackathon keretében, önkéntes fejlesztők készítik.
A forráskód szabadon elérhető a [GitHubon](https://github.com/szentjozsefhackathon/cantores), ahol hibákat is lehet jelenteni, illetve fejlesztési javaslatokat is szívesen fogadunk.

Ha kérdésed/javaslatod van, írj nekünk az [info@cantores.hu](mailto:info@cantores.hu) címre, vagy a [Facebook](https://www.facebook.com/people/Cantoreshu/61588419360930/) oldalunkon.
