# Aretino — felhasználói útmutató

> Magyar katolikus gregorián notáció szöveges formátumban.

```aretino
(g2) g a b g. ab a g e_d_ , g ab ag g. ||
w: Al-le-lu-ja, al-le-lu-ja, al-le-lu-ja.
```

Ez az útmutató lépésről lépésre, példákkal mutatja be az **Aretino** kottaformátum használatát.
A formátum/tördelési algoritmus még változhat, a visszajelzéseket köszönettel fogadjuk!

Ezen az oldalon kipróbálható, gyakorolható a formátum használata. Az összes beállítási lehetőséggel ellátott szerkesztő a [Kottaszerkesztő](/score/preview) oldalunkon található.

Az Aretino kottaformátum szabadon felhasználható, nyilvános anyagokban kérjük, hivatkozzanak a formátum nemzetközi honlapjára: [aretino-chant.github.io](https://aretino-chant.github.io)

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
10. [Módosító utótagok (mora, episema, ictus, plica)](#10-módosító-utótagok)
11. [Ligatúrák — neumák](#11-ligatúrák--neumák)
12. [Neuma-tagoló rés (`/`)](#12-neuma-tagoló-rés)
13. [Vonalak és tagolójelek](#13-vonalak-és-tagolójelek)
14. [Sorvégi kiegyenlítés, manuális elosztás és sortörés](#14-sorvégi-kiegyenlítés-manuális-elosztás-és-sortörés)
15. [Módosítójelek (b, kereszt, feloldó)](#15-módosítójelek)
16. [Szöveg, versszakok és zsoltárversek](#16-szöveg-versszakok-és-zsoltárversek)
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
| **Külön sor a szövegnek** | A `w:` (szótagolt) és `W:` (folyó versszak) sorok önállóan értelmezhetők. |

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
(g2) g ga ag a'/g af ae aba gCB CCbag
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
- **mora, episema, ictus és plica** utótagként, **quilisma és
  liquescentia** kottafej-típusként (8–9. pont);
- **a vonalak és kulcsok kérdésének függetlensége** (10. pont) —
  kulcs cserélhető, és nem érinti az `a–n` jelölést.

Az Aretino tehát **nem szakít** a hagyománnyal — éppen ellenkezőleg:
a **lényeget** automatizálja, így a felhasználónak csak a zenei
tartalomra kell figyelnie.

---

## 5. Első kotta

```aretino
(g2) c e g a g.
```

Ez egy violinkulcsot (G a 2. vonalon), majd öt punctumot rajzol —
az utolsóra morával (nyújtóponttal).

Egy minimum-példa szöveggel és fejléccel:

```aretino
%title: Salve, Regina
%%
(g2) c e g a g.
w:   Sal-ve, Re-gí-na,
```

A három fő építőelem:

1. **Fejléc** — elhagyható, `%kulcs: érték` sorok, `%%` zárja.
2. **Dallam-sor** — hangok, ligatúrák, vonalak. Az első zárójeles elem
   a kulcs (`(g2)`, `(f4)`, `(c3)`).
3. **Szöveg-sor** — `w:` előtaggal, közvetlenül a dallam alatt.

---

## 6. Fejléc

A fejléc-sorok pontosvesszővel kezdődnek (`%kulcs: érték`), és a `%%` lezárja:

| Kulcs | Leírás |
|---|---|---|
| `title` | Cím, középre igazítva, félkövér. |
| `subtitle` | Alcím, kisebb betűmérettel, félkövér. |
| `caption` | Felirat, jobbra igazítva, dőlt. |
| `indent` | Behúzás az első sor elején. Ha értéket adsz (pl. `I.d`), megjelenik ezen a részen. |
| `rubric` | Balra igazított, kiskapitális felirat |

Formázási információkat is tartalmazhatnak ezek a sorok (ld. később).

```aretino
%title: Vigília
%caption: Zsolt 50,17
%indent: VII.
%rubric: Kezdés
%%
(g2) a a a g a C b g a. ||
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
(g2) d f g a  (c3) e g a (f4) b a g
```

Sortörés után a megjelenítő automatikusan kirajzolja az
aktuális kulcsot.

---

## 8. Hangmagasság

A hangokat **a–g** kisbetűk, illetve az **A-G** nagybetűk jelölik. A kisbetűk az egyvonalas oktávot jelentik (violinkulcsban olvasva), a nagybetűk az ezen kívül eső oktávot. A betű mindig ugyanazt a sort/vonalközt jelenti, **függetlenül a kulcstól**:

```aretino
A B c d e f g a b C D E F G
w: a b c d e f g h i j k l m n
```

Tehát G-kulcsban `c` az 1. vonalon C-hang, `g` a 3. vonalon B-hang stb.
F-kulcsban ugyanaz a `c` az 1. vonalon E-hang lesz (mert a kulcs változik,
de a vonal-pozíció nem).

---

## 9. Kottafej-típusok

A kottafej alapformáját egy **utótag-karakter** módosítja a betű után:

| Forrás | Név | Rajz |
|---|---|---|
| `d` | **punctum** | töltött kerek kottafej, szár nélkül |
| `d'` | **virga** | punctum bal oldalán lefelé mutató szárral (felsővessző) |
| `dw` | **quilisma** | csíkozott, cikkcakkos kontúrú kottafej |
| `dt` | **tenor-hang** | üres kottafej, két oldalán függőleges vonalkák |
| `ds` | **kiskotta** | liquescens átírására használt kis méretű kottafej |

### Példák

```aretino
(g2) d d' dw dt ds
```

Bal → jobb: punctum, virga, quilisma, tenor-hang, kiskotta — mind ugyanazon a magasságon (D).

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
| `~` | **plica** | kis „farok” a kottafej jobb felső sarkán |

```aretino
(g2) d d. d_ d- d~ d_e_d_
```

Itt a `d~` plicát jelöl. Liquescens átírásához a kiskotta (`ds`) való.

---

## 11. Ligatúrák — neumák

Az **egymás után, szóköz nélkül** írt hangbetűk egy ligatúrát (neumát)
alkotnak. Ez az Aretino egyik leglényegesebb mechanizmusa.

| Forrás | Név | Jelentés |
|---|---|---|
| `df` | **podatus** | felfelé lépő kettős, alsóról a felsőre vivő ívvel |
| `fd` | **clivis** | lefelé lépő kettős, ívelt kalligrafikus vonallal |
| `dfd` | **torculus** | hármas: fel-le |
| `fdf` | (völgy hármas) | hármas: le-fel |
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

A `/` körül lehet szóköz: a parser ezt elhagyja, és ugyanúgy neuma-tagoló
résként értelmezi.

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
(g2) (K:f#) h h h f h i j ih h_ , h h h f g hg e d. d. ; f f f g e g f_ , e d e e e g f d. d. || (Z) ht i ht g ht ||
w: Men-je-tek, és vi-gyé-tek hí-rül: (*) föl-tá-madt az Úr, al-le-lu-ja! Néz-zé-tek ü-res sír-ját, a-hol nyu-go-dott, al-le-lu-ja!
```
<small>Forrás: A húsvéti Szent Három Nap liturgiája, © Bencés Kiadó</small>

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
| `(b)` vagy `(ib)` | aktuális b | egyszeri b a megadott hangmagasságon |
| `(n)` vagy `(in)` | feloldó | a megelőző alteráció feloldása |
| `(#)` vagy `(i#)` | kereszt | félhanggal emelt |

A jel elé írhatod a céltartományt (`(fb)`, `(in)`, `(m#)`). Ha elhagyod a
hangbetűt, az alapértelmezett pozíció `i`, tehát `(b)`, `(n)`, `(#)` az `i`
magasságon jelenik meg.

### Példa

```aretino
(g2) (ib) (sp) (in) (sp) (i#) (sp) : h (ib) hih fgh. g(ib)hih
```

A módosítójeleket a következő neumával egyben tartjuk. (Neumán belül is használható módosítójel.)

### Előjegyzés — `(K:...)`

Az előjegyzést a kulcs után helyezzük el. A megjelenítő minden új sor
elején automatikusan kiteszi a kulcsot követően, akkor is, ha a kulcs
csak a darab elején van leírva.

| Forrás | Jelentés |
|---|---|
| `(K:b)` vagy `(K:ib)` vagy `(K:Bb)` | b-előjegyzés az `i`/`B` magasságon |
| `(K:eb)` | b az E hangon |
| `(K:F# C#)` | több módosítójel — szóközzel elválasztva |
| `(K:)` | előjegyzés törlése |

```aretino
%title: Példa előjegyzéssel
%%
(g2) (K:m# j#) d e f g h i j k (||)
```

Egy újabb `(K:…)` token megváltoztatja az előjegyzést onnantól (helyben is megjelenik, és a következő sorok elején is az új jel szerepel). `(K:)` törli az
előjegyzést.

---

## 16. Szöveg, versszakok és zsoltárversek

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
(g2) dghfe ed , g hg ghj h hghgfg(b)ihig fhgfgfe e :
w: Hús-vét ün-ne-pe e-lőtt tör-tént:
```

A tördelő algoritmus megpróbálja a gazdaságosan és esztétikusan elhelyezni a szöveget. Ez azt jelenti, hogy ha megoldható, a szótagokat megpróbálja összevonni. Az egyes szótagokat punctum alatt középre rendezi, neumák alatt, tenorhang alatt alatt pedig balra rendezi.

### Több versszak

A dallamsor alá több `w:` sor is írható — minden új sor egy versszak:

```aretino
(g2) d c d f g f e d. ,
w: Vic-ti-mae pas-cha-li lau-des
w: A hús-vé-ti szent Bá-rány-nak
```

### Soronként 1-2 ütem beírása

Hosszabb kottáknál vagy antifóna/zsoltárvers kottáknál célszerű lehet használni azt a funkciót, hogy az `n:` bevezetéssel tudjuk folytatni az előző zenei sort. Így egyértelműen látszik, hogy melyik szöveg melyik zenei részhez tartozik.

```aretino
(g2) (K:b) 
cf f f fghigf fj jklmkj j , 
w: {V}a-dis,~{*} pro-pi-ti-á-tor, 
n: fj j jk k kj j hijih gh igf ; 
w: ad im-mo-lán-dum pro ó-mni-bus. 
```

### Zsoltárvers-sorok (`W:`)

A `W:` sor nem szótagolt, hanem folyó szövegként tördelődik. Tipikus
használata zsoltárverseknél és responzóriumoknál.

```aretino
%indent: VI. f.
(g2) f g h f. gh g f d_c_ , f gh gf f. || 
w: Al-le-lu-ja, al-le-lu-ja, al-le-lu-ja. 

(Z) f g | ht = g. , ht = g h- fs f. | ht = f g gs h g fs f. ||
w: ~ ~ ~ + ~ ~ ~ ~ ~ (*)
W: <Jézus> mond[ja]: + szeressétek egymást, ez az [én] parancsom *
amint én szerette[lek] benneteket!
W: Arról ismerje meg mindenki, hogy az én tanítványa[im] vagytok: *
hogy sze[re]titek egymást!
```

Ha egy `W:` sort egy prefix nélküli sor követ, az ugyanannak a versszaknak
új sora lesz.

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

Hogy a szöveg tördelését ne zavarják a verszakok számai és egyéb rövid jelölések,
a `~~` jellel kell összekötnünk. Első versszak esetén manuális térköz kellhet.

```aretino
(g2) = g g g h g gj j ' jt
w: 1.~~Ki-rá-lyok-nak Ki-rá-lya (†) és~Atyja...
```

### Speciális karakterek
Néhány karaktert speciálisan jelenítünk meg.

```aretino
c d e f
w: \R \V + ++
``` 

### Szövegformázás

A szöveges sorokba egyszerű formázó jelöléseket is tehetünk:

- `<szöveg>` — *dőlt* (italic)
- `{szöveg}` — **félkövér** (bold)
- `[szöveg]` — aláhúzott
- `\red{szöveg}` — piros
- `\color:green{szöveg}` — egyedi szín

A formázás tetszőleges szótagokra alkalmazható, és a szótaghatáron át is érvényes marad, amíg a záró tag meg nem jelenik.

```aretino
(g2) g h i g. hi h g e_d_ , g hi Ag g. ||
w: {\R}~~Al-le-lu-ja, al-le-lu-ja>, (\red{{*}}) al-[le-lu]-ja.
```

### Feliratok

**Felirat**: rövid szöveg, amely egy hang vagy neuma fölött jelenik meg. A feliratot idézőjelek közé téve, közvetlenül a hang vagy ligatúra után (szóköz nélkül) kell írni:

```aretino
(g2) hg"Felirat" d f"\red{{!}}" gh"2x"
```

A felirat bármilyen (akár formázott) szöveg lehet; a megfelelő hang vagy ligatúra fölött jelenik meg.

---

## 18. Zárójelezett hangok

Egy vagy több hangot (vagy egész neumát) `[` … `]` közé téve **tipográfiai zárójelek** jelennek meg körülöttük.

| Forrás | Jelentés |
|---|---|
| `[h]` | egyetlen hang zárójelben |
| `[hg]` | ligatúra (neuma) zárójelben |
| `[h i j]` | több elem zárójelben (szóközök megengedettek) |

```aretino
(g2) d [h] g [hg] d [h i j] g
```

## 19. Hosszabb példák

### Egyszerű Kyrie

```aretino
%title: Uram, irgalmazz (XVI.)
%%
(g2) (K:ib) h h h g h fg h ||
w: U-ram, ir-gal-mazz né-künk! (<3x>)

h h h g h fg h ||
w: Krisz-tus, ke-gyel-mezz né-künk! (<3x>)

h h h g h fg h ||
w: U-ram, ir-gal-mazz né-künk! (<2x>)

h g i g f gh h ||
w: U-ram, ir-gal-mazz né-künk!
```

**Mit lehet itt megfigyelni?**

- A kulcsot csak az első sorban kell megadni — a megjelenítő az új rendszerek elejére automatikusan kiteszi.
- Üres sorok **új szakaszt** indítanak.
- Az ütemvonal alá rendezünk szöveget.

### Antifóna zsoltárdallammal

```aretino
%title: Hints meg engem
%%
(g2) (K:m#) d e g f gh h , i j i h i h ge d | d e gfgh h , i g ge ggfg h g e d d ||
w: Hints meg en-gem U-ram, i-zsóp-pal és meg-tisz-tu-lok, moss meg en-gem, és fe-hé-rebb le-szek a hó-nál.

f g ht gs ht g h i- gs g | ht i h gs- gf e ||
w: ~ ~ ~ † (*)
```

<small>Forrás: Népénektár, © Gödöllői Premontrei Apátság</small>

### Kyrie VIII

```aretino
(g2) (K:b) 
   f  ABC C./DCBC./FDCB/CDC. ,  CAgfBA g  g f. ||
w: Ky-ri-e                 (*)  e-le-i-son. (<bis>)
n: A    Agfef.,fABC./DCBC., CAgfBA g g f. ||
w: Chris-te ~               e-le-i-son. (<bis>)
n: F  E  FEDEFC. , FCD.ABC. , CAgfBA g g f. ||
w: Ky-ri-e      (*)         ~ e-le-i-son.
n: F  E  FEDEFC. , FE/FEDEFC. ,  FCD.ABC. , CAgfBA g g f. ||
w: Ky-ri-e      (*)                  ~ ~ (*)e-le-i-son.
```

Itt látható, hogy ha akarjuk, szóközökkel jelölhetjük, hogy melyik szövegrész hova esik.

---

## 20. A szerkesztő használata

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

## 21. Visszajelzés, hibajelentés

Az Aretino formátumnak és a megjelenítő szoftvernek saját honlapja van: [aretino-chant.github.io](https://aretino-chant.github.io), ahol hibákat is lehet jelenteni, illetve fejlesztési javaslatokat is szívesen fogadunk.

Ha kérdésed/javaslatod van, írhatsz nekünk az [info@cantores.hu](mailto:info@cantores.hu) címre, vagy a [Facebook](https://www.facebook.com/people/Cantoreshu/61588419360930/) oldalunkon is.
