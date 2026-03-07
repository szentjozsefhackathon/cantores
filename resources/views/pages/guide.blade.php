<x-layouts::app.main>
<div class="max-w-3xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold mb-4 text-accent">Útmutató – Privát és publikus tartalmak</h1>
    <p class="mb-8 text-lg text-neutral-700 dark:text-neutral-200">
        A Cantores.hu különbséget tesz <strong>privát</strong> és <strong>publikus</strong> tartalmak között.
        Az alábbiakban megmagyarázzuk, mit jelent ez az egyes tartalom-típusoknál, és milyen szabályok vonatkoznak a szerkesztésre.
    </p>

    {{-- Privát vs. publikus magyarázat --}}
    <h2 class="text-2xl font-semibold mt-8 mb-4">Privát és publikus tartalmak</h2>

    <div class="space-y-6">

        {{-- Énekrend --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 bg-zinc-50 dark:bg-zinc-900">
            <h3 class="text-lg font-semibold mb-2 flex items-center gap-2">
                <span class="text-accent">♫</span> Énekrend
            </h3>
            <p class="text-neutral-700 dark:text-neutral-300 mb-3">
                Az énekrend egy adott liturgikus alkalomhoz összeállított énekek listája. Alapértelmezés szerint
                <strong>privát</strong>, ekkor csak te láthatod.
            </p>
            <ul class="list-disc pl-5 space-y-1 text-neutral-700 dark:text-neutral-300">
                <li><strong>Privát énekrend:</strong> csak te látod, senki más nem fér hozzá, és nem szerepel a javaslatokban sem.</li>
                <li><strong>Publikus énekrend:</strong> megjelenik a közzétett énekrendek között, és hozzájárul a közösségi javaslatok rendszeréhez. A pontos szerződ neve csak akkor látható, ha ezt a Beállításokban engedélyezted, egyébként csak a beceneved jelenik meg mások számára.</li>
            </ul>
            <p class="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                A közzétett énekrendet bármikor visszavonhatod (priváttá teheted), amíg az nincs zárolva. A szerkesztők szintén priváttá tehetik az énekrendedet, ha az nem felel meg a platform szabályainak.
            </p>
        </div>

        {{-- Ének --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 bg-zinc-50 dark:bg-zinc-900">
            <h3 class="text-lg font-semibold mb-2 flex items-center gap-2">
                <span class="text-accent">♪</span> Ének (zenei tétel)
            </h3>
            <p class="text-neutral-700 dark:text-neutral-300 mb-3">
                Az énekek (zenei tételek) a közös <strong>törzsadatbázis</strong> részei. Egy ének létrehozásakor te vagy a
                létrehozója, de az ének nyilvánosan elérhető a könyvtárban, hogy mások is hivatkozhassanak rá.
            </p>
            <ul class="list-disc pl-5 space-y-1 text-neutral-700 dark:text-neutral-300">
                <li><strong>Privát ének:</strong> csak te és a szerkesztők látják; nem szerepel a közös keresőben és mások nem hivatkozhatnak rá énekrendjeikben.</li>
                <li><strong>Publikus ének:</strong> mindenki számára kereshető és hivatkozható. Az adatokat te (a létrehozó) és a szerkesztők módosíthatják.</li>
            </ul>
            <p class="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                A szerkesztők priváttá tehetik az éneket, ha például duplikátumnak bizonyul, vagy az adatai pontatlanok és javítást igényelnek.
            </p>
        </div>

        {{-- Gyűjtemény --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 bg-zinc-50 dark:bg-zinc-900">
            <h3 class="text-lg font-semibold mb-2 flex items-center gap-2">
                <span class="text-accent">📁</span> Gyűjtemény
            </h3>
            <p class="text-neutral-700 dark:text-neutral-300 mb-3">
                A gyűjtemény énekeskönyveket, kiadványokat vagy tematikus énekcsoportokat jelöl. Szintén a közös
                törzsadatbázis részét képezi.
            </p>
            <ul class="list-disc pl-5 space-y-1 text-neutral-700 dark:text-neutral-300">
                <li><strong>Privát gyűjtemény:</strong> csak te és a szerkesztők látják; az ehhez tartozó énekek nem jelennek meg nyilvánosan gyűjtemény alapján szűrve.</li>
                <li><strong>Publikus gyűjtemény:</strong> mindenki számára látható és kereshető. Az adatokat te (a létrehozó) és a szerkesztők módosíthatják.</li>
            </ul>
        </div>

        {{-- Szerző --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 bg-zinc-50 dark:bg-zinc-900">
            <h3 class="text-lg font-semibold mb-2 flex items-center gap-2">
                <span class="text-accent">✍️</span> Szerző
            </h3>
            <p class="text-neutral-700 dark:text-neutral-300 mb-3">
                A szerzők szintén a közös törzsadatbázis részei: zeneszerzőket, szövegírókat, fordítókat jelölnek.
            </p>
            <ul class="list-disc pl-5 space-y-1 text-neutral-700 dark:text-neutral-300">
                <li><strong>Privát szerző:</strong> csak te és a szerkesztők látják; az ehhez kapcsolt énekek szerzői adatai nem nyilvánosak.</li>
                <li><strong>Publikus szerző:</strong> mindenki számára kereshető. Az adatokat te (a létrehozó) és a szerkesztők módosíthatják.</li>
            </ul>
        </div>

    </div>

    {{-- Szerkesztési jogosultságok --}}
    <h2 class="text-2xl font-semibold mt-12 mb-4">Ki szerkesztheti a törzsadatokat?</h2>
    <p class="mb-4 text-neutral-700 dark:text-neutral-200">
        A <strong>törzsadatok</strong> (énekek, gyűjtemények, szerzők) a közösség közös adatbázisát alkotják.
        Azért, hogy ezek megbízhatóak és következetesek maradjanak, a szerkesztési jogok korlátozottak:
    </p>
    <ul class="list-disc pl-5 space-y-2 text-neutral-700 dark:text-neutral-200 mb-6">
        <li>
            <strong>Te (a létrehozó)</strong> – mindaddig szerkesztheted a saját magad által létrehozott törzsadatot,
            amíg az nincs <em>verifikálva</em> (lásd lentebb).
        </li>
        <li>
            <strong>Szerkesztők</strong> – a platform megbízott szerkesztői bármely törzsadatot módosíthatnak,
            priváttá tehetnek, vagy összeolvaszthatnak más tételekkel.
        </li>
    </ul>
    <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 p-5 mb-6">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>Miért nem szerkeszthetnek mások törzsadatot?</strong><br>
            Ha bárki szabadon módosíthatná az énekek, gyűjtemények és szerzők adatait, a közösség által
            megosztott énekrendek megbízhatatlanná válnának. A korlátozás azt biztosítja, hogy
            a hivatkozott adatok ne változzanak meg észrevétlenül.
        </p>
    </div>

    {{-- Szerkesztők priváttá tételének joga --}}
    <h2 class="text-2xl font-semibold mt-10 mb-4">Mikor tehetnek priváttá a szerkesztők egy tartalmat?</h2>
    <p class="mb-4 text-neutral-700 dark:text-neutral-200">
        A szerkesztők az alábbi esetekben tehetnek priváttá egy nyilvános tartalmat:
    </p>
    <ul class="list-disc pl-5 space-y-2 text-neutral-700 dark:text-neutral-200">
        <li>Az adat <strong>duplikátum</strong> – egy másik tétel már tartalmazza ugyanezt az információt.</li>
        <li>Az adat <strong>pontatlan vagy hiányos</strong> – például hibás cím, rossz szerző, hiányzó kötelező mező.</li>
        <li>Az adat <strong>nem felel meg a platform szabályainak</strong> – pl. nem liturgikus vagy nem releváns tartalom.</li>
        <li>Az <strong>énekrend sérti a felhasználási feltételeket</strong>.</li>
    </ul>
    <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">
        Ha egy tartalmad priváttá vált és nem érted miért, vedd fel a kapcsolatot a szerkesztőkkel a
        <a href="{{ route('contact') }}" class="text-accent hover:underline" wire:navigate>Kapcsolat</a> oldalon.
    </p>

    {{-- Verifikáció --}}
    <h2 class="text-2xl font-semibold mt-10 mb-4">Miért nem tudom szerkeszteni/törölni a saját tartalmamat?</h2>
    <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950 p-5 mb-4">
        <p class="font-semibold text-amber-800 dark:text-amber-200 mb-2">A verifikáció zárojele</p>
        <p class="text-amber-800 dark:text-amber-200 text-sm">
            Ha egy ének, gyűjtemény vagy szerző adatainak pontosságát egy szerkesztő <strong>ellenőrizte és verifikálta</strong>,
            az adott tétel „zárolva" lesz a véletlen módosításokkal szemben.
        </p>
    </div>
    <p class="mb-4 text-neutral-700 dark:text-neutral-200">
        <strong>Mit jelent a verifikáció?</strong><br>
        A szerkesztők rendszeresen átnézik az adatbázisba kerülő új tartalmakat. Ha egy tétel adatai pontosnak és
        teljesnek bizonyulnak, a szerkesztő <em>verifikálja</em> azt. A verifikált tételek:
    </p>
    <ul class="list-disc pl-5 space-y-2 text-neutral-700 dark:text-neutral-200 mb-6">
        <li>megbízható forrásnak számítanak – sok énekrendben hivatkoznak rájuk;</li>
        <li><strong>nem módosíthatók</strong> a létrehozó által sem, csak a szerkesztők változtathatják meg;</li>
        <li><strong>nem törölhetők</strong> – törlés helyett priváttá tehető, ha szükséges.</li>
    </ul>
    <p class="mb-4 text-neutral-700 dark:text-neutral-200">
        <strong>Miért van ez így?</strong><br>
        Ha egy verifikált adatot a létrehozó utólag módosíthatna vagy törölhetne, az minden olyan
        énekrendet érintene, amely erre a tételre hivatkozik. A zárolás tehát a közösség adatainak
        integritását védi.
    </p>
    <p class="mb-4 text-neutral-700 dark:text-neutral-200">
        <strong>Mi a teendő, ha hiba van egy verifikált adatban?</strong><br>
        Ha úgy látod, hogy egy verifikált énekben, gyűjteményben vagy szerzőben hiba van, kérj javítást
        a szerkesztőktől a
        <a href="{{ route('contact') }}" class="text-accent hover:underline" wire:navigate>Kapcsolat</a> oldalon keresztül.
        A szerkesztők megvizsgálják és szükség esetén kijavítják az adatot.
    </p>

    {{-- Összefoglaló táblázat --}}
    <h2 class="text-2xl font-semibold mt-10 mb-4">Összefoglaló táblázat</h2>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm text-neutral-700 dark:text-neutral-300">
            <thead class="bg-zinc-100 dark:bg-zinc-800 text-left">
                <tr>
                    <th class="px-4 py-3 font-semibold">Tartalom</th>
                    <th class="px-4 py-3 font-semibold">Ki látja privátan?</th>
                    <th class="px-4 py-3 font-semibold">Ki szerkesztheti?</th>
                    <th class="px-4 py-3 font-semibold">Verifikálható?</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <tr class="bg-white dark:bg-zinc-900">
                    <td class="px-4 py-3 font-medium">Énekrend</td>
                    <td class="px-4 py-3">Csak te</td>
                    <td class="px-4 py-3">Te + szerkesztők</td>
                    <td class="px-4 py-3">Nem</td>
                </tr>
                <tr class="bg-zinc-50 dark:bg-zinc-800">
                    <td class="px-4 py-3 font-medium">Ének</td>
                    <td class="px-4 py-3">Te + szerkesztők</td>
                    <td class="px-4 py-3">Te (amíg nem verifikált) + szerkesztők</td>
                    <td class="px-4 py-3">Igen</td>
                </tr>
                <tr class="bg-white dark:bg-zinc-900">
                    <td class="px-4 py-3 font-medium">Gyűjtemény</td>
                    <td class="px-4 py-3">Te + szerkesztők</td>
                    <td class="px-4 py-3">Te (amíg nem verifikált) + szerkesztők</td>
                    <td class="px-4 py-3">Igen</td>
                </tr>
                <tr class="bg-zinc-50 dark:bg-zinc-800">
                    <td class="px-4 py-3 font-medium">Szerző</td>
                    <td class="px-4 py-3">Te + szerkesztők</td>
                    <td class="px-4 py-3">Te (amíg nem verifikált) + szerkesztők</td>
                    <td class="px-4 py-3">Igen</td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="mt-8 text-sm text-neutral-500 dark:text-neutral-400">
        Kérdésed van? Vedd fel velünk a kapcsolatot a
        <a href="{{ route('contact') }}" class="text-accent hover:underline" wire:navigate>Kapcsolat</a> oldalon,
        vagy tekintsd meg az <a href="{{ route('about') }}" class="text-accent hover:underline" wire:navigate>Rólunk</a> oldalt.
    </p>
</div>
</x-layouts::app.main>
