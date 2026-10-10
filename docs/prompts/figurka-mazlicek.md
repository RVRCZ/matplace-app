# Zadání pro session D: Figurka mazlíčka z fotky (`/tools/pet-figurine`)

Předloha: printpal „Pet to 3D Figurine Generator“ (Roman poslal 8 snímků průvodce do chatu; popis v bodu 1 je
úplný). Cíl stejný jako u všech našich nástrojů: **udělat to lépe než předloha** – bez přihlášení, s náhledem zdarma,
s koncem na farmě (tisk u nás) nebo ve stažení, a hlavně **tisknutelně** (tenké nohy a ocas jsou u mazlíčků to, co
se láme).

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-colorpick-wt`** (tvoje), nová větev **`feature/pet-figurine`** z aktuálního `main`:
  `git fetch origin && git checkout -b feature/pet-figurine origin/main` (okno barev je slité a nasazené).
  `.env` (sqlite, `APP_URL=http://localhost:8017`, `PYTHONPATH` podle tvé poznámky v U.md), `node_modules` junction
  (nemaž, nepřepisuj), `vendor`, `public/build` máš. Místní server `php artisan serve --port=8017`.
- Tvůj dokument: **`docs/X.md`** (co jsi postavil, rozhodnutí, co není ověřené, nasazení, co stálo kreditů Tripo).
- Řídící session (`conceprt19092026-50`, farma) slévá do `main` a nasazuje; ty **nemerguješ a nenasazuješ**. Commity po
  celcích, push na `origin feature/pet-figurine`, zpráva řídící session s commitem a souhrnem po každém kole. Před
  pushem `git fetch origin main && git merge origin/main`.
- Pravidla pro všechny session: texty jen v `lang/{cs,en,es}/<skupina>.php`, nebo – kde už skupina žije v JSON
  (`figure.*`) – přes `lang/src/<soubor>.json` + `python scripts/lang_add.py lang/src/<soubor>.json` (commitni zdroj
  i tři JSONy); testy `php -d memory_limit=2G vendor/bin/phpunit --filter …`; před pushem `vendor/bin/pint --dirty`,
  `npx tsc --noEmit -p .`, `npm run build`; **nikdy `taskkill /F /IM python.exe`**, nikdy `queue:retry all`; jedna
  session na worktree; `git add` jen jmenovitě; žádný `git stash`.
- Soubory, do kterých jen **přidáváš** (patří jiným): `config/tools.php`, `routes/web.php`, `lang/*/tools.php`,
  `lang/*/tools_seo/`, `tools/page.blade.php` (společná stránka – neměnit), `tool_page.ts` (Stage – jen když je to
  nutné a napiš proč). Figurka z fotky (`tools/figure.blade.php`, `figure.ts`, `GenerationController`,
  `GenerationService`, `PedestalChanger`, `ModelNormalizer`, `TripoGenerator`) je dnes bez vlastníka (session 0/1
  neběží) – smíš do ní zasahovat, ale **existující chování busty a figurky se nesmí změnit** (`FigureToolTest`
  zelený beze změn asercí). Farmu, kalkulaci a okno barev neměň.
- **Kredity**: Tripo stojí 20 kreditů (0,20 $) za obrázek → model. Zkoušky naostro drž pod ~15 generování a každou
  zapiš do X.md (co, proč, výsledek). Místně a v testech `engines.generator = fake` (`FakeGenerator`).

## 1. Co předloha umí (z 8 snímků průvodce)

1. „Turn a pet photo into a 3D figurine“: AI nejdřív vylepší fotku (Super Resolution), pak z ní postaví model; trvá to
   4–6 minut; jen po přihlášení; každá figurka = 1 kredit.
2. Nahrání fotky (PNG/JPG/WebP): celé tělo, dobře osvětlené, minimum pozadí, **boční nebo tříčtvrteční úhel** zachytí
   podobu nejlépe.
3. Styl: **Realistic** (přirozené detaily a proporce) / **Cartoon** (stylizovaný, výrazné rysy) / **Tabletop
   Miniature** (matný „hliněný“ vzhled, hračkovité proporce, **na podstavci**).
4. Výstup STL (nebo OBJ/GLB/FBX pro úpravy v 3D programu). 5. Počítadlo kreditů. 6. Generate ve dvou krocích
   (vylepšená fotka se ukáže v panelu, pak model; můžeš odejít a vrátit se). 7. Historie generování, stažení znovu.
- Co předloha neumí a my ano: bez účtu a zdarma náhled, **velikost** a **podstavec se jménem mazlíčka**, kontrola
  tisknutelnosti (tenké nohy, ocas, uši), tisk u nás, otevření s týmiž nastaveními, víc fotek (bok, záda) pro věrnější
  tvar (naše figurka to už umí).

## 2. Co už máme (navaž, nepiš znovu)

- **Figurka z fotky** `/tools/figure` (`tools/figure.blade.php`, `resources/js/calc/figure.ts`, texty `figure.*`
  v `lang/*.json` ze `lang/src/figure_lead.json` a spol.): druh busta/figurka, hlavní fotka + volitelné pohledy
  `left/back/right` (posouzení fotky viděním, mazání fotek, souhlas), velikost 30–250 mm, podstavec
  (`PedestalChanger::TYPES`, jméno + věnování), stav generování přes `POST /api/generate` →
  `GenerationController::store` → `GenerationService::fromPhoto(kind bust|figure)` → fronta `GenerateModel`
  (Tripo image-to-model, geometrie bez textur, `enable_image_autofix`) → `ModelNormalizer::toPrintableStl`
  (`clean`, `pedestal`) → `ModelFile` (origin `generated`) → `stage.fileResult`. Změna podstavce bez nového
  generování (`PedestalChanger::change`). Kvóty (`GenerationService::quota`, `ai.daily_limits`). Testy
  `FigureToolTest` s `FakeGenerator` (vzor pro tvoje testy, vč. mazání fotky a kontroly podstavce).
- Tripo: `app/Engines/Generator/TripoGenerator.php` (image-to-model, `texture:false`, `pbr:false`, `face_limit`).
  Naše obálka dnes **neposílá styl**; Tripo API má u image-to-model volitelné `style` (např. `object:clay`,
  `person:person2cartoon`, `animal:venom`, `gold`) a samostatnou úlohu `stylize` – ověř v aktuální dokumentaci
  Tripo (verze modelu, co `style` umí u zvířat) a **napiš do X.md, co jsi zjistil**, než to použiješ.
- Vylepšení fotky obrázkovým modelem (Gemini) je ve větvi session 4 (`feature/tools-sell`, nesloučená) – **nepoužívej**;
  Tripo `enable_image_autofix` dělá ořez a doplnění pozadí samo.
- Kontrola tisknutelnosti: `App\Domain\Farm\ModelValidator` (tenké stěny, rozměry) – podívej se, co hlásí, a použij
  pro varování o tenkých nohách/ocasu (bod 3.4).

## 3. Co postavit

### 3.1 Stránka `/tools/pet-figurine` (kind `pet`, modul `figure` znovu použitý)
Nová stránka `tools/pet.blade.php` (`@extends('tools.page', ['tool' => 'pet', 'module' => 'figure', …])`, `figure.ts`
dostane `cfg.kind = 'pet'`; co je pro mazlíčka jinak, řeší podmínky v blade a pár řádků v TS – **ne kopie souboru**).
Kroky:
1. **Fotka** – stejné nahrání jako u figurky (hlavní fotka + volitelně bok/záda), tipy pro mazlíčky: celé tělo včetně
   nohou a ocasu, bok nebo tříčtvrteční úhel, klidný postoj (sed nebo stoj), jednobarevné pozadí, žádný vodítko/ruka
   v záběru. Posouzení fotky (vidění) má u figurky „je to člověk?“ – pro mazlíčka otočit: „je na fotce jedno zvíře,
   celé, bez člověka?“ (`FigureToolTest`-style test s falešným viděním).
2. **Styl** – **Realistická** (jak to Tripo vrátí, jen vyčištěno), **Miniatura na stůl** (bod 3.3), **Kreslená**
   – jen když Tripo `style` dá u zvířat použitelný výsledek (ověř 2–3 fotkami, zapiš); jinak tuhle volbu v prvním
   kole **nezobrazuj** (nenech tlačítko, které vrátí realistickou).
3. **Podstavec** – stejný krok jako u figurky: velikost 30–250 mm (výchozí 80), typy podstavce z `PedestalChanger`,
   **jméno mazlíčka** na štítku (výchozí prázdné; placeholder „Rex“), věnování; u Miniatury je podstavec součástí stylu
   (kulatý, hrubší, min. Ø 40 mm) a nejde vypnout.
4. **Materiál a počet kusů** – společný krok.
Tlačítko „Vytvořit figurku“ → `POST /api/generate` s `kind=pet` (`GenerationController` validace `in:bust,figure,pet`,
`GenerationService::fromPhoto` `name_en` „pet figurine“, `PedestalChanger` přijme `pet`), stav a výsledek jako u
figurky (fáze, čekání 1–3 min u Tripa – napiš do stránky poctivě „obvykle 1–3 minuty, můžete odejít a vrátit se
odkazem“, odkaz = `?from=…` / `/tools/pet-figurine?generation=token` jako u figurky).

### 3.2 Realistická
Tripo image-to-model jako dnes, `clean` + `pedestal`. Zjisti, zda `enable_image_autofix` u zvířat pomáhá (pozadí,
ořez) – porovnej jednu fotku s/bez, zapiš.

### 3.3 Miniatura na stůl (náš vlastní krok v Pythonu, offline, `engines/python/`)
Po Tripu, před podstavcem: „hliněný“ vzhled a tisknutelné proporce.
- Voxelizace na mřížce 0,4 mm (v tiskové velikosti), **uzavření/dilatace** o poloměr r = 0,8–1,2 mm (slider „hrubost“
  3 stupně), marching cubes zpět na síť, Taubin vyhlazení (λ 0,5, μ −0,53, 10–20 iterací), decimace na ~150 k
  trojúhelníků; výsledek musí být `is_watertight`. Tím nohy, ocas a uši zesílí a zmizí drobné výčnělky (vousy,
  obojek) – přesně vzhled „tabletop miniature“.
- Kontrola tloušťky **po** zesílení: nejtenčí místo ≥ 2,5 mm při zvolené velikosti; když ne, zvedni r (max 1,6 mm) nebo
  navrhni větší velikost (varování na stránce, bod 3.4).
- Podstavec: kulatý, Ø = max(40 mm, šířka postavy × 1,1), výška 4 mm, horní hrana zkosená 1 mm, jméno vyvýšené
  0,6 mm na obvodu (reuse `PedestalChanger`/socle, jen jiné proporce) – figurka stojí celou plochou nohou na něm
  (seřízni spodek rovně, jako to dělá busta „torn bottom → flat cut“).
- Čas na serveru do 20 s pro 150 mm figurku (změř).

### 3.4 Tisknutelnost (náš rozdíl proti předloze)
Po normalizaci spočítej nejtenčí místo (voxel/odstup; `ModelValidator` nebo vlastní v Pythonu – co je k ruce) a na
stránku dej větu: „Nejtenčí místo (ocas/noha) má X mm – pod 2,5 mm se při tisku láme. Zvolte Miniaturu nebo větší
velikost.“ Číslo i do `meta.notes.thinnest_mm`. Podstavec vždy: figurka na vlastních nohách bez podstavce se tiskne
špatně a předloha to neřeší.

### 3.5 Texty a SEO
`lang/src/pet_figurine.json` (klíče `pet.*`: lead, kroky, tipy, styly, varování, podstavec; cs/en/es) →
`scripts/lang_add.py`; `lang/*/tools.php` (název „Figurka mazlíčka z fotky“, hledací slova: figurka psa, figurka
kočky, 3D mazlíček, soška psa z fotky); `lang/*/tools_seo/pet.php` (intro, kroky, FAQ: jaká fotka, jak dlouho, cena,
jak velká, co s tenkýma nohama, mohu ji nechat vytisknout, co s fotkou – mažeme po vygenerování jako u figurky);
`config/tools.php` `'pet' => ['route' => 'tools.pet', 'intent' => 'create', 'categories' => ['images', 'names'],
'available' => true, 'seo' => ['examples' => []]]` – ukázky u figurky nejsou (generátor), stejně tak tady; karta
`matplace:tool-examples pet --card` ať funguje s fake generátorem nebo bez ukázek (podívej se, jak to řeší figurka).
Řídící session nástroj po nasazení **skryje** v `/admin/tools`, Roman zapne po zkoušce.

### 3.6 Testy
`tests/Feature/PetFigurineTest.php` (vzor `FigureToolTest`, `FakeGenerator`, falešné vidění): stránka ve třech
jazycích (kroky, tipy, styly bez „Kreslené“ pokud není), `kind=pet` přijat a `name_en` „pet figurine“, souhlas,
fotka smazána po generování, jméno na podstavci dojde do modelu, **Miniatura**: na fixtuře s tenkýma nohama
(jednoduchá síť „stůl“ 4 nohy Ø 1,2 mm, deska 30×20×3 mm, `tests/Support/MeshFixtures`) zesílí nohy na ≥ 2,5 mm při
80 mm, výsledek `is_watertight`, podstavec Ø ≥ 40; `thinnest_mm` v meta; `?from`/`generation=` otevře stránku se
stejnými volbami; `ToolPageTest` (cyklus přes `config('tools')`) zelený; `FigureToolTest` zelený beze změn.

### 3.7 Pořadí práce
1. Zjištění: Tripo `style` u zvířat (2–3 generování naostro, zapsat), `image_autofix` s/bez (1 fotka).
2. Stránka s kind `pet` + Realistická + podstavec se jménem + tisknutelnost; testy; zpráva řídící session.
3. Miniatura (Python), kontrola tloušťky, varování; testy; zpráva.
4. Kreslená jen podle bodu 1. Texty, SEO, X.md, zpráva s commitem a s tím, co nainstalovat (ideálně nic).
5. Druhé kolo (po Romanově zkoušce): dvě zvířata na jednom podstavci, výběr pózy, barevné oči/obojek jako druhá
   barva – **ne teď**.

## 4. Co není v zadání
- Vylepšení fotky obrázkovým modelem (Gemini) – až bude sloučená session 4; Tripo autofix stačí.
- OBJ/GLB/FBX export – ne (STL/3MF jako všude).
- Historie generování – máme `?from=`/`generation=` a „Moje modely“ u přihlášených; nic nového.
- Měnit chování busty a figurky – ne.
