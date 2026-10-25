# AN · Prodejní cesta, část web (session 3, 25.–26. 10. 2026)

Zadání `docs/prompts/prodejni-cesta-web.md` (Roman 25. 10.; celé zadání `docs/prompts/prodejni-cesta.md`). Větev
`feature/sales-path-web` z `origin/main` bf112cb, worktree `C:\matplace-edit-wt`. Cíl: víc dokončených objednávek tisku.
Nic nenasazeno, nic nemergováno; stránka zakázky, platba a texty farmy jsou session 2, hlavní stránka řídící.

## 1. Slepá cesta `/models`

Jedno místo pravdy: `DesignerModel::enoughToShow()` (práh `config/catalog.php` → `menu_from` = 6, keš 10 min pod
`nav.models` jako dřív). Používá ho menu (`layouts/app.blade.php`), úvod Inspirace (`inspiration/index.blade.php`: bez
karet jen dvě věty `models.inspiration.lead_alone`, s kartami věta s odkazem jako dřív), stránka `/models`
(`robots noindex, follow`; hreflang zůstává, canonical ne, stejně jako u stránek Inspirace bez vlastního textu, které tak
čeká `CatalogSeoTest`) a sitemapa (`Sitemaps::pages` přidá `models.index` až s kartami). Prázdný stav `/models` vede
dál: vlastní soubor (`/`, kalkulace), Inspirace (`/model`), nástroje (`/tools`). Stránka ani cesta nezrušena.

## 2. Stránkování ve třech jazycích

`lang/{cs,en,es}/pagination.php` (previous, next) a JSON klíče `Showing`, `to`, `of`, `results`, `Pagination
Navigation`, `Go to page :page` přes `lang/src/pagination.json`. Výchozí `pagination::tailwind` Laravelu zůstává;
test vykreslí `LengthAwarePaginator` ve třech jazycích („Zobrazeno 49 až 96 z 100 výsledků“, „Mostrando … de …“).

## 3. Orientační cena s dopravou

`App\Support\ShippingHint::text()`: z `config/farm.php` `shipping.zones` v měně návštěvníka, pro zemi, kam nejspíš
objedná (země účtu; jinak koruny = CZ, španělština = ES, jinak nejlevnější zóna v zahraničí = SK), výdejní místo nebo
domů, co je levnější; „osobní odběr zdarma“ jen když ho farma nabízí (`Shipping::pickup()`). Nic, dokud farma není
návštěvníkovi nabízena (`FarmGate::offered`, jako tlačítko). Server větu složí (`calc.price.shipping[_pickup]`,
cs/en/es), `ConfigController::payload` ji dá do `MP_CONFIG.shipping_hint`, `calculator.ts` ji ukáže pod „Orientační cena
tisku u nás“ (`#price-shipping`) u odhadu i u přesného výsledku. Výpočet ceny tisku beze změny. Příklad cs: „+ doprava
od 99 Kč (Zásilkovna), přesně podle doručení zvoleného v objednávce.“; en/euro: „+ delivery from €5.90 (Packeta)…“.

## 4. Nástroje: záměry a hledání

`config/tool_intents.php` (nový soubor, ne klíč v `config/tools.php`: každý klíč tam čte patnáct míst jako nástroj)
+ `App\Domain\Tools\ToolIntents`: dárek = kategorie names + `gifts`; z fotky = nástroje, které berou fotku nebo obrázek
(`ToolInput` photo, picture, text_or_picture; ověřeno z konfigurace, 29 nástrojů); domácnost = home; náhradní díl =
parts + repair, check, calc (+ spare, dnes vypnutý); pro firmu = qr, logo, badge, sign, nameplate, stencil, stamp,
coaster, keychain, lightbox, text (11). Řádek záměrů nad kategoriemi (`tools.aims`, `tools.aim.*`), jeden filtr pro
stránku: záměr nebo kategorie, ne obojí; v adrese jako `#gift`. Kategorie, sbalení na telefonu a chipy v řádku
zachovány. Hledání: `tools.keywords` doplněny o účely u 22 nástrojů (kolega, kancelář, firma, narozeniny, visačka na
klíče, náhradní knoflík, dárek z fotky…), `tools.synonyms` o pět skupin (kolega, firma, příležitosti, náhradní díl,
rodina) ve třech jazycích. HTML: `data-words` jen na velké kartě, řádek telefonu je hledá podle klíče (−13 kB);
stránka má lokálně 326 kB, z toho 182 obrázků `<picture>` se srcset (každý nástroj dvakrát: mřížka a řádek telefonu)
81 kB + 55 kB SVG, to je další páka (níže).

## 5. Důvěra jen z pravdivých věcí

`calculator/_trust.blade.php` na konci pravého sloupce kalkulace (na telefonu až za cenou a tlačítky): a) čísla farmy
z `App\Domain\Farm\PublicStats::summary()` jen když třída existuje (session 2, feature/sales-path-farm 56461b0; do
sloučení se čísla neukážou, test má zástupnou třídu); b) poslední zveřejněné video (`FarmVideo` published, titulek
z DB), YouTube nocookie přehrávač až po kliknutí, před ním nic z YouTube (ani náhled; po kliknutí autoplay jako na
stránkách nástrojů); c) ceny skutečných kalkulací z `config/site.php example_items` jako řádky bez obrázků (fotky
výtisků nejsou), odkaz na kalkulaci; v eurech se žádná cena nevymýšlí. Bez recenzí. Blok jen když je farma nabízena.

## 6. Texty

`pages.php` (cs/en/es) „Co děláme“, „Jak to funguje“ (krok 1 „zatím nic neplatíte“, krok 2 „bez registrace a bez
placení“, krok 3 „až na konci objednávky zaplatíte kartou nebo z kreditu“), „Tiskneme sami“, FAQ „Jak se platí a co je
kredit?“ v jednotném znění ze zadání (+ věta o dobírce), „Co když tisk nezačne“ a „Mohu zrušit“ mluví o „částce“, ne
o kreditu. **Obchodní podmínky (`pages.terms`, `business_terms`) jsem nechal**: mluví o platbě jen z kreditu
(§ platba), změna textu smlouvy je na Romanovi (doporučení níže). `/tools` lead už vykal; tykání bylo jen ve výchozím
popisu `app.subline`, který stránka `/tools` teď nepoužívá (§7). `AuditRestTest` scan klíčů rozšířen o
`tools/index`, `tools/card`, `tools/card_row`, `models/index`, `inspiration/index`.

## 7. SEO titulky a popisy

| stránka | titulek (cs) | popis (cs) |
|---|---|---|
| `/tools` | Nástroje pro 3D tisk: z fotky, jména či QR · matplace (53) | „58 nástrojů zdarma: z fotky, jména, loga nebo rozměrů vznikne model, hned vidíte cenu tisku a můžete ho stáhnout nebo objednat.“ (číslo z konfigurace) |
| `/model` | 3D modely k tisku z Printables a MakerWorldu · matplace (55) | stávající |
| `/models` | Modely od designérů k tisku u nás · matplace (54) | stávající |
| `/business` | 3D tisk pro firmy: QR cedulky, loga, visačky · matplace (55) | „Malé série pro firmy…“ |

Klíče `tools.seo.title/description`, `models.seo_title`, `models.inspiration.seo_title` (h1 zůstávají krátké);
en/es stejně. Test ověřuje délky (≤ 60 / ≤ 155) a Open Graph na všech čtyřech stránkách ve třech jazycích.

## 8. Kurátorské stránky

- Dárky se jménem: `/tools/gifts` kryje jména (kategorie names) a je v záměru „Dárek“ (karta mezi prvními). Bez nové stránky.
- Náhradní díly: 4 nástroje + nahrání + poptávka jen s marketplace → jen záměr „Náhradní díl“ na `/tools`.
- 3D tisk pro firmy: 11 nástrojů → stránka `/business` (`PageController::PAGES`, texty `pages.business` cs/en/es):
  nástroje jako karty, ceny ze skutečných kalkulací v měně návštěvníka, postup objednání v jednotném znění, doprava
  z `config/farm.php` (`:cz_point` …, `LandingPage::numbers()` dosazuje `PageController`), kdo tiskne (matplace s.r.o.,
  IČO), čtyři otázky = FAQPage JSON-LD. Canonical a hreflang z layoutu, v sitemapě (`PageController::PAGES`), odkaz ze
  záměru „Pro firmu“ na `/tools`. Route se jmenuje `tools.business` (`PageController::routeName`): hlavní stránka řídící
  na ni přepne odkaz „Pro firmu“ sama, jakmile existuje. Šablona `pages/static.blade.php` umí v sekci `tools` a `prices`, `h1` zvlášť od titulku.

## 9. Měření trychtýře

- Odmítnuté nahrání: server zapíše `failed` se `step` = `upload` a `kind` = `too_big` | `missing` | `format`
  (`UploadController::store`); prohlížeč totéž pro soubor odmítnutý před nahráním (formát, velikost) přes
  `POST /api/refused` (`RefusedController`, throttle 30/min, přijme jen step `upload` a dva kódy, nic o souboru).
  Chyba sítě při nahrání se nehlásí (server ji nevidí a prohlížeč by zdvojil chyby validace).
- `Funnel`: nová cesta `sales`: návštěva → nahrání začalo (`upload` | `failed:upload`) → nahráno → kalkulace → cena
  ukázána → Vytisknout (`order_started`) → registrace (`register` | stránka `/register` ve `visit`/`page` | zakázka
  založená s účtem) → zakázka → platba začala (`checkout_started` | `topup_started`) → zaplaceno. Chyby: nový krok
  `upload`, `checkout` počítá i `failed:pay` (session 2); podíl „dobití dokončená“ (`topup_started` → `topup`).
  `/admin/stats` cestu ukazuje první, s názvy kroků.

## 10. Ověření (26. 10., lokálně, dev server 8013, `scripts/review_round2.mjs`, 390 i 1280 px)

| scénář | jak | výsledek |
|---|---|---|
| nahrání kostky 20 mm, cena, nový řádek s dopravou | test `SalesPathWebTest` (cs „99 Kč“, euro „5,90“, es „9,90“, bez farmy nic) | zelené; lokálně se řádek neukáže, farma tu není veřejná |
| `/tools` hledání „krabička“ i „krabicka“, „dárek pro kolegu“ | `--then` vepsání do pole, snímek | krabička: Krabička na míru + organizér (+ světelný nápis přes synonymum box); kolegu: busta, klíčenka, podtácek, dárek se jménem |
| záměr „Pro firmu“ | klik, snímek | 11 karet, odkaz na `/business` |
| `/business`, `/models`, `/c/<token>` | snímky | bez vodorovného posuvu, bez chyb JS; malé cíle jen stávající chipy řazení na `/models` |
| `/model` ↔ `/models` prázdný i naplněný stav | test (keš `nav.models`) | zelené |
| stránkování cs/en/es | test | zelené |
| sitemapa, JSON-LD | test (`matplace:sitemap`, FAQPage) | zelené |
| stažení modelu, model z nástroje | beze změny v této větvi (netknuto) | neověřováno znovu |

Testy: `SalesPathWebTest` 8 testů; spolu s `WebRound2Test`, `CatalogSeoTest`, `ToolPageTest`, `AuditRestTest`,
`SeoTest`, `LandingPagesTest`, `StatsTest`, `AuditEventsTest` zelené; pint, `tsc`, `npm run build` čisté. Plná sada:
viz konec.

## 11. Co zůstalo a doporučení

- Čísla farmy v bloku důvěry se ukážou až po sloučení `PublicStats` (session 2).
- Obchodní podmínky stále říkají „platí se z kreditu“; sjednocení je změna smluvního textu (verze `terms_version`),
  rozhodne Roman.
- `/tools` 326 kB: 182 `<picture>` (každý nástroj dvakrát) = 136 kB; další krok by byl jeden `<img>` bez srcset v řádku
  telefonu, nebo jeden seznam karet přeskládaný CSS.
- Trychtýř: krok „registrace“ je průchozí i pro přihlášené (zakázka založená s účtem), jinak by cesta přihlášeným
  končila; „platba začala“ z kreditu bez dobití nemá vlastní událost, zaplacení z kreditu jde rovnou do `order_paid`.
- Chyba sítě při nahrání se neměří (viz §9).
- Na produkci neověřeno (headless Chrome s `mp_test=1` nebyl spuštěn; stránky se ověřily lokálně a testy).

## 12. Soubory

`config/{catalog,tool_intents}.php`, `app/Models/DesignerModel.php` (`enoughToShow`), `app/Support/{ShippingHint,
Sitemaps,LandingPage}.php`, `app/Domain/Tools/ToolIntents.php`, `app/Domain/Stats/Funnel.php`,
`app/Http/Controllers/{PageController,Api/ConfigController,Api/UploadController,Api/RefusedController}.php`,
`routes/web.php` (`api.refused`), `resources/views/{layouts/app,inspiration/index,models/index,tools/index,
tools/card_row,calculator/index,calculator/_trust,pages/static,admin/stats/funnel}.blade.php`,
`resources/js/calc/calculator.ts`, `lang/{cs,en,es}/{models,tools,pages,pagination}.php`, `lang/src/{pagination,
calc_shipping,calc_trust}.json` → `lang/{cs,en,es}.json`, `tests/Feature/{SalesPathWebTest,AuditRestTest}.php`.
