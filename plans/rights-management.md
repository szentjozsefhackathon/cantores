# Cantores – jogkezelési rétegek

## 1. Alapgondolat

A Cantores elsődlegesen **liturgikus zenei műhely**.

Nem jogkezelő rendszerből indulunk ki, amelyhez később hozzáadunk néhány szerkesztési funkciót, hanem fordítva:

**először létezik a zenész saját munkatere, és erre épülnek rá fokozatosan a jogkezelési funkciók.**

A különböző layerek azt fejezik ki, hogy a Cantores egy adott tartalommal kapcsolatban mekkora jogi szerepet vállal.

Minél magasabb a layer:

* annál többet tudunk arról, hogy ki milyen joggal rendelkezik;
* annál több állítást tesz a Cantores a tartalom jogállásáról;
* annál szélesebb körben tehető hozzáférhetővé mások számára;
* és annál erősebb bizonyítékra, jogosulti nyilatkozatra vagy engedélyre van szükség.

A rendszer alapelve:

> **A magasabb jogkezelési layer nem ahhoz szükséges, hogy valaki zenészként dolgozhasson, hanem ahhoz, hogy az elkészült munkát egyre szélesebb körben, dokumentált jogalapon újra lehessen használni.**

---

# Alapvető jogkezelési elvek

## Nem „egy mű = egy licenc”

A Cantores nem abból indul ki, hogy egy dalnak van egyetlen `license` mezője.

Egy zenei anyag több, egymásra épülő alkotást és teljesítményt tartalmazhat:

* zenemű;
* dalszöveg;
* fordítás;
* harmonizáció;
* hangszerelés;
* átdolgozás;
* kottakiadás;
* esetleges egyéni szerkesztői teljesítmény;
* hangfelvétel.

Ezeknek különböző jogosultjai és különböző felhasználási feltételei lehetnek.

Ugyanígy nem egyetlen általános „felhasználási jog” létezik.

Más kérdés például:

* letölthető-e;
* nyomtatható-e;
* továbbadható-e;
* módosítható-e;
* vetíthető-e;
* nyilvánosan hozzáférhetővé tehető-e;
* kereskedelmi célra használható-e.

A Cantores ezért hosszú távon nem egyszerű licenctáblát, hanem **rights graphot** kezel.

---

## A bizonytalanság nem jelent szabadságot

Ha egy tartalom jogállása nincs dokumentálva, abból nem következik, hogy szabadon terjeszthető.

Az alapértelmezés:

> **nincs dokumentált engedély mások számára történő közzétételre.**

Ez azonban nem akadályozza a 0. layer szerinti saját munkát.

---

## A Cantores nem akar szerzői jogi bíróság lenni

A rendszer lehetőleg nem maga próbálja eldönteni például azt, hogy:

> „Ez a lekottázás elég eredeti-e ahhoz, hogy szerzői jog védje?”

Ehelyett nyilatkozatokat és jogcímeket rögzít.

Például:

> „A kottakészítő saját alkotói teljesítményt állít.”

vagy:

> „A kottakészítő hű átírásként kezeli, és nem állít új kizárólagos jogot.”

A Cantores dokumentálja a nyilatkozatot, de nem állítja, hogy jogvita esetén feltétlenül ez lenne a végleges jogi minősítés.

---

# 0. layer – Saját zenei munkatér

## Szerep

Ebben a layerben a Cantores tisztán **technikai és alkotói eszköz**.

Egyszerre lehet:

* szövegszerkesztő;
* kottaszerkesztő;
* felhőtárhely;
* saját zenei adatbázis;
* énekrendkészítő;
* füzetkészítő;
* nyomtatási rendszer;
* vetítő.

A megfelelő analógia:

**Word + OneDrive + MuseScore + prezentációs rendszer + saját zenei adatbázis.**

## Mit csinál a felhasználó?

A saját munkaterébe:

* feltölthet;
* begépelhet;
* bemásolhat;
* importálhat;
* szerkeszthet;
* transzponálhat;
* összeállíthat;
* tárolhat;

olyan anyagokat, amelyekkel a saját zenei munkáját végzi.

Ide kerülhetnek például:

* saját munkapéldányok;
* saját kották;
* saját transzpozíciók;
* próbaváltozatok;
* saját akkordozások;
* liturgikus füzetek;
* zenekari segédanyagok;
* saját használatú átiratok;
* külső forrásból behozott szövegek és kották.

## Mit nem állít a Cantores?

A Cantores ezen a szinten nem állítja, hogy:

* a felhasználó anyaga közkincs;
* szabad licenc alatt áll;
* mások számára terjeszthető;
* átdolgozható;
* nyilvánosan publikálható.

És nem ad ezekre saját nevében engedélyt.

## Alapelv

> **A 0. layerben a Cantores műhely, nem kiadó és nem licencadó.**

A felhasználó saját munkájának jogalapját maga ismeri és maga viseli érte a felelősséget.

A platform ettől még:

* kezeli a jogsértési bejelentéseket;
* eltávolíthat jogsértő anyagokat;
* technikailag korlátozhat visszaéléseket.

De nem végez előzetes repertoár-jogosítást.

---

# 1. layer – Jogállás és alkotói rétegek nyilvántartása

Ez az első valódi rights-management layer.

A tartalom továbbra is lehet privát, de a Cantores már tudja róla:

* mi az alapmű;
* ki a szerző;
* mi új alkotás benne;
* ki állít jogot;
* milyen jogállítás vagy forrás tartozik hozzá.

## 1/A – Saját művek

A felhasználó saját alkotását összekapcsolhatja saját profiljával.

Például:

**Zenemű:** © Kovács Anna
**Szöveg:** © Nagy Péter
**Hangszerelés:** © Kovács Anna
**Kottakiadás:** Kovács Anna

Itt még nem feltétlenül engedélyezünk semmit mások számára.

Csak tudjuk, hogy ki mit állít saját alkotásának.

---

## 1/B – Artisjus és más jogkezelők

Az Artisjus-jogállás nem egyszerű boolean mező.

Nem:

`artisjus_member = true`

hanem:

> **egy adott jogot egy adott mű esetében ki kezel?**

Például:

**Nyilvános előadás:** Artisjus
**Egyes online jogok:** Artisjus
**Átdolgozás:** jogosult
**Kottakiadási engedély:** jogosult / kiadó

A Cantores nem próbálja átvenni az Artisjus által kezelt jogokat.

A rights engine egyszerűen rögzíti, hogy:

> `managed_by = ARTISJUS`

ahol ez releváns.

---

## 1/C – Public Domain alapművek

A PD alapművek már ezen a korai layeren megjelennek.

Például:

**Alapdallam:** Public Domain
**eredeti latin szöveg:** Public Domain

Erre készülhet mai kotta.

Fontos: a Cantores nem feltételezi automatikusan, hogy a lekottázás önálló szerzői mű.

A kottakészítő megadhatja például:

### Saját alkotói teljesítmény

> A kottakiadásban saját, egyéni alkotói/szerkesztői teljesítményt állítok.

vagy:

### Hű átírás

> A kotta az alapmű hű átírása; új kizárólagos szerzői jogot nem állítok.

Ez utóbbi Cantores-szempontból lehet:

`no_new_rights_asserted`

Nem azt jelenti, hogy a Cantores jogerősen megállapította a szerzői jog hiányát.

Csak azt:

> **a kottakészítő nem kíván új jogi korlátozást állítani a transzkripció alapján.**

---

# 2. layer – Saját vagy szabad alapú tartalom közzététele

Itt történik meg az első valódi publikációs ugrás.

A jogosult már nemcsak azt mondja meg, hogy ő alkotta az anyagot, hanem azt is:

> **mire engedélyezi más Cantores-felhasználóknak.**

## Milyen anyag kerülhet ide?

Tipikusan:

### Saját mű

A felhasználó saját szerzeménye.

### Saját szöveg

A felhasználó saját szövege.

### Saját hangszerelés

Ha annak elkészítéséhez szükséges alapjogok rendben vannak.

### PD alapműből készített saját feldolgozás

Például:

**Alapdallam:** Public Domain
**harmonizáció:** © Nagy Anna 2026

### Hű PD-transzkripció

Ha a kottakészítő nem állít rá új jogot.

---

# Mit lehet engedélyezni?

Nem feltétlenül kell rögtön CC-licenc.

A rendszer kezelhet konkrét felhasználási grantokat.

Például:

> ✓ online megtekinthető
> ✓ letölthető
> ✓ nyomtatható
> ✓ zenekari példány készíthető
> ✓ módosítható
> ✓ továbbterjeszthető
> ✗ kereskedelmi továbbértékesítés

Ezekből később akár szabványos licencek is leképezhetők.

---

## Creative Commons

Ha a jogosult valóban rendelkezik az érintett jogokkal, választhat például:

* CC0;
* CC BY;
* CC BY-SA;
* megfelelő esetben NC-változatok.

A rendszernek azonban soha nem szabad egyszerűen ezt mondania:

> „A dal CC BY.”

Pontosabban:

> **„A jogosult ezt az alkotói réteget ezen feltételekkel licenceli.”**

Például:

**Alapmű:** Public Domain
**harmonizáció:** CC BY-SA 4.0
**kottakiadás:** CC BY-SA 4.0

vagy:

**Alapmű:** © szerző
**nyilvános előadás:** Artisjus
**Cantoreson közzétett kottakiadás:** szerző engedélye alapján letölthető

---

# 3. layer – Jogvédett idegen alapművek

Itt kezdődik az első komoly jogi ugrás.

A Cantores-felhasználó már nem csak:

* saját művet;
* vagy PD alapműből készített anyagot

tesz közzé.

Hanem egy **másik jogosult még védett alapművére** épít.

Például:

* kortárs dal új kottája;
* fordítás;
* új harmonizáció;
* új hangszerelés;
* kórusfeldolgozás;
* liturgikus adaptáció;
* új nyomtatott/digitális kottakiadás.

## Megjelenik a permission chain

Például:

**Alapmű:** © A szerző
↓
**magyar fordítás:** © B fordító
↓
**hangszerelés:** © C kántor
↓
**kottakiadás:** D

A saját új hozzájárulás teljesen valódi szerzői mű lehet.

De attól még nem lehet szabadon publikálni, ha az alapmű használatához hiányzik az engedély.

A 3. layer tehát már nem pusztán alkotói nyilatkozatról szól, hanem:

> **dokumentált engedélyláncról.**

---

# 3. layerben tárolandó bizonyítékok

Például:

* jogosulti engedély;
* kiadói engedély;
* szerződés;
* e-mailes hozzájárulás;
* licencfeltétel;
* engedélyezett felhasználási módok;
* időbeli hatály;
* terület;
* visszavonhatóság;
* továbblicencelési jog.

Egy tartalom csak akkor léphet a 2. layerhez hasonló publikus állapotba, ha az engedélylánc ezt valóban lehetővé teszi.

---

# 4. layer – Jogosulti és kiadói fiókok

Ebben a layerben már nem egy Cantores-felhasználó próbálja dokumentálni valaki más engedélyét.

**Maga a jogosult kapcsolódik a platformhoz.**

Például:

* zeneszerző;
* szövegíró;
* örökös;
* zeneműkiadó;
* rend;
* egyházi közösség;
* repertoártulajdonos.

Igazolt jogosulti profilt kaphat.

## Mit tehet?

Megadhatja:

> Ezek az én műveim.

És:

> Ezeket a jogokat én kezelem.

Majd például:

> ✓ Cantoreson megjelenhet
> ✓ vetíthető
> ✓ PDF letölthető
> ✓ 30 próbapéldány nyomtatható
> ✗ átdolgozás nem engedélyezett
> ✗ nyilvános újraközlés nem engedélyezett

Ezzel a Cantores már valódi, strukturált licencplatformmá válik.

---

# 5. layer – Fizetős és előfizetéses repertoár

A következő layerben az engedély már pénzügyi feltételt is tartalmazhat.

Például:

### Egyedi kotta

> 1 500 Ft
> letöltés + nyomtatás

### Szerzői katalógus

> Évi 5 000 Ft
> az adott szerző valamennyi partitúrája

### Prémium gyűjtemény

> Cantores-előfizetéssel hozzáférhető

### Plébániai jogosultság

> egy intézmény felhasználói közösen használhatják.

Itt továbbra is lehet az a modell, hogy a Cantores csak technikai piactér/licencplatform.

Nem feltétlenül maga „birtokolja” a repertoárjogokat.

---

# 6. layer – Intézményes repertoárlicencelés

Ez már a távoli, OneLicense-jellegű irány.

Itt a Cantores esetleg több jogosult repertoárjára egységes licencet kínálhat.

Például:

> Cantores liturgikus zenei éves licenc

amely több szerző vagy kiadó meghatározott felhasználási jogait fogja össze.

Ehhez már tartozhat:

* jogosulti képviselet;
* jogdíjbeszedés;
* felosztás;
* felhasználási jelentések;
* repertoárkezelés;
* intézményi licencelés.

Ez már lényegében külön szervezeti projekt.

Nem szükséges ahhoz, hogy a Cantores első öt layere rendkívül értékes legyen.

---

# A layerek közötti mozgás

A tartalom nem egyszer kerül be egy végleges kategóriába.

Fokozatosan haladhat felfelé.

Példa:

### 0.

Egy kántor privátban elkészít egy kottát.

### 1.

Megjelöli:

> alapmű: PD
> hangszerelés: saját

### 2.

A hangszerelést CC BY-SA alatt közzéteszi.

### 4.

Később igazolt alkotói profilt kap, és a teljes katalógusát kezeli.

### 5.

Bizonyos új műveinek partitúráit már előfizetéshez köti.

Ugyanaz az infrastruktúra szolgálja ki az egész fejlődést.

---

# Fontos különbség: private status ≠ rights status

A két tengelyt külön kell kezelni.

Egy kotta lehet:

**privát**, miközben pontosan ismert a jogállása.

Vagy:

**publikus**, mert dokumentált engedélye van.

Ezért például nem jó:

`is_private = false → licensed`

A láthatóság és a jogállás külön fogalom.

---

# Javasolt alapjogállások

A rendszernek érdemes kezelnie legalább:

### Unknown / unspecified

A Cantores nem ismeri a jogállást.

### User supplied

Felhasználó saját munkaterébe behozott anyag.

### Public Domain

Dokumentáltan szabad alapmű.

### No new rights asserted

PD mű hű átírása, amelyre a készítő nem állít új kizárólagos jogot.

### Original contribution claimed

A felhasználó saját alkotói teljesítményt állít.

### Open licensed

Dokumentált CC vagy más nyílt licenc.

### Direct permission

Jogosult konkrét engedélye.

### Managed by collecting society

Valamely adott jogot például az Artisjus kezel.

### Rights chain incomplete

Van saját alkotói réteg, de az alapműre vonatkozó publikációs engedély hiányzik.

---

# Javasolt jogi objektumok

## Work

Az alapmű.

## Text

Szöveg.

## Translation

Fordítás.

## Arrangement

Harmonizáció, hangszerelés, átdolgozás.

## Score edition

Konkrét kottaváltozat.

## Rights holder

Szerző vagy más jogosult.

## Rights manager

Például:

* jogosult maga;
* Artisjus;
* zeneműkiadó;
* más szervezet.

## Permission / licence grant

Konkrét felhasználási engedély.

## Evidence

Az engedély bizonyítéka.

---

# Egy permission grant példája

```text
subject:
    arrangement #1234

rightsholder:
    Kovács Anna

rights:
    download
    print
    reproduce
    adapt

audience:
    all Cantores users

commercial_use:
    false

territory:
    worldwide

valid_from:
    2026-09-15

valid_until:
    null

license:
    CC BY-NC-SA 4.0

evidence:
    rightsholder declaration
```

Ezzel szemben:

```text
subject:
    composition #1234

right:
    public_performance

managed_by:
    Artisjus
```

A kettő ugyanahhoz a zenei anyaghoz tartozhat anélkül, hogy egymásnak ellentmondana.

---

# UI-alapelv

A felhasználónak nem jogászi adatstruktúrát kell mutatni.

Ő ezt szeretné látni:

> **Felhasználhatóság**
>
> ✓ Letölthető
> ✓ Nyomtatható
> ✓ Zenekarnak másolható
> ✓ Módosítható
> ⚠ Forrásmegjelölés kötelező
> ℹ Az alapmű egyes jogait az Artisjus kezeli

Ha valami nem tiszta:

> **Csak saját munkatérben használható.
> Nyilvános közzétételhez nincs dokumentált engedély.**

---

# A Cantores fejlődési útja

## 0. layer

**Digitális zenei műhely**

Már önmagában teljes termék.

## 1. layer

**Rights metadata**

Tudjuk, mi micsoda és kihez tartozik.

## 2. layer

**Saját és szabad tartalom újrafelhasználása**

Megszületik a valóban jogtiszta közösségi kottatár.

## 3. layer

**Idegen jogvédett művek permission chainje**

Komoly rights management.

## 4. layer

**Jogosultak és kiadók közvetlenül belépnek**

A Cantores licencplatformmá válik.

## 5. layer

**Fizetős repertoár**

A szerzők és kiadók pénzt is kereshetnek a kottáikkal.

## 6. layer

**Intézményes repertoár-licencelés**

Csak akkor, ha egyszer valóban szükség lesz rá.

---

# A stratégiai lényeg

A Cantores nem abból indul ki, hogy:

> „Csak olyan zenével dolgozhatsz, amelyet mi előzetesen jogosítottunk.”

Hanem:

> **„Dolgozz a saját zenei műhelyedben. Ha pedig a munkádat másokkal is meg akarod osztani, segítünk pontosan dokumentálni, hogy milyen jog alapján és mire adható tovább.”**

Ez választja szét a két szerepet:

**munkakörnyezet**
és
**licencplatform**.

A Cantoresnak már a 0. layerben teljes értékűnek kell lennie.

A jogkezelés pedig fokozatosan arra szolgál, hogy a zenészek munkájából egyre nagyobb rész válhasson **biztonságosan, érthetően és újrahasználható módon közösségi erőforrássá**.
