<x-layouts::app.main>
<div class="max-w-3xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold mb-6 text-accent">Használati feltételek</h1>
    
    <p class="mb-6 text-lg text-neutral-700 dark:text-neutral-200">
        Üdvözöljük a Cantores.hu oldalon! Kérjük, olvassa el figyelmesen a következő használati feltételeket.
        A szolgáltatás használatával Ön elfogadja ezeket a feltételeket.
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">1. Általános rendelkezések</h2>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        A Cantores.hu egy ingyenes, önkéntes alapon működő platform magyar katolikus kántorok számára.
        A szolgáltatást „jelen állapotában” nyújtjuk, garanciák nélkül. Az oldal fenntartója nem vállal felelősséget
        a szolgáltatás megszakadása, adatvesztés, vagy más hátrányos következményekért.
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">2. Regisztráció és fiók</h2>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        A szolgáltatás használatához regisztráció szükséges. Regisztráció során egy e‑mail cím megadása kötelező,
        amelyet kizárólag a bejelentkezéshez és a szolgáltatással kapcsolatos fontos értesítések küldéséhez használunk.
        <strong>Személyes adatot (pl. valódi név, lakcím, telefonszám) nem tárolunk.</strong>
    </p>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        A felhasználó bármikor törölheti a fiókját, amely során a rendszer az Önhöz kapcsolódó összes adatot véglegesen eltávolítja.
        A törlés után a helyreállítás nem lehetséges.
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">3. Tartalom és viselkedés</h2>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        A felhasználó köteles tartalmat (énekrendeket, megjegyzéseket) csak a jogszabályoknak megfelelően,
        mások szerzői jogait és személyiségi jogait megsértve nem feltölteni. A platform moderálási jogot fenntart,
        és a szabályokat sértő tartalmakat előzetes figyelmeztetés nélkül törölheti.
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">4. Adatvédelem</h2>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        Az Ön e‑mail címét és a platformon megadott nem személyes adatokat (pl. becenév, város, énekrendek)
        kizárólag a szolgáltatás működtetése céljából dolgozzuk fel.
        <strong>Harmadik félnek (reklámpartnereknek, értékesítőknek) semmilyen adatot nem adunk át.</strong>
        Az adatokat a magyar és az érvényes uniós adatvédelmi jogszabályoknak megfelelően kezeljük.
        Részletes információkért tekintse meg az <a href="{{ route('privacy') }}" class="text-accent hover:underline">Adatvédelmi nyilatkozatot</a>.
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">5. A szolgáltatás módosítása és megszüntetése</h2>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        A platform fenntartója jogosult a szolgáltatás tartalmát, működését bármikor módosítani,
        ideiglenesen vagy véglegesen megszüntetni, előzetes értesítés nélkül. A felhasználók ezzel a lehetőséggel
        ismerkedve és azt elfogadva használják a szolgáltatást.
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">6. Felelősség korlátozása</h2>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        A platform fenntartója semmilyen körülmények között nem felelős közvetlen vagy közvetett kárért,
        amely a szolgáltatás használatából vagy használatának képtelenségéből ered. A felhasználó saját felelősségére
        használja a rendszert.
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">7. Egyéb rendelkezések</h2>
    <p class="mb-6 text-neutral-700 dark:text-neutral-200">
        A jelen feltételek a magyar jog szerint értelmezendők. A felhasználói jogvitákra a magyar bíróságok illetékessége terjed ki.
        A feltételek időről időre frissülhetnek; a változások közzététele a weboldalon történik, és a közzététel pillanatától hatályosak.
    </p>

    <p class="mt-10 pt-6 border-t text-sm text-neutral-500 dark:text-neutral-400">
        Utolsó frissítés: {{ \Carbon\Carbon::now()->format('Y. m. d.') }}
    </p>
</div>
</x-layouts::app.main>