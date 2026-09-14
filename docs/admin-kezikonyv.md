# Cantores.hu – Szerkesztői és adminisztrátori kézikönyv

Ez a dokumentum azokat a felületeket írja le, amelyek **szerkesztői** vagy
**adminisztrátori** jogosultsághoz kötöttek. A mindenki számára elérhető funkciókat a
nyilvános [Útmutató](../resources/markdown/guide.md) (`/guide`) tárgyalja – ott van szó az
énekrendekről, a kottatárról, a kölcsönzésről, a füzetekről és a vetítésről.

## Tartalomjegyzék

1. [Szerepkörök és jogosultságok](#1-szerepkörök-és-jogosultságok)
2. [Szerkesztői eszközök](#2-szerkesztői-eszközök)
   - 2.1 [Énekek egyesítése](#21-énekek-egyesítése)
   - 2.2 [Duplikátumok egyesítése](#22-duplikátumok-egyesítése)
   - 2.3 [Énekek ellenőrzése és verifikáció](#23-énekek-ellenőrzése-és-verifikáció)
   - 2.4 [Kotta-közzététel elbírálása](#24-kotta-közzététel-elbírálása)
   - 2.5 [Zenei címkék](#25-zenei-címkék)
   - 2.6 [Külső hivatkozások](#26-külső-hivatkozások)
3. [Adminisztrációs felület](#3-adminisztrációs-felület)
   - 3.1 [Felhasználók és szerepkörök](#31-felhasználók-és-szerepkörök)
   - 3.2 [Szerepkör-jogosultságok](#32-szerepkör-jogosultságok)
   - 3.3 [URL whitelist](#33-url-whitelist)
   - 3.4 [Énekrend sablonok és slotok](#34-énekrend-sablonok-és-slotok)
   - 3.5 [Tömeges importálás](#35-tömeges-importálás)
   - 3.6 [Direktórium](#36-direktórium)
   - 3.7 [Tartalmi statisztika](#37-tartalmi-statisztika)
   - 3.8 [Becenév-törzsadatok](#38-becenév-törzsadatok)

---

## 1. Szerepkörök és jogosultságok

A jogosultságokat a `RolePermissionSeeder` hozza létre, és **minden deploy során lefut**,
tehát a beépített szerepkörök jogosultságkészlete kódban van rögzítve.

### Szerepkörök

| Szerepkör | Kinek | Mit tud |
|---|---|---|
| **contributor** | minden regisztrált felhasználó alapból ezt kapja | saját tartalom létrehozása és szerkesztése |
| **editor** | megbízott szerkesztők | a teljes törzsadat karbantartása, verifikáció, kotta-közzététel elbírálása |
| **admin** | üzemeltetők | minden jogosultság, plusz az adminisztrációs felület |

### Jogosultságok

| Jogosultság | Jelentés | contributor | editor | admin |
|---|---|---|---|---|
| `content.create` | új tartalom létrehozása | ✔ | ✔ | ✔ |
| `content.edit.own` | saját tartalom szerkesztése és törlése | ✔ | ✔ | ✔ |
| `content.edit.published` | közzétett tartalom szerkesztése, visszavonása | | ✔ | ✔ |
| `content.edit.verified` | verifikált tartalom módosítása | | ✔ | ✔ |
| `masterdata.maintain` | törzsadat-karbantartás (egyesítés, címkék, hivatkozások) | | ✔ | ✔ |
| `scores.publish.review` | kotta-közzététel elbírálása | | ✔ | ✔ |
| `system.maintain` | rendszerszintű adminisztráció | | | ✔ |

Az első adminisztrátort az `ADMIN_EMAIL` konfigurációs érték jelöli ki
(`config('admin.email')`); a seeder ennek az e-mail címnek adja meg az `admin` szerepkört,
a többi meglévő felhasználónak pedig `contributor`-t.

---

## 2. Szerkesztői eszközök

A szerkesztői eszközök az oldalsáv **„Szerkesztő”** csoportjában jelennek meg, azoknak,
akiknek van szerkesztői jogosultságuk.

### 2.1 Énekek egyesítése

`/editor/musics/merge` – ha ugyanaz az ének kétszer szerepel az adatbázisban (például
eltérő írásmóddal), itt vonható össze a kettő.

1. Keresd meg a két összevonandó éneket.
2. Mezőnként döntsd el, melyik tétel adata maradjon meg.
3. Erősítsd meg az összevonást.

Az összevonás minden hivatkozást – énekrendeket, kottákat, gyűjtemény-sorszámokat,
szerzőket – a megmaradó tételre irányít át. **Visszafordíthatatlan.**

### 2.2 Duplikátumok egyesítése

`/editor/musics/duplicates` – ugyanaz a művelet, de a rendszer maga kínálja fel a
gyanúsan egyező párokat, így nem kell kézzel rákeresni mindkét tételre. Nagy import után
ez a gyorsabb út.

### 2.3 Énekek ellenőrzése és verifikáció

`/editor/musics/verify` – itt látod, mely énekek adatlapja hiányos vagy ellenőrizetlen.

A verifikáció **mező szinten** működik:

- `verifyField()` – egyetlen mező (vagy egy kapcsolat, például egy gyűjtemény-sorszám)
  megjelölése ellenőrzöttként, megjegyzéssel;
- `unverifyField()` – a jelölés visszavonása;
- `verifyAll()` – az adatlap összes mezőjének egyszerre történő elbírálása.

A verifikált mezőket a létrehozó **nem** módosíthatja többé, az ellenőrizetleneket
továbbra is igen. Ez védi azokat az énekrendeket, amelyek az adott tételre hivatkoznak.
A felhasználóknak szóló magyarázat az Útmutató 14. fejezetében van.

### 2.4 Kotta-közzététel elbírálása

`/editor/score-publications` – ez a kapu az [Ingyenes kották](/ingyenes-kottak) nyilvános
tárára. A felhasználók felajánlásai ide érkeznek sorba.

Az elbíráláskor látod a kottát, a hozzá megadott licencet és forrást, valamint
fájlonként, mi kerülne közzétételre. Műveletek:

| Művelet | Mikor |
|---|---|
| **Jóváhagyás** (`approve`) | a licenc és a forrás rendben van, a kotta valóban szabadon terjeszthető |
| **Elutasítás** (`reject`) | a jogi háttér hiányos vagy kétséges |
| **Levétel** (`takeDown`) | egy már közzétett kottát utólag ki kell vonni – például bejelentés nyomán |
| **Visszaállítás** (`restore`) | a levétel oka tisztázódott |
| **Bejelentés lezárása** (`dismissReport`) | a jogsértési bejelentés alaptalan |

A beérkezett jogsértési bejelentések (`openReports`) ugyanezen a felületen jelennek meg,
tételenkénti darabszámmal. A közzététel feltételeit a `/kotta-jogok` oldal írja le.

### 2.5 Zenei címkék

`/editor/music-tags` – a tematikus kulcsszavak karbantartása: új címke felvétele,
átnevezés, nem használt címke törlése. A címketípusokat a `MusicTagType` enum rögzíti.

### 2.6 Külső hivatkozások

`/editor/external-links` – az énekekhez rendelhető külső linkek (YouTube, kiadói oldal,
felvétel) központi kezelése: létrehozás, szerkesztés, törlés. A megadható domainek körét
az [URL whitelist](#33-url-whitelist) korlátozza.

---

## 3. Adminisztrációs felület

Az adminisztrációs felület az `/admin` útvonal alatt érhető el, `admin` szerepkörrel.

### 3.1 Felhasználók és szerepkörök

`/admin/users` – a regisztrált felhasználók listája. Egy felhasználó sorából nyitható a
szerepkör-szerkesztő, amelyben a hozzárendelt szerepkörök jelölőnégyzetekkel
állíthatók.

### 3.2 Szerepkör-jogosultságok

`/admin/role-permissions` – szerepkörönként állítható, mely jogosultságokat kapja meg. A
jogosultságok csoportosítva jelennek meg, és a listák kereshetők.

> **Figyelem:** a `RolePermissionSeeder` minden deploykor lefut, és a beépített
> szerepkörök jogosultságait visszaállítja a kódban rögzített készletre. A felületen tett
> eltérés tehát a következő deploynál elveszhet – tartós változtatás a seederben történik.

### 3.3 URL whitelist

`/admin/url-whitelist` – az engedélyezett domainek listája. A rendszer csak innen fogad
el külső hivatkozást az énekek adatlapjára; ez akadályozza meg a nem kívánt vagy veszélyes
linkek beillesztését.

### 3.4 Énekrend sablonok és slotok

- `/admin/music-plan-slots` – a liturgikus slotok törzsadata: név, leírás, sorrend,
  esetleges időszaki kötöttség (`special`, például Advent), prioritás.
- `/admin/music-plan-templates` – a sablonok, amelyekből a felhasználók énekrendet
  indítanak. Egy sablon másolható kiindulásnak.
- `/admin/music-plan-templates/{template}/slots` – egy adott sablon slotjainak
  összeállítása és sorrendje.

A slotnevek és a sorrend sablononként eltérhetnek, hogy egy-egy közösség szokásához
igazodjanak.

### 3.5 Tömeges importálás

`/admin/bulk-imports` – nagyobb énekanyag betöltése. Az importált sorok gyűjtemény és
tétel szerint böngészhetők és szűrhetők, majd egy kötegből (batch) énekek hozhatók létre
a kiválasztott gyűjteménybe. Az importot hosszabb műveletként érdemes kezelni: az
eredményről értesítés érkezik.

Kapcsolódó konzolparancs: `cantores:music-set-genre` – egy importált tételcsoport
műfajának utólagos beállítása.

### 3.6 Direktórium

`/admin/direktorium` – a liturgikus direktórium kiadásainak kezelése: PDF feltöltése,
feldolgozása oldaltartományonként, forrás-URL megadása, az aktuális kiadás kijelölése,
sikertelen feldolgozás megjelölése, kiadás törlése. A bejegyzések a
`/admin/direktorium/entries` oldalon nézhetők át.

A direktórium PDF-oldalai szerzői jogi okból csak bejelentkezett felhasználóknak
szolgálódnak ki (`/direktorium/{edition}/page/{page}`).

### 3.7 Tartalmi statisztika

`/admin/content-statistics` – felhasználónkénti bontásban mutatja, ki mennyi éneket,
szerzőt, gyűjteményt, kottát és énekrendet hozott létre, publikus és privát bontásban,
rendezhető oszlopokkal. Ez mutatja meg, kit érdemes szerkesztőnek felkérni.

### 3.8 Becenév-törzsadatok

`/admin/nickname-data` – a generált becenevekhez használt városok és keresztnevek listája.
A rendszer minden felhasználóhoz egyedi kombinációt rendel (például „Budapesti Anna”); ha
minden kombináció elfogyott, véletlenszerűen választ. A lista bővítésével nő a szabad
kombinációk száma.

---

*Cantores.hu – Szerkesztői és adminisztrátori kézikönyv*
