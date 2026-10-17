# Zadání pro session 2: víc kusů na desce než mřížka podle kvádru (TODO #3)

Od Romana (13. a 17. 10. 2026): kopie jednoho dílu se dnes kladou do mřížky podle obrysového obdélníku; u cedulek,
přívěsků a klíčenek se na desku vejde o 20 až 40 % kusů víc, když se skládají podle skutečného půdorysu. Kalkulace
musí slibovat totéž, co farma vytiskne.

## 0. Kde pracuješ a pravidla

- Tvůj worktree, nová větev **`feature/plate-layout`** z aktuálního `main`
  (`git fetch origin && git checkout -b feature/plate-layout origin/main`), dokument **`docs/AE.md`**.
- Tvoje soubory: `app/Domain/Farm/PlateLayout.php`, nový `app/Domain/Farm/Footprint.php` (nebo podobně),
  `engines/python/farm_tool.py` (je tam `footprint`, jen plocha konvexního obalu) a případně nový malý skript v
  `engines/python/`, `app/Engines/DTO/PreparedMesh.php` (nové pole), `app/Jobs/PrepareFarmOrder.php` jen v místech, kde
  volá `PlateLayout`, `resources/js/calc/calculator.ts` jen `piecesOnBed` a co s ním souvisí, nový endpoint v
  `routes/api.php` + kontroler, testy. Nesahat: `OrderService`, `OrderController`, `GcodeSlot`, agent, profily sliceru,
  `param.ts`, soubory videí (session 1: `BuildFarmTimelapse`, `FarmVideos`).
- Pravidla: texty jen v `lang/{cs,en,es}/*.php` nebo `lang/src/<soubor>.json` + `python scripts/lang_add.py`; `git add`
  jmenovitě; žádný stash; `PYTHON_BIN` v `.env` (Store Python s trimesh a manifold3d); před pushem `vendor/bin/pint --dirty`,
  `npx tsc --noEmit -p .`, `npm run build`; testy `php -d memory_limit=2G vendor/bin/phpunit --filter …`. Nemerguješ a
  nenasazuješ: push na `origin feature/plate-layout` a zpráva řídící session (conceprt19092026-8c).
- Co dnes je: `PlateLayout::capacity` (mřížka podle bbox, mezera `GAP_MM` 5, otočení o 90°, když se vejde víc) a
  `PlateLayout::replicate` (zapíše kopie do jednoho STL, co nejčtvercovější mřížka uprostřed desky); `PrepareFarmOrder`
  počítá `usable` = deska mínus 2 × `bed_margin_mm`, dělí zakázku na desky (`perPlate`, zbytek); kalkulace slibuje
  totéž v `calculator.ts` `piecesOnBed`. Max 64 kopií. Desky: S1 250 × 250, Max 420 × 420, Kobra X 260 × 260.

## 1. Půdorys dílu místo kvádru

- Při přípravě modelu (`PrepareFarmOrder`, kde vzniká `PreparedMesh`) spočítat jednou **půdorys**: konvexní obal stínu
  dílu na desce v jeho tiskové orientaci (Python, `farm_tool.py` už obal umí, vrať body, ne jen plochu), nejvýš
  64 bodů, mm, zaokrouhleně na 0,1. Uložit k připravenému dílu (`PreparedMesh::footprint`, do JSON vedle STL), ať se
  při kladení nevolá Python.
- Pro díly, kde obal není o moc menší než obdélník (plocha obalu > 85 % bbox), zůstává mřížka: stejný výsledek jako
  dnes, žádná změna u krabiček a stojánků.

## 2. Kladení

- Deterministický algoritmus v PHP (`PlateLayout`), jeden zdroj pravdy pro farmu i kalkulaci:
  1. obal zvětšený o polovinu mezery (offset 2,5 mm) na každé straně;
  2. **řádkové kladení** (shelf): díly v řadách, v každé řadě střídavě otočené o 0° a 180° (trojúhelníky a lichoběžníky
     do sebe zapadnou), řady se sunou k sobě, kam to obaly pustí; zkusit otočení 0/90/180/270° celé sady a vzít
     nejlepší;
  3. kontrola, že se žádné dva obaly nepřekrývají (separating axis test) a že vše leží v `usable`;
  4. výsledek nikdy menší než dnešní mřížka: když vyjde méně, vrátit mřížku.
- `replicate` zapíše kopie podle spočtených poloh a otočení kolem Z (díl zůstává ve své tiskové orientaci, jen se
  otáčí a posouvá); sada uprostřed desky jako dnes.
- Výkon: do 200 ms na díl a desku v PHP; při 64 kopiích do 1 s. Žádné externí knihovny.

## 3. Kalkulace slibuje totéž

- Nový endpoint `POST /api/calc/plate` (uuid souboru nebo rozměry + půdorys, deska) → `{max, perPlate, layout}`;
  volá `PlateLayout`. `calculator.ts` ho zavolá, jakmile má soubor, a do odpovědi ukáže dnešní odhad podle bbox jako
  pesimistickou výchozí hodnotu (nikdy neslíbí víc, než pak farma dá). Odpověď kešovat podle souboru a desky.
- Na stránce kalkulace u počtu kusů věta „Na jednu desku se vejde N kusů“ zůstává, jen číslo je nové; text neměnit.

## 4. Testy a ověření

- `tests/Unit/PlateLayoutTest.php`: syntetické tvary (trojúhelníková cedulka 40 × 60, L 60 × 60 s ramenem 20,
  kruh Ø 50, obdélník 30 × 80) na S1 i Maxu: počet ≥ mřížka, u trojúhelníku a L o ≥ 20 % víc, žádné překrytí
  (test polygonů), vše uvnitř desky, stejný výsledek při opakování; obdélník = přesně mřížka.
- `tests/Feature`: `FarmPagesTest`/`FarmOrderFlowTest` beze změny výsledku u kostky; nový test endpointu
  (odpověď, keš, odmítnutí cizího uuid).
- Obrázek rozložení (debug PNG z PHP přes GD, nebo z Pythonu) pro dva tvary do `docs/img/plate-layout/`, do AE.md
  tabulka „tvar, deska, dnes, nově“.
- **Hotovo =** push `feature/plate-layout`, AE.md (rozhodnutí: práh 85 %, úhly, kdy mřížka), testy, `pint`, `tsc`,
  `build`, zpráva řídící session.

## 5. Co není v zadání

Kladení více různých dílů na jednu desku (dnes jde každý díl zvlášť), `arrange` sliceru, změna ceny a plateb,
Kobra X mimo rozměr desky.
