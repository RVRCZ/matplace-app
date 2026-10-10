# Opravy nástrojů (log úkolů pro session E)

Úkoly zadává řídící session podle postřehů Romana; session E dopisuje sloupce „hotovo“ a „commit“.

| # | datum | nástroj | co | kde | hotovo | commit |
|---|---|---|---|---|---|---|
| 1 | 10. 10. | admin + katalog nástrojů | zapnutí/skrytí jednotlivých nástrojů v adminu; skrytý nástroj zůstává správci přístupný k testování | viz úkol #1 níže | 10. 10. (popis v `docs/TOOLS-ADMIN.md`) | „Opravy #1“ na `feature/tool-fixes` |
| 2 | 10. 10. | úvodní stránka + formuláře nástrojů | dlaždice náhradního dílu a odkaz „poskládat vlastní“ se řídí přepínačem nástroje | viz úkol #2 níže | 10. 10. (`ToolVisibility::canOpen`: host podle přepínače, správce vidí dál) | „Opravy #2“ na `feature/tool-fixes` |
| 3 | 10. 10. | stránky návrhu všech nástrojů | tlačítko „Pokračovat k přesné ceně a tisku“ → „Pokračovat ke kalkulaci“; pod cenou se dvakrát říká totéž o dalším kroku | viz úkol #3 níže | 10. 10. (šest klíčů v `lang/src/tools_flow.json`, test v `ToolPageTest`) | „Opravy #3“ na `feature/tool-fixes` |
| 4 | 10. 10. | všechny stránky nástrojů | jedno tlačítko „Pokračovat ke kalkulaci“ a jeden text pod cenou pro všechny nástroje (reliéf, figurka, úpravy souboru, kontrola, forma mají dnes jiné) | viz úkol #4 níže | 10. 10. (bez výjimek: i kontrola, oprava a forma otevírají kalkulačku; `toolpage.go*` smazáno) | „Opravy #4“ na `feature/tool-fixes` |
| 5 | 10. 10. | všechny stránky nástrojů | krok „Materiál a počet kusů“ (dnes jen u parametrických nástrojů) na každé stránce nástroje, zvolený materiál a počet jdou do kalkulace; mrtvé texty tlačítek pryč | viz úkol #5 níže | 10. 10. (krok kreslí `tools/page.blade.php`; reliéf a figurka mají „Vytvořit“ na konci kroku 2; přenos ověřen v prohlížeči na split, letter-beads, filament-art) | „Opravy #5“ na `feature/tool-fixes` |
| 6 | 10. 10. | krok „Materiál a počet kusů“ | v úzkém panelu (340 px) se název materiálu ve výběru ořízne („Běžný plast (PL“) | viz úkol #6 níže | 10. 10. (materiál přes celou šířku panelu, počet kusů pod ním; změřeno v prohlížeči při 340 px, cs/en/es) | „Opravy #6“ na `feature/tool-fixes` |
| 7 | 10. 10. | nástroje pro úpravu souboru (hollow, life-size, puzzle, holder, potion, flexi-cut, colors, soap, wearable, slider) | pod souborem svítí syrový klíč fáze („edit.stage.repairing“) – společné texty fází chybí v lang/*/edit.php | viz úkol #7 níže | 10. 10. (11 společných textů fází cs/en/es; stránka neposílá skriptu klíč bez textu – na živé /tools/hollow jich bylo 69) | „Opravy #7“ na `feature/tool-fixes` |

## Úkol #1 · 10. 10. 2026 · Zapnutí a skrytí nástrojů v adminu (koordinováno: dotýká se `config/tools.php`, `ToolsController`, sitemapy, `/gifts`)

**Co Roman chce:** v adminu přepínat, který nástroj je **veřejně vidět**, a vypnutý nástroj dál **otevřít a doladit jako
správce** (stránka funguje, náhled, stažení i „Vytisknout u nás“), jen není v katalogu, v sitemapě, v odkazech a pro
nepřihlášené vrací 404.

**Dnes:** `config/tools.php` má u každého nástroje `available => true|false`; podle toho se skládá katalog `/tools`,
karty (`matplace:tool-examples --card`), sitemapa (`SitemapController`), odkazy z `/gifts` a stránek nástrojů
(`ToolsController`, kontrola v `ToolsCatalogTest`). Vypnutý nástroj dnes nevidí ani správce (brčko, otvírák, vložka
do zásuvky jsou `available => false` a Roman je nemůže vyzkoušet na webu).

**Udělat:**
1. Tabulka `tool_flags` (migrace): `tool` (klíč z `config/tools.php`, unikátní), `public` (bool, výchozí = hodnota
   `available` z configu), `note` (text, nepovinná), `updated_by`, časy. Model `ToolFlag`. Služba
   `App\Domain\Tools\ToolVisibility` s `isPublic(string $tool): bool` (= `config available` && `flag.public`,
   výchozí bez řádku = config) a `canOpen(?User $user, string $tool): bool` (= `isPublic` || správce), s cache
   (`Cache::remember('tool_flags', 60)`, smazat při uložení).
2. Všechna místa, která dnes čtou `available`, čtou **`ToolVisibility::isPublic`** (katalog `/tools`, kategorie, karty,
   `/gifts`, sitemapa, odkazy „další nástroje“, OG obrázky, vyhledávání nástrojů, `ToolsCatalogTest`). Stránka
   nástroje: nepřihlášený nebo běžný uživatel → 404 (jako dnes); **správce** → stránka normálně + horní lišta
   „Nástroj je skrytý pro veřejnost, vidíte ho jako správce“ s odkazem do adminu, a `<meta name="robots"
   content="noindex">`. API nástroje (`/api/tools/param`, `/api/tools/edit/…`) pro skrytý nástroj: povolit jen
   správci (stejná kontrola), jinak 404.
3. Admin: nová stránka **`/admin/tools`** (položka „Nástroje“ v admin navigaci vedle katalogu): tabulka všech nástrojů
   z configu – název, cesta, kategorie, `verified`, stav v configu, přepínač **Veřejně viditelný**, poznámka, kdo a kdy
   změnil; uložení řádku bez reloadu (jako jinde v adminu), hromadné „zobrazit vše / skrýt vše“ ne. Filtry: vše /
   skryté / neověřené tiskem.
4. Texty cs/en/es (`lang/*/tools.php` nebo `admin.php`, kde jsou texty adminu): název stránky, sloupce, lišta pro
   správce, poznámka.
5. Testy: `tests/Feature/ToolVisibilityTest.php` – skrytý nástroj: katalog ho neukazuje, sitemapa ne, stránka 404
   pro hosta i uživatele, 200 + lišta pro správce, API 404 pro hosta a 201 pro správce; přepnutí v adminu změní vše
   bez restartu (cache); nástroj s `available => false` v configu se chová jako skrytý a správce ho otevře;
   `ToolsCatalogTest` upravit tak, aby používal `ToolVisibility`.
6. `docs/U.md`? Ne – tahle session píše do `docs/prompts/opravy.md` (řádek úkolu) a krátce do `docs/TOOLS-ADMIN.md`
   (co to je, jak to funguje, cache, migrace).

**Hotovo =** správce v adminu skryje třeba `/tools/box`: zmizí z `/tools`, `/gifts`, sitemapy a karet; host dostane 404;
správce stránku otevře s lištou, vyzkouší náhled, stažení i „Vytisknout u nás“; po zapnutí je vše zpět. Testy zelené,
`pint`, `tsc`, `build`. Nasazení = 1 migrace (napiš to do zprávy řídící session).

**Nedělat:** neměnit `config/tools.php` hodnoty `available` ani `verified`; nepřidávat další pole do adminu (pořadí,
kategorie) – to budou další úkoly.

## Úkol #2 · 10. 10. 2026 · dva odkazy podle ToolVisibility (koordinováno: jen tyto dva soubory)

`resources/views/calculator/home.blade.php` – dlaždice náhradního dílu se řídí jen tržištěm, ne přepínačem; `resources/views/tools/param.blade.php` –
odkaz „poskládat vlastní“ vede na `/tools/compose` natvrdo (po skrytí compose je pro hosta 404). Obojí přes `ToolVisibility::isPublic`
(správce vidí dál). Test v `ToolVisibilityTest`. Hotovo = skrytý compose/spare nemá na těchto místech odkaz pro hosta.

## Úkol #3 · 10. 10. 2026 · „Pokračovat ke kalkulaci“ a jedna věta o dalším kroku (jen texty)

**Co Roman chce:** na stránce návrhu nástroje přejmenovat tlačítko **„Pokračovat k přesné ceně a tisku“** na
**„Pokračovat ke kalkulaci“**. Při tom opravit, že pod orientační cenou se dvakrát řekne totéž: „Odhad z objemu modelu.
Přesnou cenu a dobu tisku spočítáme v dalším kroku. V dalším kroku uvidíte přesnou cenu a dobu tisku. Výtisk si…“

**Kde:** texty jsou v JSON překladech `lang/cs.json`, `lang/en.json`, `lang/es.json`; zdroj je `lang/src/tools_flow.json`
(`{"klíč": ["cs", "en", "es"]}`), do JSONů se slévá `python scripts/lang_add.py lang/src/tools_flow.json` (uprav zdroj,
pusť skript, commitni zdroj i všechny tři JSONy). Klíče: `param.go.farm` (tlačítko ve farmovém režimu – to je produkce),
`param.go.download` (režim bez farmy), `param.estimate.note.farm` + `param.go.hint.farm` – ty dva se skládají za sebe v
`resources/views/tools/param.blade.php` řádek 107 a `filament_art.blade.php` řádek 33 (`NextStep::text`), proto ta
zdvojená věta; `param.estimate.note.download` + `param.go.hint.download` stejně.

**Udělat (jen texty, žádný kód):**
1. `param.go.farm`: „Pokračovat ke kalkulaci“ / „Continue to the calculation“ / „Continuar al cálculo“.
   `param.go.download`: „Pokračovat ke kalkulaci“ / stejně / stejně (i bez farmy je další krok kalkulace).
2. `param.estimate.note.farm` i `.download`: jen „Odhad z objemu modelu.“ / „An estimate from the model volume.“ /
   „Una estimación según el volumen del modelo.“ – větu o dalším kroku z nich vyhodit, zůstane jen v `go.hint`.
3. `param.go.hint.farm`: „V kalkulaci uvidíte přesnou cenu a dobu tisku. Výtisk si objednáte u nás, nebo si stáhnete
   soubor pro svou tiskárnu.“ (en/es ve stejném duchu: „The calculation shows the precise price and print time…“);
   `param.go.hint.download`: „V kalkulaci uvidíte přesnou dobu tisku a spotřebu materiálu a stáhnete si soubor nebo
   hotový projekt pro svou tiskárnu. Bez registrace.“
4. Ověřit, že `toolpage.go` v `lang/*/toolpage.php` (režim tržiště, „Pokračovat k přesné ceně“) nikdo na produkci
   nevidí – nechat být. Ostatní výskyty „k přesné ceně“ v `lang/*/tools_seo/*.php` (SEO odstavce) **neměnit**, to je
   další úkol, až Roman řekne.
5. Test: v `ToolPageTest` (nebo kde se testuje stránka nástroje) jedno `assertSee('Pokračovat ke kalkulaci')` a
   `assertDontSee('V dalším kroku uvidíte přesnou cenu a dobu tisku. V dalším kroku')` – ať se zdvojení nevrátí.

**Hotovo =** na `/tools/letter-beads` (a každé stránce nástroje) je tlačítko „Pokračovat ke kalkulaci“ a pod cenou:
„Odhad z objemu modelu. V kalkulaci uvidíte přesnou cenu a dobu tisku. Výtisk si objednáte u nás, nebo si stáhnete
soubor pro svou tiskárnu.“ Testy zelené, `pint`, `tsc`, `build` (JSON překlady jdou do bundle? – ne, čtou se v PHP;
build přesto pustit). Bez migrace.

## Úkol #4 · 10. 10. 2026 · jedno tlačítko a jeden text pod cenou pro všechny nástroje (koordinováno: `tools/page.blade.php`, `relief.blade.php`, `figure.blade.php`, `edit.blade.php`, `filament_art.blade.php`, `param.blade.php`, `check.blade.php`, `mold.blade.php`)

**Co Roman chce:** „sjednotit u všech nástrojů, prostředí a texty u nástrojů musí být stejné nebo podobné.“ Po #3 mají
reliéf a figurka z fotky na produkci dál „Vytisknout u nás“ (berou `toolpage.go`, jehož farmová varianta je
`toolpage.go.farm`), nástroje pro úpravu souboru (`edit.blade.php`: split, hollow, …) mají pod cenou jen „Odhad z objemu
modelu.“ bez věty o dalším kroku, kontrola (`check.page.go`) a forma mají vlastní popisky tlačítka.

**Udělat:**
1. `tools/page.blade.php`: výchozí popisek tlačítka = `NextStep::text('param.go')` (ne `toolpage.go`) a výchozí text
   pod cenou = `NextStep::text('param.estimate.note')` + mezera + `NextStep::text('param.go.hint')` – tedy to, co má
   po #3 `param.blade.php`. Stránky, které si dnes posílají `goLabel` nebo `@section('price-note')` jen proto, aby
   dostaly totéž, to přestanou posílat (`relief`, `figure`, `param`, `filament_art`, `edit`). Vlastní `goLabel` zůstane
   jen tam, kde další krok opravdu není kalkulace – projdi `check` („Zjistit cenu a objednat tisk“) a `mold`: pokud u nich
   po tlačítku následuje kalkulace, sjednotit taky; pokud ne, napsat mi proč.
2. `toolpage.go`, `toolpage.go.farm`, `toolpage.go.download` v `lang/*/toolpage.php`: pokud po bodu 1 nikdo nepoužívá,
   smazat (všechny tři jazyky); pokud ano, nastavit stejné texty jako `param.go*`.
3. Test v `ToolPageTest`: pro **každý** nástroj z `config('tools')` (ten cyklus na řádku 31) ve farmovém režimu
   `assertSee('Pokračovat ke kalkulaci')` a `assertSee('Odhad z objemu modelu. V kalkulaci uvidíte přesnou cenu a dobu
   tisku.')` (vyjma nástrojů, u kterých bod 1 výslovně nechá jiný text – vyjmenovat v testu s důvodem).
4. Nic jiného v rozložení stránek neměnit; „prostředí“ (stejná struktura kroků, stejná lišta s cenou) je už dané
   `tools/page.blade.php` – jen zkontroluj, že reliéf a figurka opravdu přes něj jdou (ano, `@extends('tools.page')`).

**Hotovo =** na `/tools/relief`, `/tools/figure`, `/tools/split`, `/tools/letter-beads`, `/tools/filament-art` je stejné
tlačítko „Pokračovat ke kalkulaci“ a stejná věta pod cenou. Testy zelené, `pint`, `tsc`, `build`. Bez migrace.

## Úkol #5 · 10. 10. 2026 · krok „Materiál a počet kusů“ na každé stránce nástroje (koordinováno: `tools/page.blade.php`, `tools/param.blade.php`, `resources/js/calc/tool_page.ts`, `param.ts`, moduly `edit`, `relief`, `figure`, `art`, `mold`, `check`, `repair`, `picture`; `lang/src/*.json`)

**Co Roman chce:** „prostředí a texty u nástrojů musí být stejné nebo podobné“ a funnel bez dvojího ptaní. Dnes má krok
**„Tisk nebo stažení“** (výběr materiálu `#param-material` a počtu kusů `#param-qty`) jen `param.blade.php`
(parametrické nástroje) a `spare`; ty při „Pokračovat ke kalkulaci“ posílají `?open=…&material=…&quantity=…` (`param.ts`,
funkce `save`), kalkulace si je převezme (`calculator.ts` řádek ~829). Ostatní stránky (`edit` = split, hollow…,
`relief`, `figure`, `filament_art`, `mold`, `check`, `repair`, `picture`) krok nemají, orientační cena u nich počítá
s výchozím materiálem a `Stage.fileResult` (`tool_page.ts` ř. 272) posílá jen `?open=…`, takže materiál a počet kusů
zákazník vybírá až v kalkulaci.

**Udělat:**
1. Krok s materiálem a počtem kusů přesunout do společné stránky: `tools/page.blade.php` vykreslí sekci
   `print` (stejný markup jako dnes v `param.blade.php` ř. 462–470: select materiálů z `cfg.price.materials`,
   číslo kusů 1–1000) pro **každý** nástroj, `param.blade.php` svou kopii přestane vykreslovat. Pořadí kroků a
   navigace (`#tool-nav`) zůstává, u nástrojů bez kroku „Barvy“ je to krok 3.
2. `Stage` (`tool_page.ts`): zná zvolený materiál a počet (`material()`, `quantity()`), orientační cena (`price()`)
   je přepočítá při každé změně (jako dnes `param.ts`), `fileResult` staví odkaz `?open=uuid&material=…&quantity=…`
   (+ `download=1` kde už je). `param.ts` použije totéž místo vlastního čtení `#param-material` / `#param-qty`
   (pole `material`, `quantity` v `save`). Nic jiného v `param.ts` neměnit (soubor sdílí session 1).
3. Název kroku: `param.step.inquiry.farm` i `.download` → **„Materiál a počet kusů“** / „Material and quantity“ /
   „Material y cantidad“ (`lang/src/tools_flow.json` → `python scripts/lang_add.py …`). Tržištní `param.step.inquiry`
   („Poptávka“) nechat.
4. Mrtvé texty po #4 smazat: `check.page.go`, `check.page.go.farm`, `check.page.go.download`, `mold.page.go`,
   `mold.page.go.download` – ručně z `lang/cs.json`, `en.json`, `es.json` a ze zdrojů `lang/src/stage2.json`,
   `lang/src/tools_flow.json` (skript umí jen přidávat).
5. Testy: `ToolPageTest` – každý nástroj z `config('tools')` má na stránce `id="param-material"` a `id="param-qty"`
   a krok „Materiál a počet kusů“; jeden test (třeba relief nebo split) v headless režimu nejde, tak alespoň
   jednotkově: `Stage.fileResult` sestaví odkaz s `material` a `quantity` (když je TS test harness; když ne, ověř ručně
   v prohlížeči na `/tools/split` a napiš to do zprávy). `ToolsFlowTest`: nic se nerozbije.

**Hotovo =** na `/tools/split`, `/tools/relief`, `/tools/figure` i `/tools/letter-beads` je stejný krok „Materiál a počet
kusů“, orientační cena na něj reaguje, po „Pokračovat ke kalkulaci“ má kalkulace předvybraný ten materiál a počet.
Testy, `pint`, `tsc`, `build`. Bez migrace. **Nerozšiřovat** o další volby (kvalita, výplň) – ty patří do kalkulace.

## Úkol #6 · 10. 10. 2026 · celý název materiálu ve výběru kroku „Materiál a počet kusů“ (koordinováno: `tools/page.blade.php`)

**Co:** tvůj postřeh z #5 – ve 340px panelu se text výběru materiálu ořízne („Běžný plast (PL“); dřív to bylo jen u
generátorů, po #5 všude. **Udělat:** výběr materiálu na vlastní řádek přes celou šířku panelu a počet kusů pod něj
(nebo vedle s pevnou šířkou ~6 rem), tak aby i „Běžný plast (PLA)“ a nejdelší název z `cfg.price.materials` byly vidět
celé při 340 px; nic jiného v kroku neměnit. **Hotovo =** na `/tools/split` a `/tools/letter-beads` při šířce panelu
340 px je celý název materiálu vidět; `ToolPageTest` zelený, `tsc`, `build`.

## Úkol #7 · 10. 10. 2026 · texty fází u nástrojů pro úpravu souboru (koordinováno: `tools/edit.blade.php`, `lang/{cs,en,es}/edit.php` – soubory session 3, která neběží)

**Od Romana** (snímek z `/tools/hollow` poslaný přímo session E): pod souborem je vidět „edit.stage.repairing“.
**Příčina** (E): `tools/edit.blade.php` ř. 11 bere text nástroje `edit.<op>.<klíč>` a jinak společný `edit.<klíč>`;
společné `edit.stage.*` v `lang/*/edit.php` nejsou (jen `edit.split.stage.*` a dvě `edit.hollow.stage.*`), do stránky
jde syrový klíč a `edit.ts` ř. 219 ho bere jako text. Týká se všech nástrojů úpravy kromě split a fází queued,
loading, thinning, repairing, cutting, joints, numbers, layout, done.
**Udělat:** společné texty fází `edit.stage.*` ve třech jazycích; blade neposílá klíče bez textu (záložní texty
skriptu pak platí). Test v `ModelEditTest`/`ToolPageTest`: žádná stránka nástroje nenese v datech pro skript syrový
klíč fáze. **Hotovo =** `/tools/hollow` při zpracování ukazuje českou/anglickou/španělskou fázi; testy, `pint`, `tsc`, `build`.

## Poznámky pro session 1 (z úkolu #1, předat až poběží)

- `HeldShapesTest`, `InsertToolTest` se přihlašují jako správce už v `setUp` (vypnuté nástroje straw/opener/insert jsou pro hosta 404,
  v API i na stránce); `InsertToolTest` v `tearDown` maže fotky nahrané pod správcem; `ToolPageTest` otevírá straw/opener/insert jako správce.
- `tools/param.blade.php`: odkaz „poskládat vlastní“ na `/tools/compose` natvrdo (řeší úkol #2).
- Skrytý nástroj: `ToolGate` middleware (web) hlídá stránky i API; `config/tools.php` `available=false` = skryto pro veřejnost, správce otevře.
