# Prodejní cesta, část web (katalog, kalkulace, nástroje, SEO, měření) – session 3

Zadal Roman 25. 10. 2026 večer (celé zadání je v `docs/prompts/prodejni-cesta.md`, body 1, 3 až 11 a 13). Tohle je tvoje
část. Session 2 dělá stránku zakázky, platbu a texty farmy (`docs/prompts/prodejni-cesta-farma.md`), řídící dělá hlavní
stránku a bezpečnostní hlavičky. Piš česky, krátce, bez pomlček.

**Hlavní cíl:** víc dokončených objednávek tisku. Nic nového bez vazby na tento cíl. Nic nemaž a nevypínej, dokud
neověříš, že to nepoužívá jiná část webu.

## Pracoviště

- Worktree `C:\matplace-edit-wt`, nová větev `feature/sales-path-web` z `origin/main` (3219dee nebo novější, nejdřív
  `git fetch origin`). Nemerguješ ani nenasazuješ, to dělá řídící (conceprt19092026-91). Po dokončení push větve a zpráva
  řídící session přes `SendMessage` (stačí jedna, až bude hotovo; dílčí push klidně dřív, když je něco samostatně nasaditelné).
- Deník do `docs/AN.md` (nový soubor), stejně stručně jako AH.md: co, proč, čím ověřeno, co zůstalo.
- Texty jen v `lang/{cs,en,es}/*.php`, nebo přes `lang/src/<soubor>.json` + `python scripts/lang_add.py <soubor>`; každý
  JSON, kterého se dotkneš, ověř `python -c "import json; json.load(open(...))"`. Před pushem `vendor/bin/pint --dirty`,
  `npx tsc --noEmit -p .`, `npm run build`, testy (`php -d memory_limit=2G vendor/bin/phpunit --filter …`, plná sada
  na pozadí, nikdy dvě sady naráz, během běhu neupravuj soubory).
- Nedotýkej se: `resources/views/calculator/home.blade.php` a `lang/*/home.php` (řídící), `lang/*/farm.php`,
  `resources/js/calc/farm.ts`, `app/Http/Controllers/Farm/*`, `app/Domain/Farm/*` (session 2), nginx a serveru.

## Co řídící ověřila na produkci 25. 10. (abys to nemusela znovu)

- `/models` (modely designérů, `ModelCatalogController`, `DesignerModel::printable()`) je prázdný: v DB je 0 karet.
  V menu je odkaz schovaný do 6 modelů (`$showModels`, Z.md §7), ale `/model` (Inspirace) na něj odkazuje v úvodní větě
  (`models.inspiration.lead` + `models.printable_link` „modelů k tisku“, `resources/views/inspiration/index.blade.php:11`).
  Stránka `/models` nemá `noindex`, v sitemapě není (sitemapa = nástroje, blog, katalog inspirace).
- Stránkování na `/model`, `/models` a dalších výpisech je anglicky („Showing 1 to 48 of …“, „Previous“, „Next“, aria
  „Pagination Navigation“): chybí `lang/{cs,en,es}/pagination.php` a klíče JSON `Showing`, `to`, `of`, `results`,
  `Pagination Navigation`, `Go to page :page` (Laravel výchozí `pagination::tailwind`). Platí i pro en a es
  (`/es/model` ukazuje „Showing“).
- `/tools` má hledání (`#tool-search`, bez diakritiky přes `normalize('NFD')`, synonyma `tools.synonyms`, slova karty
  `data-words` = název + hint + `tools.keywords.<klíč>`) a chipy podle kategorií `tools.cats` (images, names, home, parts,
  toys, signs, craft, edit, sell). Záměry (dárek, fotka, domácnost, díl, firma) tam nejsou. 58 veřejných nástrojů
  (sitemapa). Stránka má 321 kB HTML.
- SEO: `/tools` „Nástroje · matplace“ / „Za minutu vidíš model, cenu a dobu výroby. Bez registrace.“ (tykání, zbytek
  webu vyká); `/model` „Inspirace · matplace“ / „Tisíce 3D modelů z Printables, MakerWorldu…“; `/models` „Modely
  k tisku · matplace“. Canonical a hreflang (7 výskytů) jsou v pořádku, 404 vrací 404.
- Kalkulace ukazuje „Orientační cena tisku u nás“ bez dopravy; doprava se objeví až na stránce zakázky po volbě doručení
  (AH.md §9.3). Ceny dopravy: `config/farm.php` `shipping` (CZ Zásilkovna 99 Kč / domů 149 Kč, SK od 149 Kč, EU od
  199 Kč, v eurech vedle; osobní odběr zdarma); blok „Než objednáte“ pod nástroji je už čte v měně návštěvníka
  (`App\Support\LandingPage`, AH.md §11.1).
- Platba ve skutečnosti: z kreditu (`pay`), nebo kartou (`checkout` = Stripe na přesně chybějící částku zaokrouhlenou
  nahoru na celé koruny či eura, připíše se jako kredit a hned se zablokuje pro zakázku; drobný zbytek zůstane jako kredit).
  Texty si odporují: `pages.php` „Jak to funguje“ krok 2 „zaplaťte z kreditu“, FAQ „Tisky se platí z předplaceného
  kreditu, který si dobijete kartou“ proti `farm.cta_hint` „kreditem nebo kartou“. Jednotné znění je níže.
- Farma je veřejná (`farm.public` = true, host vidí „Vytisknout u nás“). Čísla: 223 zakázek, 104 zaplacených
  (57 od adminů, 47 od 5 lidí), 45 dotištěných + 58 předaných, 70 účtů, 31 zveřejněných videí z farmy na YouTube
  (`FarmVideo` status published), 0 modelů designérů, 0 kolekcí, žádné recenze. Fotky výtisků zatím nejsou
  (Roman dluží 30 fotek, `docs/FOTKY-BLOG.md`), `SITE_EXAMPLES` je vypnuté, čísla příkladů jsou z produkce (AH.md §9.3, §11.1).
- Měření (`App\Support\Track`, první strana, server): `upload`, `calculation`, `price_shown`, `model_ready`,
  `download`, `order_started` (klik Vytisknout = POST /farm/orders), `checkout_started` (kind card), `order_created`,
  `order_paid` (i kartou, `Track::paidByCard`), `register` (dokončená), `failed` se `step` download, checkout, calculation,
  model, generate. Chybí: začátek a chyba nahrání, začátek registrace, dobití kreditu (dělá session 2). GA4 a pixel se
  načtou jen se souhlasem (`Consent::allows`, `partials/measure.blade.php`); vlastní události jdou do GA přes
  `Track::forBrowser`. Trychtýř je v `/admin/stats` (`Funnel`, `admin.stats.funnel`).

## Úkoly (v tomhle pořadí, nejvyšší dopad a nejmenší riziko první)

1. **Slepá cesta `/models`.** Dokud je karet méně než práh z menu (dnes 6, jedno místo pravdy, dnes `$showModels`
   v layoutu: udělej z toho pomocníka, třeba `DesignerModel::enoughToShow()` s keší, a použij ho všude): bez odkazu
   v úvodu Inspirace, `noindex` na `/models` (robots meta, canonical nech), sitemapa dál bez něj. Prázdný stav stránky
   musí vést dál: Inspirace (`/model`), nahrání vlastního souboru (`/`), nástroje (`/tools`), ne jen větu. Jakmile karty
   jsou, všechno se samo vrátí (test obou stavů). Stránku ani cestu neruš, designéři mají `/designer/models`.
2. **Stránkování česky, anglicky a španělsky.** `lang/{cs,en,es}/pagination.php` (previous, next) a JSON klíče přes
   `lang/src/pagination.json` + `lang_add.py`. Ověř na `/model`, `/models`, účtu a farmě (`farm/orders`, `farm/credit`).
3. **Orientační cena s dopravou dřív.** V kalkulaci u „Orientační cena tisku u nás“ přidej řádek s dopravou podle země
   návštěvníka (jazyk a měna, jako `LandingPage`): „+ doprava od 99 Kč (Zásilkovna) nebo odběr zdarma“, u eur v eurech.
   Jasně odhad, ne konečná cena. Výpočet ceny tisku neměň. Čísla ber z `config/farm.php` přes stávající pomocníka,
   neopisuj je. Stránka zakázky je session 2.
4. **Nástroje: záměry a hledání.** Nad chipy kategorií (nebo místo prvního řádku) záměry: Dárek, Z fotky, Domácnost,
   Náhradní díl, Pro firmu. Mapování dej do `config/tools.php` (nový klíč `intents`, ne do šablony):
   dárek = kategorie names + `/tools/gifts`; z fotky = nástroje, které berou fotku (figure, pet, relief, papel,
   filament_art, mosaic, compose, colors, stencil, logo, cookie…, ověř v konfiguraci každého); domácnost = home;
   náhradní díl = parts + repair, check, calc (vlastní soubor); pro firmu = qr, logo, badge, sign, nameplate, stencil,
   stamp, coaster, keychain, lightbox, text. Hledání: doplň `tools.keywords` a `tools.synonyms` o účely (dárek k
   narozeninám, pro kolegu, do kanceláře, visačka na klíče, náhradní knoflík…), ve všech třech jazycích. Diakritika už
   funguje, ověř to testem. Kategorie, sbalení na telefonu a chipy v jednom řádku (AF.md §2) zachovej.
   Pokud se dá snadno srazit 321 kB HTML (třeba `data-words` jen jednou pro kartu i řádek telefonu), udělej to, ale až
   po záměrech.
5. **Důvěra v prodejní cestě, jen pravdivé věci.** Pod kalkulací (nebo v kartě „Tisk u nás“) blok s tím, co máme:
   a) čísla farmy z `App\Domain\Farm\PublicStats::summary()` (dělá session 2; rozhraní níže; dokud není, ukaž blok jen
   když třída existuje, `class_exists`), b) jedno zveřejněné video z farmy (YouTube nocookie, jen `FarmVideo` status
   published, titulek z DB, bez autoplay, náhled obrázkem a načtení až po kliknutí kvůli rychlosti), c) příklady s cenou
   z `calculator/_examples` (čísla jsou ověřená, u obrázků zůstává štítek „Náhled modelu, ne fotka výtisku“) s odkazem
   na kalkulaci nebo nástroj, odkud se jde rovnou dál. Recenze žádné nejsou, nepiš je. Fotky výtisků nejsou, nevkládej
   jiné. Blok nesmí odsunout cenu a tlačítko na telefonu (AH.md §9.1: cena první).
6. **Texty.** `pages.php` „Jak to funguje“ krok 2 a 3, FAQ „Jak se platí a co je kredit?“, slovník kolem registrace
   a dobíjení na webových stránkách na jednotné znění (níže), cs/en/es. Odstraň opakování: u každého kroku jedna věta,
   co člověk udělá dál a zda už něco platí. `/tools` popis a titulek do vykání jako zbytek webu. Test chybějících klíčů
   (`AuditRestTest`) rozšiř o nové šablony.
7. **SEO titulky a popisy** `/tools`, `/model`, `/models` a jazykových verzí: přesně obsah a záměr návštěvníka, přirozená
   věta, žádný výčet klíčových slov. Návrh: `/tools` „Nástroje pro 3D tisk: model z fotky, jména, QR nebo náhradní díl
   za minutu · matplace“ / „58 nástrojů zdarma: z fotky, jména, loga nebo rozměrů vznikne model, hned vidíte cenu tisku
   a můžete ho stáhnout nebo objednat.“ Číslo počítej z konfigurace, ne natvrdo. Podobně Inspirace („3D modely k tisku
   z Printables a MakerWorldu na jednom místě…“). Ověř délky (titulek do 60 znaků, popis do 155) a Open Graph.
8. **Kurátorské vstupní stránky jen s obsahem.** Posuď tři záměry: dárky se jménem (dnes `/tools/gifts`, zkontroluj, že
   kryje jména a je propojená ze záměru „Dárek“), náhradní díly na míru (parts jsou 4 nástroje + nahrání vlastního
   souboru + poptávka `spare` jen s marketplace), 3D tisk pro firmy (qr, logo, badge, cedulky, podtácky, klíčenky,
   faktura s DPH, firemní údaje z `pages.php`). Stránku vytvoř jen tam, kde je aspoň 6 skutečných nástrojů nebo modelů
   a konkrétní obsah (ceny z produkce, postup objednání, doprava); jinak jen filtr záměru na `/tools`. Nová stránka:
   canonical, hreflang, do sitemapy jen indexovatelná, FAQPage JSON-LD jen z toho, co stránka ukazuje. Žádná tenká stránka.
9. **Měření trychtýře.** Doplň začátek nahrání a chybu nahrání (prohlížeč pošle událost přes malý endpoint s omezením,
   nebo server zaloguje `failed` se `step` upload u odmítnutého požadavku: validace, velikost, nečitelný soubor), začátek
   registrace (návštěva `/register` už je ve `visit`; stačí ji ve trychtýři ukázat jako krok), dokončenou kalkulaci (je),
   klik Vytisknout (`order_started`, je), dobití kreditu a platbu (session 2 přidá `topup_started` a `topup`, ty je ukaž).
   Trychtýř v `/admin/stats` ať má kroky: nahrání začalo → nahráno → kalkulace → cena ukázána → Vytisknout → registrace →
   zakázka → platba začala → zaplaceno. Bez obsahu modelů, bez osobních údajů, se souhlasem jen to, co jde do GA.

## Jednotné znění o platbě (cs; en a es přelož věrně, session 2 použije totéž na stránce zakázky)

- Krátce: „Platíte až na konci objednávky, kartou nebo z kreditu.“
- Dlouze (FAQ, Jak to funguje): „Cenu znáte před zaplacením. Zaplatíte kartou (Stripe) nebo z kreditu na účtu. Kartou
  platíte přesně cenu zakázky zaokrouhlenou nahoru na celé koruny nebo eura; pár haléřů navíc zůstane jako kredit na
  další tisk. Kredit si můžete dobít i předem (100 až 20 000 Kč, 5 až 800 €). Částka se při zaplacení zablokuje a strhne
  až po dotisknutí; když tisk nezačne nebo se nepovede naší vinou, vrátí se celá.“

## Rozhraní `App\Domain\Farm\PublicStats` (dělá session 2, ty jen čteš)

```php
PublicStats::summary(): array{printed: int, handed_over_days_median: ?float, last_30_days_printed: int, videos_published: int}
```
Keš 1 h, jen zakázky s `paid_at`. Dokud třída není, blok se neukáže.

## Ověření (před pushem, zapiš do AN.md)

- Scénáře: nahrání modelu (kostka 20 mm), výpočet ceny a nový řádek s dopravou v cs/en/es a v eurech, stažení modelu,
  výběr nástroje přes záměr i hledání s diakritikou a bez („krabička“, „krabicka“, „dárek pro kolegu“), vytvoření modelu
  z nástroje, `/model` ↔ `/models` prázdný i naplněný stav (factory karty), stránkování ve 3 jazycích, 390 px i 1280 px.
- `node scripts/review_round2.mjs` nebo `scripts/check_preview_colors.mjs` použij, kde se hodí; headless Chrome na
  produkci s cookie `mp_test=1`.
- Sitemapa a strukturovaná data po změnách (`/sitemap.xml`, JSON-LD validní, žádný `noindex` v sitemapě).
- Plná sada testů zelená, `npm run build` čisté.

## Výstup pro řídící (zpráva + AN.md)

Seznam změněných souborů, co se skutečně změnilo, jak byly scénáře otestovány, výsledky testů a buildu, co zůstalo
nevyřešené. Odděl implementované změny od dalších doporučení. Nic nehlásit jako hotové bez ověření.
