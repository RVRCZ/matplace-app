# AJ – měření přínosu podle auditu (bod 12) a naše průchody mimo čísla (TODO #10)

Session 4, 17. 10. 2026, větev `feature/audit-events` (z `main` 10245aa), zadání `docs/prompts/audit-mereni.md`,
podklad `docs/AUDIT.md` bod 12 a `docs/O.md`. Nic se nemerguje ani nenasazuje. Migrace není.

## 1. Inventura (stav před touto větví)

Události zapisuje jen server, jedním místem: `App\Support\Track` → tabulka `events`. Prohlížeč (`resources/js/site/measure.ts`)
sám nic nezapisuje. Pošle jen „stránka se opravdu zobrazila“ (`POST /api/seen`) a události, které mu server vrátí
(v `window.MP_MEASURE.events` nebo v hlavičce `X-Matplace-Events`), předá GA4 a Meta pixelu, když to návštěvník
v cookie liště povolil. Kliky s `data-track` jdou jen do GA a Meta; dnes je žádný prvek nemá.

| typ | kde vzniká | předmět | `meta` | trychtýř |
|---|---|---|---|---|
| `visit` | `Track::visit` (middleware `RecordVisit`, první stránka session) | – | `path`, `tool`; později `js` / `act` / `bot` / `staff` | začátek všech cest |
| `page` | `Track::seen` (`/api/seen`) | `page` | `path` | přehled (stránky) |
| `crawl` | `Track::crawl` (robot) | – | `path`, robot v `source` | jen přehled robotů |
| `view` | `Track::view` (stránka modelu, designéra, inspirace), `Track::tool` (stránka nástroje) | model / designér / `tool` | `tool` | `model_view`, `tool_view` |
| `ref_visit` | `RememberReferral` (odkaz designéra `?ref=`) | designér | – | designéři |
| `upload` | `Api\UploadController::store` (soubor přijat, ještě nezpracován) | soubor | `kind` = přípona | zákazník krok 2 |
| `generate` | `Api\GenerationController::store` / `refine` (požadavek odeslán) | – | `kind` = image / text / refine | zákazník krok 2, majitel |
| `calculation` | `Api\CalculationController::store` (výpočet zadán) | soubor | `tool` | zákazník krok 3, majitel |
| `download` | middleware `GuardModelFile` (STL s `?download=1` nebo projekt 3MF, odpověď 2xx) | soubor / karta | `kind` stl / 3mf, `tool`, `printer` | majitel (výsledek) |
| `search` | `Api\SearchController` | – | `results` | – |
| `register` | `AuthController`, `OAuthController` | uživatel | `kind`, `event_id` | designér krok 2 |
| `designer_enabled`, `designer_import`, `designer_file_uploaded` | designérské profily a karty | profil / karta | `kind` | designér |
| `order_created` | `Farm\OrderController::store` (objednávka založena) | soubor / karta | `order`, `tool` | – |
| `order_paid` | `Farm\OrderController::payFromRequest` (zaplaceno z kreditu v tomtéž požadavku) | soubor / karta | `order`, `value`, `currency`, `tool`, `event_id` | zákazník (výsledek) |

**Trychtýř** (`App\Domain\Stats\Funnel`, `/admin/stats`): návštěvník = anonymní session prohlížeče (`mp_sid`).
Krok počítá návštěvníky, kteří udělali jeho i všechny předchozí kroky, v období 7, 30 nebo 90 dní. Cesty
zákazník (visit → upload|generate → calculation → order_paid), majitel tiskárny (visit → stránka modelu nebo
výstup nástroje → download) a designér. Robot, staff a nepotvrzená návštěva vypadnou i se vším, co udělaly.

**Potvrzená platba** se odvozuje jen ze stavu na serveru, nikdy z návštěvy děkovací stránky. Při placení
z kreditu vzniká `order_paid` až po zaúčtování (`OrderFlow::pay`). **Nález:** při placení kartou banka potvrdí
platbu webhookem (`CreditController` → `OrderFlow::payFromCard`), ale `order_paid` tam nevznikal vůbec. Každá
objednávka zaplacená kartou tedy v trychtýři chyběla. Opraveno (§2). `card.status` ve stavu objednávky
(`Payment.status`) slouží stránce, která čeká na banku, ne měření.

**Chybělo** (audit): výsledek nahrání nebo vytvoření modelu (bylo jen „odesláno“), zobrazení ceny (bylo jen
„zadáno“), zahájení objednávky ze stránky objednávky, přechod k platbě, chyby, neúspěšné stažení, vstupní
cesta, dělení podle zařízení a nový / vracející se, značka našich průchodů.

## 2. Doplněné události

Pojmenování je stejné jako u dnešních typů (podstatné jméno, příp. s příčestím). Všechny zapisuje `Track`, takže
platí stejná pravidla (roboti, staff, testy).

| typ | kdy | kde | `meta` |
|---|---|---|---|
| `model_ready` | majitel souboru dostane odpověď, že model je hotový (nahraný soubor, výstup nástroje, vygenerovaný model) | `Api\UploadController::measure` (z `store` i `show` = `GET /api/files/{uuid}`), `Api\GenerationController::show` | `kind` upload / tool / generated, `tool` |
| `price_shown` | majitel výpočtu dostane přesnou cenu ze sliceru | `Api\CalculationController::measured` (`store` i `show`) | `kind` = exact, `tool` |
| `order_started` | otevře stránku zahájení objednávky (`/farm/orders/start`: vlastní soubor, karta designéra, inspirace, host po přihlášení) | `Farm\OrderController::start` | `kind` file / card / inspiration / calculation, `tool` |
| `checkout_started` | stiskne „Zaplatit z kreditu“ (`pay`) nebo „Zaplatit kartou“ (`checkout`) | `Farm\OrderController` | `order`, `kind` credit / card |
| `order_paid` (doplněno) | banka potvrdila platbu kartou a objednávka se z ní zaplatila | `OrderFlow::payFromCard` → `Track::paidByCard` | jako dosud + `kind` = card |
| `failed` | chyba v kroku: model se nezpracoval, generování selhalo, výpočet selhal, platbu farma odmítla nebo brána neodpověděla, stažení neprošlo | `measure`/`measured`/`GenerationController::show`, `OrderController` (`pay`, `checkout`), `GuardModelFile` | `step` model / generate / calculation / checkout / download, `code` (důvod nebo HTTP status), `kind`, `tool`, `order` |

- **Jednou na předmět a jen pro vlastníka** (`Track::outcome`): prohlížeč se ptá každé 3 s a odkaz na kalkulaci
  může otevřít kdokoli. Událost vznikne jen v session nebo účtu, který model či výpočet vlastní, a jen jednou.
- **Platba kartou** se zapíše do návštěvy, která objednávku založila (podle `order_created` s tímtéž `order`).
  Webhook není požadavek návštěvníka. Objednávka bez `order_created` (staff, testovací průchod) se nezapíše.
  Druhé potvrzení banky ani dřívější platba z kreditu nic nezdvojí.
- `order_paid` z kreditu má nově `kind = credit`.
- **Odhad ceny** se dnes nikomu neukazuje (při vypnutém tržišti `CalculationController::describe` ceny z odhadu
  maže, nástroje odhad nemají od 16. 10.), proto pro něj událost není. Kdyby se vrátil, patří k němu
  `price_shown` s `kind = rough`.
- **Host → objednávka**: farma je pro hosty zavřená (`farm.public` false), tlačítko „Vytisknout“ host nevidí.
  Cesta „host → požadavek na přihlášení“ dnes neexistuje, a proto se neměří. Host, který se přihlásí
  a pokračuje, se ukáže jako `order_started` s `kind = calculation`.
- GA4 a Meta nové typy nedostávají (nejsou v `Track::SHARED`). Naše čísla jsou v `events`.

**Co událost nesmí nést:** `Track::clean` pustí do `meta` jen klíče z `Track::META` (kind, tool, value, currency,
order, results, event_id, path, printer, step, code a značky bot/staff/js/act). Hodnoty smí být čísla, true/false,
kódy `[A-Za-z0-9_.:-]{1,64}` a u `path` naše maskovaná adresa. Cokoli jiného (text, e-mail, pole, adresa
odjinud) se zahodí dřív, než se řádek zapíše. Platí to i pro dosavadní události. Jediná dřívější díra byl
`printer` u stažení: bral se volně z adresy, teď projde jen jako kód. Fotky, hesla, texty zadání, poznámky
k objednávce, adresy z QR ani názvy souborů se do `events` nedostanou. Test to kontroluje (§5).

## 3. Dělení a vyhodnocení (`/admin/stats` → „Měření podle auditu“)

- **Nová cesta „Objednávka (audit)“**: návštěva → model hotový → viděl přesnou cenu → začal objednávku
  (`order_started` nebo `order_created`) → šel zaplatit → zaplatil (potvrzeno serverem). U každého kroku je počet
  a podíl z předchozího, takže je vidět, kde lidé odcházejí. Karta designéra cestu zkracuje (nemá vlastní model
  ani cenu). Taková objednávka se ukáže v „začal objednávku“ a „zaplatil“, ale ne v krocích před tím.
- **Podíly s velikostí vzorku n**: návštěvníci s cenou (z všech návštěvníků), zahájené objednávky zaplacené,
  úspěšná stažení (stažení / pokusy o stažení).
- **Chyby v kroku**: kolik návštěvníků krok zkusilo (n) a kolik z nich narazilo na chybu (model, výpočet, platba,
  stažení).
- **Tabulky podle zařízení, nový / vracející se a vstupní cesty**, v každé n, model, cena, začali objednávku,
  zaplatili, stáhli a s chybou.
  - **Zařízení**: z User-Agentu, se kterým anonymní session začala (`anonymous_sessions.user_agent`). Mobil =
    telefon nebo tablet (Mobi, Android, iPhone, iPad…), jinak počítač. Bez User-Agentu je to „neznámé“.
  - **Vracející se**: prohlížeč měl návštěvu už před obdobím, nebo v období víc návštěv (víc session
    prohlížeče). Jinak je nový.
  - **Vstupní cesta**: první věc, kterou návštěvník udělal směrem k modelu: nahrál soubor, fotka (generování
    z obrázku), text (generování z textu), nástroj (výpočet, stažení nebo objednávka výstupu nástroje), model
    (stažení nebo objednávka hotového modelu). Kdo nic z toho neudělal, má cestu podle první stránky nástroje
    nebo modelu, kterou viděl. Jinak „nic z toho“.
- Filtry stránky (zdroj, jazyk, nástroj) platí i pro tyto tabulky.

**Od kdy a s jakou jistotou:** nové události vznikají od nasazení této větve. Starší návštěvy je nemají, takže
cesta „Objednávka (audit)“ a podíly mají smysl jen pro období po nasazení. Lidí je dnes 6 až 12 denně
(docs/O.md). Podíl návštěvníků s cenou (n = všichni) bude za dva týdny z asi 100 až 170 návštěvníků, tedy
s nejistotou kolem ±7 až 10 procentních bodů. Zahájených objednávek je zatím jen pár za týden (farma je pro hosty
zavřená), takže „zahájené → zaplacené“ potřebuje měsíce, než z něj půjde něco vyvozovat. Dokud je n pod 30,
jde o jednotlivé případy, ne o konverzi.

## 4. TODO #10: naše průchody se nepočítají

Značka: **cookie `mp_test=1`** nebo **hlavička `X-Matplace-Test`** (jakákoli hodnota). `Track::test()` ji
pozná a `Track::staff()` pak s prohlížečem zachází jako s adminovým. Prohlížeč se zapamatuje
(`anonymous_sessions.staff`), od té chvíle nezapíše nic, ani bez značky, a co udělal za posledních 30 dní,
dostane `meta.staff`. Z „lidí“ (docs/O.md), z přehledu, z cest i z týdenního e-mailu tím zmizí. Cookie se
nešifruje (`bootstrap/app.php`), aby šla nastavit ručně.

- **Skripty v repu** nastavují cookie: `scripts/review_round2.mjs` (CDP `Network.setCookie`) a
  `scripts/check_preview_colors.mjs` (`page.setCookie`).
- **Skripty řídící session v jejím scratchpadu** (`check_boxes.mjs`, `check_home.mjs`, `check_hollow.mjs`…)
  nejsou v repu. Stačí do nich po `newPage()` přidat
  `await page.setCookie({ name: 'mp_test', value: '1', url: BASE });`.
- **Ručně v prohlížeči** na matplace.com do konzole (F12):
  `document.cookie = "mp_test=1; path=/; max-age=31536000; secure; samesite=lax"`. Platí rok pro ten prohlížeč.
  Zrušit: totéž s `max-age=0`. Prohlížeč ale zůstane označený jako náš, dokud nesmažete i cookie `mp_sid`.
- **Rozšíření prohlížeče** (např. ModHeader): hlavička `X-Matplace-Test: 1`, **jen pro doménu matplace.com**.
  Vlastní hlavička na cizí adresy (mapy, Google) by tam mohla rozbít CORS.
- **curl / API skripty**: `-b mp_test=1` nebo `-H 'X-Matplace-Test: 1'`.

Proč na tom záleží: headless Chrome z puppeteeru má `navigator.webdriver`, takže stránku nepotvrdí
(`/api/seen` neodejde). Jakmile ale nahraje model nebo spočítá cenu, potvrdí návštěvu akcí (`act`) a počítal se
jako člověk. 13. 10. tak bylo 98 „lidí“, většinou průchody session B.

## 5. Testy a ověření

- `tests/Feature/AuditEventsTest.php` (5 testů):
  - **Celá cesta kartou**: nahrání → `model_ready` jednou, ani při opakovaném dotazu ne podruhé → výpočet →
    `price_shown` jednou, cizí otevření odkazu nic nepřidá → objednávka → `checkout_started` (card) → návrat
    z brány nic nezaplatí → webhook dvakrát → jedno `order_paid` (card) v návštěvě zákazníka. Trychtýř
    „Objednávka“ má 1 v každém kroku. Všechny události mají jen klíče z `Track::META` a hodnoty, které
    `Track::clean` nemění. Název souboru, poznámka k objednávce ani e-mail v `events` nejsou.
  - **Zaplaceno z kreditu**: `checkout_started` a `order_paid` mají `kind = credit`.
  - **Testovací průchod**: cookie i hlavička nezapíší nic. Prohlížeč, který se počítal a pak poslal značku,
    z čísel zmizí.
  - **`Track::clean`**: zahodí text, e-mail, pole a HTML, nechá čísla, kódy a cestu.
  - **Dělení**: mobil / počítač, nový / vracející se, vstupní cesta, podíly s n, chyby v krocích a stránka
    `/admin/stats` se vykreslí.
- Celá sada (`php -d memory_limit=2G vendor/bin/phpunit`; `artisan test` při 128 MB spadne na paměti): 664 testů,
  634 prošlo, 30 selhalo. Těch 30 selže stejně i na `main` 10245aa (porovnáno na stejných třídách, 30 = 30):
  503 „nástroj není dostupný“ a stránky nástrojů bez enginu, protože tenhle worktree nemá v `.env` `PYTHON_BIN`.
  Nové testy, `StatsTest`, `FarmCardPaymentTest` a `FarmOrderFlowTest` prošly. `pint --dirty` je bez nálezů,
  `npx tsc --noEmit -p .` bez chyb a `npm run build` proběhl v pořádku.

## 6. Nasazení a co zbývá

- Bez migrace a bez nové konfigurace. Po nasazení stačí na produkci ověřit, že průchod s `mp_test=1` nepřidá
  řádek do `events` (např. `curl -b mp_test=1 https://matplace.com/tools/vase` a počet `visit` za minutu)
  a že `/admin/stats` ukazuje „Měření podle auditu“.
- **Mimo tuto větev (platby, nesahala jsem na ně):** při placení kartou se konverze neposílá do Meta Conversions
  API (`SocialPublisher::conversion` je jen v `payFromRequest`) a prohlížeč po návratu z brány nedostane
  `order_paid` pro GA4 a pixel. Naše `events` už ji mají. Jestli ji mají dostat i GA a Meta, je to rozhodnutí pro
  řídící session.
- Vyhodnocení podle auditu: první čtení dva týdny po nasazení, s n u každého čísla. Závěry o konverzi až po
  týdnech až měsících (§3).
