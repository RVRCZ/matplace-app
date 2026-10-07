# P: tvar z obrázku a jména (session 1, vánoční sada)

Větev `feature/tools-shapes` (z `main` 4438a2d, 7. 10. 2026), zadání `docs/prompts/nastroje.md`, část „Session 1“.
Tenhle dokument popisuje **první várku**: obrázek v barvách filamentů a pět nástrojů, které na něm stojí. Zbytek
vánoční sady (perníček se jménem, sušenka s polevou, klíčenka, velké písmeno, zápich do dortu) a zbytek session 1
jsou v sekci 7 jako to, co teprve přijde.

## 1. Co vzniklo

**Pět nástrojů v katalogu**, každý s vlastní stránkou, kartou, SEO texty ve třech jazycích a třemi ukázkami:

| nástroj | adresa | kind | co přidává k obrázku |
|---|---|---|---|
| Vánoční ozdoba z obrázku | `/tools/ornament` | `ornament` | očko na stužku; tvar podle obrázku, kruh nebo hvězda |
| Přívěsek z obrázku nebo jména | `/tools/charm` | `charm` | očko kdekoli na obrysu; tvar podle obrázku, kruh, obdélník |
| Náušnice z obrázku | `/tools/earrings` | `earrings` | pár stejných nebo zrcadlových, obě na jedné podložce |
| Magnetka z obrázku | `/tools/magnet` | `magnet` | kapsa na magnet vzadu (lepení, nalisování, skrz, bez), předvolby disků |
| Podtácek s obrázkem | `/tools/coaster` | `coaster` | kruh, čtverec, šestiúhelník; drážky zespodu |

**Obrázek v barvách** (`engines/python/shape2d.py`, funkce `colors`) – sdílené pro všechno, co přijde (sušenka,
filament‑art v session 3):

1. PNG / JPG / WebP / SVG (barevné SVG se nakreslí jako obrázek, výplně v pořadí kreslení) → mřížka nejvýš 560 buněk
   na delší straně; kontrast, jas a sytost fotky.
2. **Pozadí pryč**: průhlednost, když ji obrázek má; jinak barva, která se dotýká okrajů (medián okraje, tolerance
   ΔE 3–38 podle posuvníku „síla“, výchozí 30 = ΔE 13,5) a jen to, co je s okrajem spojené. Jde vypnout – pak je
   motivem celá fotka.
3. **Barvy**: k‑means v Lab s pevným začátkem (stejný obrázek = stejné barvy), 1–8 barev; středy bližší než ΔE 7 jsou
   jedna barva; skvrny pod 1 mm² a čáry užší než tři buňky (lem každé vyhlazené hrany) připadnou sousedovi; barva
   pod 0,3 % plochy zaniká. Barvy jsou očíslované podle plochy (1 = největší) – to číslo je vidět na stránce a nese
   ho díl `color_<n>`.
4. **Filament ke každé barvě**: nejbližší cívka v Lab z těch, které jsou skladem, mají obyčejnou barvu (ne duhové
   a svítící) a jsou z plastu, kterého je skladem nejvíc (`ParametricGenerator::spools()`); každá barva jinou cívku,
   dokud to dává smysl. Návštěvník může kterékoli barvě dát jakoukoli cívku z okna Barva, barvy přeskládat (co leží
   na čem) a dvě sloučit.
5. **Obrysy**: maska se rozmaže (σ = 0,7 buňky + vyhlazení v mm) a obrys je tam, kde přechází přes polovinu
   (`skimage.measure.find_contours`) – hrany jsou hladké, ne schodovité, a maska uvnitř jiné masky dá obrys uvnitř
   jejího obrysu. Každá barva má dvě plochy: `own` (jen ona) a `stack` (ona a všechno nad ní).

**Builder** `engines/python/shape_kinds.py` – jeden pro všech pět produktů. Díly: `body` (podklad s očkem),
`color_<n>`, `rim`. Dva způsoby, jak barvy vytisknout:

- **nad sebou (výchozí)**: každá barva je o krok výš než ta pod ní (0,4–1,2 mm po 0,2; náušnice a podtácek
  0,2–0,8). V každé vrstvě tisku je jediný filament, takže to vytiskne **každá tiskárna výměnou filamentu ve výšce**.
  `notes.color_changes` říká, kde (`z`, díl, kód cívky, hex).
- **zarovno** (`flush`) a **lem ve vlastní barvě** (`rim`): barvy sdílejí vrstvu → `notes.multi_material`. Stránka
  to řekne dřív, než člověk klikne dál.

Podklad má barvu nejnižší barvy obrázku (žádná výměna navíc: obrázek ve dvou barvách = jedna výměna, to, co farma
umí už dnes). U kruhu, čtverce, šestiúhelníku, hvězdy a obdélníku s vyříznutým motivem dostane podklad cívku, která je
od všech barev motivu nejdál (sněhulák s černým kloboukem skončí na fialové, ne na černé ani na bílé).

**Očko** je kroužek na obrysu: poloha v % obvodu po směru hodin od nejvyššího bodu (posuvník), nebo **tažením
v náhledu** – oranžový úchyt jede po obrysu (`viewer.ts` `setMarker`, obrys posílá nástroj jako 120 bodů rovnoměrně
po délce, takže místo v seznamu je rovnou podíl obvodu). Tlačítko „Vrátit očko nahoru“.

**Stránka** (`tools/param.blade.php`, `calc/param.ts`): pět produktů je „rodina“ `shape`
(`ParametricGenerator::FAMILY`). Společná stránka se naučila tři věci, které užijí i další nástroje:
pole, zaškrtávátka a volby mohou patřit do jiné sekce než „Rozměry“ (`PLACE`), text může patřit nástroji, rodině
nebo všem (`param.f.<nástroj|rodina>.<pole>`), a builder může sám říct, které trojúhelníky jsou který díl
(`parts["_pieces"]` – vrstvy barev se dotýkají plochou, podle objemu by je `pieces_of` nerozeznal).
Sekce Barvy ukazuje seznam barev shora dolů: vzorník cívky (klik = okno Barva), barva v obrázku, podíl plochy,
šipky pořadí, sloučení; pod tím větu, jak se návrh vytiskne (jedna barva / N výměn / jen vícemateriálová tiskárna).
Posuvníky fotky se na malém obrázku projeví hned (CSS filtr), model za okamžik.

**Knihovna**: nová kategorie `colour` (Barevné) s osmi vlastními kresbami v barvách, které mají cívku
(`engines/artwork/colour/_draw.py` je kreslí z prosté geometrie; CC0, řádky v `SOURCES.md`). Každý z pěti nástrojů
se otevírá s jednou z nich (`ParametricGenerator::SAMPLE`), takže první, co návštěvník vidí, je hotová věc.

**Projekt pro slicer s více výměnami**: `ColorChange::addAll` zapíše do projektu Orca / Bambu i PrusaSlicer výměnu
filamentu pro každou barvu (dvě výměny ve stejné vrstvě jsou jedna); `ModelFile::colorChanges()` je čte z návrhu.
S jedinou výměnou se nic nemění (`color_change_mm` zůstává, farma ho zná).

**Ukázky a karty** se kreslí v barvách filamentů i mimo karty (`render_tool.py` bere soubor s barvami dílů).

## 2. Rozhodnutí a proč

1. **Produkt = vlastní kind**, ne jeden kind `shape` s polem `product` (tak to psalo zadání). Meze a výchozí hodnoty
   se u produktů liší (náušnice 10–60 mm, podtácek 80–120) a stávající stroj má meze na kind; uložený návrh se
   podle kindu vrací na svou stránku („změnit návrh“ funguje bez jediné řádky navíc). Builder je jeden.
2. **Barvy nad sebou jako výchozí, zarovno a lem jako volba.** Farma i projekty dnes znají jen výměnu filamentu ve
   výšce; barvy vedle sebe v jedné vrstvě by nešly ani objednat, ani vytisknout na tiskárně s jednou tryskou.
   Proto i podtácek začíná „nad sebou“ s krokem 0,4 mm (zadání chtělo zarovno) – rovný vršek je jedno zaškrtnutí,
   ale stránka u něj říká, co potřebuje.
3. **Krok mezi barvami po 0,2 mm, nejmíň 0,4** (zadání 0,3–1): barva končí tam, kde končí vrstva, a dvě vrstvy
   kryjí barvu pod sebou.
4. **Podklad v nejnižší barvě obrázku**, ne tmavý obrys kolem (tak vypadá předloha). Tmavý obrys stojí cívku a výměnu
   navíc; je to jeden klik na vzorník podkladu.
5. **Mřížka 560 buněk** místo 360 (limit siluet): obrysy kreseb musí přežít. Trasování je přes `find_contours`,
   ne přes sjednocení řádků obdélníků, takže to času nepřidalo.
6. **Automatická cívka jen z jednoho plastu** (toho, kterého je skladem nejvíc): jeden tisk je jeden druh plastu.
7. **Očko je součást podkladu** a díra jde skrz všechny barvy; poloha po 0,5 %.
8. **Test `ToolPageTest::test_verified…`** padal už na `main` (od 7. 10. mají tři nástroje v configu datum ověření
   a test čekal katalog bez štítků). Test si teď data sám vynuluje; chování hlídá dál.

## 3. Co se na farmě vytiskne a co ne – **potřebuju rozhodnutí**

Farma dnes umí **jednu barvu, nebo dvě** (podklad + všechno nad jednou výškou; `second_slot_id`,
`GcodeSlot::secondColor`, ověřeno na S1 + ACE 22.–30. 9.). Z toho plyne:

| návrh | na farmě dnes | ke stažení |
|---|---|---|
| jedna barva | ano | STL, projekt |
| dvě barvy nad sebou (počet barev 2, nebo jméno na podkladu) | **ano**, druhou barvu si zákazník vybere při objednávce | projekt s jednou výměnou |
| tři a víc barev nad sebou (výchozí u obrázků) | jen jednobarevně – stránka to říká a radí „počet barev 2“ | projekt s N výměnami, STL po barvách |
| zarovno / lem | ne | STL po barvách (projekt je jednobarevný) |

Aby vánoční sada šla objednat ve třech a čtyřech barvách, musí farma umět **N výměn ve výšce**: jedna cívka navíc
se zobecní na seznam (`farm_orders` sloupec se sloty, výběr barev na úvodní stránce objednávky, `GcodeSlot` vloží
`T<n>` do každé z vrstev, stroj musí mít všechny cívky založené – ACE má čtyři sloty). Návrh už všechno potřebné nese:
`tool_params.color_changes` = výšky a kódy cívek, `part_colors` = cívka každého dílu. Do objednávky jsem nesahal:
mění placený tok živé farmy a potřebuje zkušební tisk. **Roman: mám to udělat jako další krok téhle session?**
Do té doby texty nástrojů i SEO stránek říkají pravdu („na farmě jednu nebo dvě barvy“).

Druhá věc k rozhodnutí: úvodní stránka objednávky u dvoubarevného návrhu z těchto nástrojů nepředvybere barvy
z návrhu (to umí jen pro QR, `codeColors()`); zákazník je vybere ručně. Je to pár řádků v `OrderController`, ale
taky objednávka – čeká na stejné „ano“.

## 4. Rychlost

Čas požadavku `POST /api/tools/param/preview` s díly (`pieces`), medián z pěti, výchozí nastavení a obrázek
z knihovny, lokálně (PHP vestavěný server na Windows, 7. 10. 2026):

| nástroj | požadavek | STL |
|---|---|---|
| přívěsek, duch 45 mm | 1,36 s | 107 kB |
| náušnice, srdce 32 mm | 1,35 s | 111 kB |
| ozdoba, perníček 80 mm | 1,35 s | 168 kB |
| magnetka, tlapka 60 mm | 1,35 s | 95 kB |
| podtácek, sněhulák 100 mm | 1,36 s | 136 kB |
| přívěsek ze jména (bez obrázku) | 0,74 s | 214 kB |
| cedulka pro srovnání (N.md: 0,71 s) | 0,74 s | 110 kB |

Samotný `param_tool.py` s obrázkem běží 0,7–0,9 s (fotka v osmi barvách na 120 mm: 0,89 s); z toho asi 0,45 s je
import numpy, `scipy.ndimage` (0,24 s) a Pillow, zbytek čtení obrázku, barvy a obrysy. **Cíl „do 1 s“ lokálně
nesplňuju** (1,35 s + 0,35 s prodleva před dotazem). Na produkci jsem neměřil (větev není nasazená); cedulka je tam
1,6× rychlejší než lokálně (0,44 vs. 0,71 s), takže čekám kolem 0,9 s – po nasazení změřit. Po zjednodušení obrysů
(bod na desetinu buněk) má model 2–5 tisíc trojúhelníků; předtím 14 tisíc.

## 5. Co není ověřené

- **Nic z toho se netisklo.** Geometrie je ověřená výpočtem (testy: objemy, rozměry, díly, výšky výměn), ne tiskem.
  K vyzkoušení na farmě: přívěsek 45 mm ve dvou barvách (očko Ø 3, stěna 2 – drží?), náušnice 30 mm (tloušťka 2,4),
  magnetka s kapsou Ø 10 × 2 na lepení (vůle 0,2) a nalisování (0,05), podtácek 100 mm s drážkami. Štítek „ověřeno
  tiskem“ žádný z pěti nemá.
- **Projekt s více výměnami** hlídá test (`ColorChangeTest`: tři řádky ve správném pořadí, Orca i Prusa); ve
  skutečné Orce ani PrusaSliceru jsem ho neotvíral. Jedna výměna je ověřená z dřívějška (N.md §8).
- **Tažení očka** jsem zkoušel skriptem v headless Chrome (úchyt se chytí, hodnota se změní, náhled se přepočítá),
  ne rukou a ne na dotykovém displeji.
- **Fotky lidí a zvířat**: postup je dělaný pro kresby a loga. Fotka projde (vybere hlavní barvy), ale výsledek je
  plakátový; na portréty bude filament‑art ze session 3.
- Barvy ukázek a karet jsou z mého lokálního katalogu cívek (37 obyčejných PLA+ skladem). Na produkci se obrázky
  nepřekreslují samy.

## 6. Nasazení (Roman)

Bez migrace, bez nových balíčků v `/opt/matplace-py` (scikit-image tam je), bez nových klíčů `.env`.

```
git pull            # feature/tools-shapes, nebo main po slití
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker
```

S gitem jdou: `engines/artwork/colour/` (8 SVG + `_draw.py`), `public/img/tools/{ornament,charm,earrings,magnet,coaster}-*`,
`public/img/tool-examples/…-{1,2,3}.png`. Po nasazení projít `/tools` (pět nových karet), `/tools/charm` (táhnout
očko, změnit cívku barvy, šipky pořadí, sloučit), `/tools/magnet` (předvolby magnetu), „Pokračovat k ceně“ a
u dvoubarevného návrhu objednávku na farmě.

Poznámka k tomuhle PC: `C:\matplace-app\node_modules` je od 7. 10. 07:37 prázdný (zmizel při úklidu worktree, které
na něj měly odkaz), takže `npm run build` nejde v žádném worktree, který tam odkazuje. V `C:\matplace-shapes-wt`
jsem odkaz nahradil vlastním `npm ci`; sdílený adresář obnoví `npm ci` v `C:\matplace-app`.

## 7. Co přijde (v tomhle pořadí)

Vánoční sada: klíčenka (`keychain`, stejná rodina), perníček se jménem (`sign` tvar `gingerbread` s polevou),
sušenka s kreslenou polevou (`cookie`), skladba vrstev `compose` → velké písmeno se jménem a zápich do dortu.
Potom zbytek zadání session 1: ostatní produkty rodiny (jmenovka na klip, brčko, gumičky, otvírák, miska,
organizér podle fotky, lístečky, čep na tašku, medaile, stojan na svíčku, papel picado, klikátko), korálky, stojánek
na tužky, tvary a motivy cedulky, `logo` `extrude`, 20+ písem.
