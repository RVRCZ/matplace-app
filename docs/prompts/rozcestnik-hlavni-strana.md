# Rozcestník na hlavní straně: fotka, text nebo soubor (TODO #78)

Pro session 3, 8. 11. 2026. Roman: „na hlavní straně možnost nahrát 3D soubor, nebo přidat fotku + větší textové okno
pro popis; podle fotky nebo textu nasměrujeme další postup a agent tomu pomůže“. Souvislosti: pět týdnů naživo bez
platícího cizího zákazníka; sázka na dárky z fotky do Vánoc; po nasazení stránku zkouší cizí člověk (Stella) na
telefonu s nahráváním obrazovky, proto musí být hotová a čistá, ne „skoro“.

**Schválený klikací návrh** (Roman 8. 11. večer, texty se mohou měnit kdykoli): soubor
`C:\Users\roman\AppData\Local\Temp\claude\c--Users-roman-Documents-MatPlace-conceprt19092026\ac77c450-d812-4639-8c0c-b3457b294a63\scratchpad\home\rozcestnik.template.html`
(obrázky jako `__IMG_…__`, skutečné soubory vedle v `img/`). Otevři si ho v prohlížeči po sestavení
`python make_data_uris.py && python -c "…"` nebo si čti přímo HTML: rozvržení, pořadí sekcí, texty a chování
(štítky vyplní pole, odeslání ukáže odpověď asistenta) jsou předloha, ne pixelová norma. Barvy, písmo a zaoblení jsou
z našeho Tailwind tématu (`--color-action`, `--color-ink`, `--color-studio`…), nic nového nezaváděj.

Worktree `C:\matplace-edit-wt` (session 3 ho používala, řídící před „spusť“ ověří, že je čistý), nová větev
`feature/home-router` z `origin/main` (na startu `git fetch origin`). Po pushi napiš řídící. Nic nenasazuj, žádný
`migrate*` ve worktree (sdílená dev.sqlite, testy běží v paměti, migraci jen napiš), git add jen jménem, žádný stash,
commit s `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`. Texty: nové klíče přes `lang/src/home_router.json`
+ `python scripts/lang_add.py lang/src/home_router.json` (cs, en, es); `farm.*` v `lang/{cs,en,es}/farm.php` přímo ve
všech třech. Před změnou tvaru jakéhokoli existujícího klíče grep v `tests/` (ToolPageTest, LandingPagesTest, SeoTest
padají na změně klíčů). Každý JSON, kterého se dotkneš, projdi `json.load` s kontrolou duplicit. PHPUnit po jedné sadě
(`php -d memory_limit=2G vendor/bin/phpunit --filter …`), nikdy dvě naráz. Klíče a `.env` neměň; nový klíč
`ANTHROPIC_INTAKE_MODEL` jen jako `env()` s výchozí hodnotou.

## Co je dnes

- Hlavní strana = `Route::get('/', [CalculatorController::class, 'index'])->name('home')`, pohled
  `resources/views/calculator/index.blade.php`: H1 „Váš nápad vytiskneme ve 3D.“, tlačítko „Nahrát soubor“, pod tím
  „Hotové modely pro“, „Co lidé tisknou nejčastěji“ (nástroje), „Tři kroky“, SEO část „Kalkulačka 3D tisku“ s FAQ.
  Ta SEO část má hodnotu ve vyhledávání (title „Cena 3D tisku online…“), zůstává pod novým vrškem, nemaž ji.
- Fotku dnes umí přijmout jen jednotlivé nástroje na svých stránkách (`/tools/figure` Busta nebo figurka z fotky,
  `/tools/pet-figurine`, `/tools/charm` z obrázku, `/tools/coaster`, `/tools/earrings`, `/tools/papel-picado`,
  `/tools/filament-art`), každý vlastním vstupem v `resources/js/calc/…`.
- Model s viděním už voláme: `app/Engines/Ai/ClaudeAssistant.php` (používá `FilamentFromShop`, `TestPhotoJudge`
  posuzuje fotky, `PrintAdvisor`), modely v `config/ai.php` `kind_models` (`claude-sonnet-5-5`).
- Poptávky a nabídky existují pro tržiště: `app/Domain/Inquiry` (InquiryService, InquiryDispatcher rozesílá
  tiskárnám) a `app/Domain/Quote` (QuoteBuilder, CostSheet, zákaznické `/q/{quote}` přijmout / odmítnout / změnit).
- Generování tvaru z fotky existuje: `app/Domain/Generation/GenerationService` + `app/Engines/Generator/TripoGenerator`
  (figurka mazlíčka), `MeshyGenerator` připravený. V tomhle zadání se nepoužije (fáze 2).
- Měření: události auditu z docs/AJ.md (`mp_test` značka, stránka trychtýře v administraci).
- Hosté: pokladna pro hosty (docs/AO.md) má relaci hosta a limity; `throttle:` middleware na citlivých POSTech.
- HEIC z iPhonu: na serveru je `/opt/matplace-py` s `PIL 12.3` a `pillow_heif 1.8`; lokálně store Python
  (`PYTHON_BIN`) má PIL 13, `pillow_heif` ověř (`python -c "import pillow_heif"`), případně `pip install --user`.

## Co udělat

### A. Stránka

- Nový vršek hlavní strany podle návrhu: eyebrow, H1 „Z fotky vyrobíme dárek. Z nápadu věc.“, podnadpis, řádek
  důvěry (náhled a cena před zaplacením, bez registrace, doručení do Vánoc při objednání do 15. 12.; datum je Romanovo
  rozhodnutí, dej ho do nastavení farmy, ne do kódu), karta vstupu: tlačítko „Vyfotit nebo vybrat fotku“
  (`<input type=file accept="image/*" capture="environment">`, na mobilu otevře fotoaparát), „Nahrát 3D soubor“
  (STL, 3MF, STEP, OBJ, stejné typy jako kalkulačka), náhled vybraného souboru s „odebrat“, textové pole s popiskem
  „Co z toho má být? Pro koho, jak velké, jaké barvy.“ a příklady v placeholderu, čtyři štítky s příklady, velké
  vystředěné tlačítko **„Navrhnout“** a pod ním „Zdarma a bez registrace. Během minuty navrhneme, jak to vyrobit
  a kolik to bude stát.“, věta o smazání fotky po 30 dnech.
- Pod tím: „Po odeslání se stane jedna ze tří věcí“ (Umíme to hned / Vytvoříme tvar podle fotky / Vezme si to
  člověk), „Dárky, které umíme z vaší fotky“ (8 karet: figure, pet-figurine, charm „Přívěsek z obrázku“, medallion,
  map, cake-topper, coaster, earrings; obrázky z `img/tools/*-800.jpg` a `img/tool-examples/*-1.png`, později fotky
  od Stelly přes správu fotek nástrojů z docs/AN.md §15; cena „od“ z nejlevnější varianty nástroje, když ji
  kalkulačka umí dát, jinak zatím skrýt, žádná vymyšlená čísla), pás „Do Vánoc stihneme objednávky přijaté do …“,
  „Takhle to vypadá, když to přijde“ (fotky výtisků `storage/prints/*/photo-web.jpg`, které už nástroj figure ukazuje),
  „Máte vlastní model? Cena ze souboru během minuty“. Zbytek dnešní stránky pod tím beze změny.
- Mobil první (390 px), pak 1280; žádný vodorovný posuv; tlačítko „Navrhnout“ aspoň 60 px vysoké; sticky prvky podle
  `site/sticky.ts` jen pokud už tam jsou. Jazyky cs/en/es (en/es texty věcně stejné, bez vánočního data, když není).

### B. Příjem (intake)

- `POST /intake` (host i přihlášený; `throttle` 5 za hodinu na relaci + IP; CSRF jako jinde). Vstup: fotka (jpg,
  png, webp, heic/heif, do 15 MB; HEIC převést na JPEG přes Python PIL + pillow_heif v `engines/python`, EXIF otočení
  srovnat, delší strana do 2000 px pro model, originál nechat), nebo 3D soubor (předat existujícímu nahrání
  kalkulačky, `ModelFile`), text do 2000 znaků. Bez fotky i souboru musí být text, jinak 422 s větou z návrhu
  („Napište, co má vzniknout, nebo přidejte fotku…“).
- Tabulka `intakes`: veřejný token (24 znaků, v URL), relace hosta / user_id, druh (photo|file|text), cesta k souboru,
  text, jazyk, `route` (tool|shape|human|file), `tool_key`, `params` json, `confidence`, `summary`, `model`, tokeny
  a cena volání, `status` (new|opened|human|closed), časy. Uložení souborů `storage/app/intake/<token>/…`,
  mazání po 30 dnech v existujícím mazacím příkazu (`PruneData` vzor, plánovač už běží; docs/AL.md §16).
- Odpověď JSON pro stránku: `route`, `summary` (věta pro zákazníka v jeho jazyce), `actions` (odkaz na nástroj
  s tokenem / odkaz na kalkulačku / formulář poptávky), `params`.

### C. Rozhodování (asistent)

- `app/Domain/Intake/IntakeRouter`: jedno volání `ClaudeAssistant` (nový druh `intake`, `kind_models['intake']`
  = `env('ANTHROPIC_INTAKE_MODEL', 'claude-sonnet-5-5')`, obrázek jako vstup, časový limit 20 s). Systémový prompt
  nese katalog nástrojů, které berou fotku nebo text (klíč, název, co potřebují, rozsah velikostí, barvy), a pravidla:
  tvář nebo postava → `figure`; zvíře → `pet-figurine`; kresba, logo nebo nápis s úmyslem přívěsek/klíče → `charm`;
  jméno a dort → `cake-topper`; název místa → `map`; medaile → `medallion`; podtácek, náušnice podle slov; náhradní
  díl, rozměry, tolerance, závit, „aby pasovalo“ → `human`; věc bez šablony (soška, hračka, maskot) → `shape`;
  3D soubor → `file`. Výstup přísně JSON: `route`, `tool_key`, `params` (`size_mm`, `colors[]`, `copies`, `text`),
  `summary` ve třech jazycích, `confidence` 0–1, `questions[]` (co chybí). `confidence < 0.6` → `human`; chyba API
  nebo časový limit → `human` se shrnutím „ozveme se do 24 h“, nikdy prázdná odpověď.
- Fáze 1: `shape` se chová jako `human` s příznakem `wants_shape` (Roman tvar zatím vyrobí ručně přes nástroj
  figurky mazlíčka nebo Tripo); automatické generování je fáze 2 (`GenerationService` už je).
- Testy bez skutečného modelu: `Http::fake` jako v testech `FilamentFromShop`; zlatý soubor
  `tests/fixtures/intake/cases.json` s aspoň 15 popisy (s fotkou i bez) a očekávanou cestou, test parsování
  odpovědi včetně rozbitého JSON a nízké jistoty.

### D. Předání do nástroje

- `/tools/{key}?intake=<token>`: stránka nástroje si fotku stáhne z `GET /intake/{token}/file` (jen s platným tokenem,
  bez indexace) a vloží ji do svého vstupu pro fotku, parametry (velikost, barvy, text) předvyplní. Udělej to jedním
  háčkem ve sdíleném kódu vstupu pro fotku v `resources/js/calc` (najdi, co používají figure, pet-figurine a charm),
  ne třikrát. Povinně funguje pro `figure`, `pet-figurine`, `charm`; ostatní nástroje aspoň otevřít s textovými
  parametry. Po načtení se intake označí `opened`.

### E. Vezme si to člověk

- Nejdřív si přečti `InquiryService`, `InquiryDispatcher`, `QuoteBuilder` a `/q/{quote}`: jestli jde poptávku
  z rozcestníku založit jako poptávku tržiště se zdrojem `intake` adresovanou jen naší farmě (bez rozesílky jiným
  tiskárnám), aby Roman mohl odpovědět nabídkou stávajícím tokem a zákazník ji přijal na `/q/{quote}`. Když to jde bez
  ohýbání tržiště, použij to; když ne, udělej jednoduchou tabulku `intake_requests` a stránku v administraci (seznam,
  detail s fotkou a textem, pole pro odpověď, stav). Rozhodnutí a důvod do zprávy řídící.
- Zákazník u téhle cesty zadá e-mail (a souhlas se zpracováním fotky), dostane potvrzení „ozveme se do 24 hodin“;
  obsluze mail přes `OrderFlow::alertAdmin` s novým druhem a přepínačem `notify_inquiry` (docs/AL.md §8 vzor).
  Lhůtu 24 h dej do nastavení farmy.

### F. Soubor

- 3D soubor jde dnešní cestou nahrání do kalkulačky; text z pole se uloží k intake a kalkulačka ho ukáže jako
  „Vaše poznámka“ a přenese do poznámky objednávky. Rozpoznávání úprav z textu („zmenšit na 10 cm, dvě barvy“) je
  fáze 3, teď ne.

### G. Měření

- Události auditu: `intake.submitted` {kind, has_text}, `intake.routed` {route, tool_key, confidence, ms, tokens},
  `intake.tool_opened` {tool_key}, `intake.human_created` {wants_shape}, `intake.file_to_calc`. Řádek do stránky
  trychtýře v administraci (docs/AJ.md), ať je vidět: odesláno → rozhodnuto → otevřen nástroj / poptávka → zaplaceno.

### H. Testy

- Feature: `IntakeTest` (fotka, text, soubor, limit 5/h, HEIC převod s malým fixture nebo s `Process::fake`, každá
  cesta přes `Http::fake`, poptávka zakládá záznam a maily přes `Mail::fake`, mazání po 30 dnech), `IntakeToolPreloadTest`
  (stránka `figure` s tokenem načte soubor a parametry; cizí token 404), `HomeRouterPageTest` (H1, sekce, tři jazyky,
  žádný text natvrdo mimo lang). Stávající `SeoTest`, `LandingPagesTest`, `ToolPageTest`, `CalculatorPageTest` zelené.
  `npm run build`, `vendor/bin/pint --test`.
- Projdi stránku skriptem na 390 a 1280 px (session 3 má `review.mjs`, docs/Z.md) a přilož snímky k pushi (scratchpad).

### I. Zpráva řídící

Větev a SHA; co funguje z A až G a co ne; rozhodnutí u E; čísla (doba odpovědi modelu, cena za jedno rozhodnutí
v tokenech); co má Roman rozhodnout: datum „do Vánoc“, ceny „od“ u karet, texty mailů, lhůta 24 h, příklady ve
štítcích. Dokumentaci piš do `docs/AN.md` (nová sekce „Rozcestník“).
