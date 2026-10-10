# Nástroje v adminu: co je veřejně vidět

Úkol #1 session E (`docs/prompts/opravy.md`), větev `feature/tool-fixes`, 10. 10. 2026.

Správce na **`/admin/tools`** přepíná, který nástroj z `config/tools.php` vidí veřejnost. Skrytý nástroj dál otevře a
vyzkouší (náhled, stažení, „Vytisknout u nás“), ostatní ho nenajdou a jeho adresa jim vrací 404.

## 1. Pravidlo

Nástroj je **veřejný**, když ho nabízí config (`available => true`) **a zároveň** ho správce nevypnul
(`tool_flags.public`; bez řádku rozhoduje jen config). Rozhoduje `App\Domain\Tools\ToolVisibility`:

- `isPublic($tool)` – pro seznamy: katalog `/tools` a jeho kategorie a hledání, karty na úvodní stránce a jejich
  počet, „další dárky“ a odkazy na cedulku na `/gifts`, sitemapa (`ToolSeo::tools()`), obrázek pro sdílení
  (`/og/tool/…`), počítání návštěv nástroje (`RecordVisit`).
- `canOpen($user, $tool)` – veřejný nástroj každý, jakýkoli nástroj správce.
- `canUseKind($user, $kind)` / `canUseEdit($user, $op)` – API. Jeden generátor slouží víc nástrojům (logo a SVG do
  STL, skladba a jmenovka, cedulka a text): generátor je otevřený, dokud je návštěvníkovi otevřený aspoň jeden
  nástroj, který na něm stojí. Které to jsou, se čte z rout stránek (`kind`, `form`, `op`), ne ze seznamu v kódu.

Nástroj s `available => false` v configu (brčko, otvírák, vložka do zásuvky) se chová stejně jako skrytý: hostovi
404, správci stránka. Přepínač v adminu ho veřejným neudělá, to umí jen změna configu; stránka adminu to u něj říká.
**Pozor, změna proti dřívějšku:** tyhle tři stránky se dřív na přímé adrese otevřely komukoli, teď jen správci.

## 2. Kde se to hlídá

`App\Http\Middleware\ToolGate` (ve skupině `web`, `bootstrap/app.php`) – jedno místo pro stránky i API, controllery
nástrojů se neměnily:

| co | podle čeho | neveřejný nástroj |
|---|---|---|
| stránka nástroje (všechny jazyky) | jméno routy = `route` v configu | 404; správce projde a stránka dostane lištu + `noindex` |
| `POST /api/tools/param`, `/preview`, `/zip` | `kind` v požadavku | 404, správce projde |
| `POST /api/tools/sign` | generátor `sign` | 404, správce projde |
| `POST /api/tools/art`, `/preview`, `/zip` | nástroj `filament_art` | 404, správce projde |
| `POST /api/tools/relief` | nástroj `relief` | 404, správce projde |
| `POST /api/files/{uuid}/edit`, `/edit/analysis` | `op` v požadavku | 404, správce projde |

Lišta („Nástroj je skrytý pro veřejnost, vidíte ho jako správce“ + odkaz na řádek v adminu) a `noindex` jsou
v `layouts/app.blade.php`; middleware jim předává klíč nástroje v atributu požadavku `hidden_tool`.

Co se **nehlídá** a proč:

- **Kalkulačka** (`calc`, routa `home`) je úvodní stránka webu. Skrytím zmizí jen její karta z katalogu.
- **Stažení dílů hotového návrhu** (`GET /api/tools/param/{uuid}/{díl}.stl`, `/api/tools/edit/{uuid}/…`): návrh, který
  si někdo vyrobil před skrytím, mu zůstává.
- **Forma, oprava, kontrola, figurka z fotky** (`/api/files/{uuid}/mold`, `/repair`, `/api/generate`…): tahle API
  volá i kalkulačka. Skrytím zmizí stránka a karta nástroje, API běží dál.
- **Odkazy psané natvrdo mimo katalog** řeší úkol #2: odkaz „poskládat vlastní“ pod formuláři
  (`tools/param.blade.php` → `/tools/compose`) a dlaždice náhradního dílu na úvodní stránce se ukážou jen tomu,
  komu se cílová stránka otevře (`ToolVisibility::canOpen`, tedy správci i u skrytého nástroje). Odkaz z rychlého
  formuláře jmenovky a klíčenky vede na skladbu na téže adrese, ne na `/tools/compose`, a zůstává.
- **Statistiky v adminu**: filtr nástrojů ve Statistikách nabízí jen veřejné nástroje.
- **Karty** (`matplace:tool-examples --card`) se kreslí podle configu jako dřív; obrázek skrytého nástroje zůstává.

## 3. Cache a sitemapa

Přepínače se čtou jednou za požadavek a drží se v cache minutu (`Cache::remember('tool_flags', 60)`). Uložení přes
model `ToolFlag` cache maže, změna v adminu je tedy vidět hned. Řádek zapsaný mimo model (ručně v databázi) se
projeví do minuty. Když tabulka ještě není (kód je nasazený dřív než migrace), rozhoduje jen config.

Sitemapa se píše jednou denně (`matplace:sitemap`, 04:40). Uložení přepínače přepíše hned jen soubory
`sitemap-tools-{jazyk}.xml` (`Sitemaps::refreshTools()`), zbytek se nechává nočnímu běhu.

## 4. Admin

`/admin/tools` (`Admin\ToolController`, položka „Nástroje“ vedle „Katalog“): všechny nástroje z configu – název,
adresa, kategorie, ověření tiskem, stav v configu, přepínač **Veřejně viditelný**, poznámka, kdo a kdy změnil.
Řádek se ukládá bez načtení stránky (přepínač hned, poznámka po opuštění pole nebo Enterem); bez skriptů uloží
tlačítko. Filtry: vše / skryté / neověřené tiskem. Texty: `lang/{cs,en,es}/tools.php`, klíče `admin.*`.

## 5. Nasazení

**1 migrace**: `2026_10_16_100000_create_tool_flags` (nová tabulka, nic nepřepisuje). Hodnoty `available` ani
`verified` v `config/tools.php` se neměnily.

Mimo zadání přibyl jeden řádek v `AppServiceProvider`: `tools.spare.available` teď sleduje živý přepínač tržiště
(`farm_settings.marketplace`) stejně jako `features.marketplace`. Bez toho by stránka náhradního dílu vracela 404
tam, kde je tržiště zapnuté v adminu a ne v `.env`.

## 6. Testy

`tests/Feature/ToolVisibilityTest.php` (8 testů). Upravené testy jiných session: `ToolsCatalogTest` čte
`ToolVisibility`; `HeldShapesTest` a `InsertToolTest` se v `setUp` přihlašují jako správce (jejich nástroje jsou
v configu vypnuté, stránka i generátor jsou tedy jen pro správce) a nově ověřují 404 pro hosta (`InsertToolTest` po sobě maže fotky nahrané
pod účtem správce, jinak by je další test našel mezi „mými obrázky“); `ToolPageTest` otevírá stránky těch tří
nástrojů jako správce. Dotčené sady po sloučení `main` 718b652: 349 testů zelených.

Neověřeno ručně v prohlížeči na produkci: přepínání na `/admin/tools` proti skutečné cache serveru.
