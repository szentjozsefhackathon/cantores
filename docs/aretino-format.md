# Aretino kottaformátum specifikáció (v0.1 piszkozat)

Az **Aretino** szöveges forrásnyelv a magyar katolikus gregorián
gyakorlatban használt notáció lejegyzésére. Dobszay László és Szendrey
Janka konvencióját követi: ötvonalas kottarendszer, violinkulcs,
hagyományos kerek kottafejek (nem kvadrát), de gregorián ritmika és
jelölésrendszer (mora, episzéma, likveszcencia, kvilizma, ligatúrák).

A név Guido d'Arezzo (Aretinus) szülővárosára utal — a régi *Guido HU/EN*
TTF betűkészlet szellemi utódja, de attól független, szemantikus formátum.

## Tervezési elvek

1. **Dallam és szöveg külön sorban.** Az ABC mintájára: a hangok sora
   önállóan értelmezhető, a szöveg `w:` prefixű sorral követi. **A v0.1
   verzióban nincs automatikus szótag–hang összerendelés** — a felhasználó
   a szöveget vizuálisan igazítja a hangok alá (szóközökkel).
2. **Karakter = jeltípus, pozíció = hangmagasság.** Nem ASCII-koordinátás
   trükk: a `d` betű mindig punctum, a magasságát a betű kódja adja meg.
3. **Szemantikus jelölők:** az episzéma, mora, likveszcencia, kvilizma stb.
   külön utótag-karakterek, nem külön glyph-fájlokra mutató mágikus kódok.
4. **Ötvonalas kottarendszer, violinkulcs alapértelmezésben.** Mert a magyar
   gregorián gyakorlat hagyományos kottafejekkel, ötvonalas rendszeren ír.
5. **Részeinkre szétszedhető.** A renderer parser + layout + glyph rétegekre
   tagolódik (lásd implementációs terv).

## Forrásszerkezet

```
;cím: Kyrie
;mód: I
%%
(g2) d fg h_ * g. fgfe d' d d.  (||)
w:   Ky-ri-e      e-lé- i-son.
```

- **Fejléc:** `;kulcs: érték` sorok, lezárása `%%`. Opcionális.
- **Dallam-sor:** hangok és vonal-jelek szóközzel elválasztva. A sor első
  zárójeles eleme a kulcs (`(g2)`).
- **Szöveg-sor:** `w:` prefix, ABC-stílusban. Több `w:` sor is követheti
  ugyanazt a dallamot (versszakok).
- A szöveg-sort a renderer önálló sorként szedi a dallam alá; **a v0.1-ben
  nincs szótag–hang automatikus alignálás**, a felhasználó szóközökkel
  igazít.

## Hangmagasságok

GABC-szerű relatív kódolás. A betűk a kulcs által meghatározott
vonalrendszeren helyezkednek el:

```
a b c d e f g h i j k l m
                G-kulcs (g2): a = vonalrendszer alatti vonalköz, g = G a 2. vonalon
```

A pontos leképezés NEM függ a kulcstól, hasonlóan a GABC-hez, tehát a betűjel azt jelzi, hogy melyik vonalon vagy vonalközben van a hang.

Aposztrof (`'`) emelt oktáv (ritka): `d'` = magas D.

## Hang-glyph típusok

| Forrás | Jel | Megjegyzés |
|---|---|---|
| `d` | punctum | töltött kerek kottafej, szár nélkül |
| `D` | virga | punctum bal oldalán lefelé mutató szár |
| `dw` | quilisma | csíkozott (cikkcakkos kontúrú) kottafej |
| `dt` | tenor-hang | üres kottafej, két oldalán függőleges vonalka |
| `ds` | kis kottafej | kicsinyített (70%) kottafej, opcionális hangok jelölésére |
| `d.` | mora (nyújtópont) | a kottafej után, jobbra |
| `d_` | episzéma | rövid vízszintes vonal a kottafej **fölött, a hang magasságához igazítva** |
| `d-` | ictus | kis függőleges vonal a kottafej **fölött, a vonalközben** |
| `d~` | likveszcens kicsi | kis „farok" kiegészítés |

Több utótag sorrendben kombinálható: `D_.` = virga episzémával és morával.
Oriscus és nagy likveszencs nincs

## Ligatúrák

Egymás után, **szóköz nélkül** írt hang-betűk egy ligatúrát alkotnak.
Szóközzel elválasztott hangok különálló punctum-ok.

| Forrás | Név | Rajz |
|---|---|---|
| `df` | podatus (felfelé) | két fej, a felsőhöz felfelé vivő ligatúravonal |
| `fd` | clivis (lefelé) | két fej, lefelé hajló kalligrafikus íves vonal |
| `dfd` | torculus | fel-le hármas |

Az Aretino (Dobszay szellemében) nem tartalmaz porrectus, climacus, és flexa-ligatúra elemeket, ezeket a többi elemből rakjuk össze.


## Kulcsok
F, G és C kulcs van támogatva. A C kulcs jelölése egy kis szögletes C jel (nem a hagyományos altkulcs)


| Forrás | Jelentés |
|---|---|
| `(g2)` | G-kulcs (violinkulcs) a 2. vonalon — alapértelmezett |
| `(f4)` | F-kulcs a 4. vonalon |
| `(c3)` | C-kulcs a 3. vonalon |

## Módosítójelek

| Forrás | Jelentés |
|---|---|
| `(bx)` | aktuális b (egyszeri módosítójel) |
| `(by)` | feloldó |
| `(b#)` | kereszt |

A módosítójelek a saját hangmagasságukon jelennek meg, a `b` után a vonal
betűje adja: `(ebx)` = b a 3. vonalon (E magasságában).

## Előjegyzés

Az előjegyzést a kulcs után helyezzük el, és minden új sor elején
automatikusan megismétlődik (a kulccsal együtt).

| Forrás | Jelentés |
|---|---|
| `(K:bx)` | b-előjegyzés (a `b` vonalon álló b) |
| `(K:ebx)` | b a 3. vonalon (E magasságában) |
| `(K:bx ebx)` | több módosítójel — szóközzel elválasztva |
| `(K:)` | előjegyzés törlése |

Az előjegyzés ugyanúgy módosulhat a darab közben is: ahol új `(K:…)`
token áll, ott jelenik meg in-line, és onnantól a következő sorok
elején is az új előjegyzés szerepel.

## Vonalak, elválasztók

| Forrás | Jelentés |
|---|---|
| `,` | rövid vonal (negyedvonal) |
| `;` | fél-vonal |
| `\|` | egész vonal |
| `\|\|` | kettős vonal (tétel vége) |
| `:\|` | ismétlőjel (vége) |
| `\|:` | ismétlőjel (eleje) |
| `:\|:` | ismétlőjel (kétirányú) |
| `\|\|\|` | záróvonal (klasszikus záró) |
| `*` | sorvég-kiegyenlítő — szélesen szétfutó üres vonalszakasz |
| `=` | szóköz (spacer) — megegyezik `(sp)`-vel; `==` = `(sp2)`, `===` = `(sp3)` stb. |
| `/` | neuma-tagoló rés — egy kottafejnyi üres hely a kis csoportok között, a melizmán belül |
| `(z)` | sortörés-javaslat (custos automatikusan generálódik) |

## Custos

Custos egyelőre nincs.

## Példa

```
;cím: Kyrie I
;mód: I
%%
(g2) d fg h_ * g. fgvFE d' d d.  (||)
w:   Ky-ri-e    e-lé- i-son.

fg h_ * g. fgvFE d' d d.  (||)
w: Chris-te  e-lé- i-son.

d fgvFE d * c. d fg h_ d.  (||)
w: Ky-ri- e  e-lé-i- son.
```

## Még nyitott kérdések (v0.2)

- Szótag–hang automatikus alignálás szükséges-e későbbi verzióban.
- Régi *Guido HU/EN* TTF-forrás konverziója Aretino-ra: külön specifikáció
  fogja leírni (lásd plans/).
