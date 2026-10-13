# Zadání pro session D: filament art z malby nebo fotky (až 8 barev, vrstvy složené do obrazu)

Od Romana (13. 10. 2026, k `/tools/filament-art`): „udělat tak, že nahraji barevný obrázek a rozložíme ho na barevné
vrstvy, max 8, ty se pak složí do obrazu“ – s fotkou vytištěné Hvězdné noci jako předlohou
(`docs/img/filament-art/predloha-tisk-hvezdna-noc.png`).

## 0. Co už máme a co zkouška ukázala

Nástroj to v principu umí: nahrání obrázku, 1–8 barev (`colors_n`), režim `stack` (jeden tisk, barvy jako schody) a
`layered` (deska na barvu, skládané za sebou s distančními sloupky, rám), odstranění pozadí, přiřazení cívek, návod
zezadu dopředu (`art_tool.py`, `shape2d.colors()`, `art.ts`). Pro **grafiku** (knihovna `lib:colour/*`, loga) je to
dobré. Pro **malbu nebo fotku** ne – zkouška řídící session na Hvězdné noci (1280 px, 8 barev, `layered`, 160 × 127 mm,
`docs/img/filament-art/params-zkouska.json`):

- výsledek `docs/img/filament-art/dnes-8-barev-vrstvene.png` proti `zdroj-hvezdna-noc.jpg`: tisíce drobných
  ostrůvků (varování `thin_merged`), cypřiš splynul s tmavou oblohou do jedné černé masy, vesnice zmizela, měsíc a
  hvězdy jsou vybledlé (#a8a558), paleta je sedm odstínů modré a jedna žlutá;
- 1 076 200 trojúhelníků, 22 s stavby; `render_tool.py` to odmítne („too many triangles“), stránka by to táhla těžko;
- předloha (vytištěná Hvězdná noc) má naopak velké čisté plochy, nejmenší prvky ~2 mm, syté barvy, tmavou obrysovou
  desku vpředu.

Příčina: `shape2d.colors()` dělá medián 3 px + k-means v Lab po pixelech; žádné zjednodušení oblastí v milimetrech,
žádné sloučení ostrůvků, barvy se vybírají podle četnosti (malé syté akcenty prohrají).

## 1. Co postavit (`source = photo` – režim pro malby a fotky; grafika zůstává, jak je)

1. **Rozpoznání vstupu**: fotka/malba = mnoho barev, plynulé přechody (počet unikátních barev po mediánu, podíl
   hran); u takového vstupu stránka přepne na režim „malba“ (zákazník to může přepnout ručně, volba `look`:
   `graphic` | `painting`).
2. **Zjednodušení oblastí v milimetrech** (nový posuvník `detail` 1–5 mm, výchozí 2): nejdřív hranově šetrné
   vyhlazení (bilaterální filtr nebo superpixely `skimage.segmentation.slic` a průměr barvy na superpixel – scikit-image
   je v `/opt/matplace-py` i místně), pak kvantizace, pak **na každé barvě** morfologické otevření/zavření s jádrem
   `detail` mm (podle mm/px vstupu) a sloučení ostrůvků pod `min_area` (= `detail²` × 2 mm²) do převažujícího souseda.
   Cíl u Hvězdné noci: pod 300 oblastí celkem, žádná menší než `detail`.
3. **Výběr barev, které drží obraz**: k-means v Lab s k = n + 3, pak slučovat nejbližší dvojice do n, ale **chránit**
   shluky, které jsou syté a kompaktní (měsíc, hvězdy: vysoká chroma, malý podíl) – takový shluk se neslučuje, dokud
   zbývá méně sytý. Saturace a kontrast se nemění bez posuvníků, které už jsou (`contrast`, `saturation`).
   Po výběru barev přiřazení nejbližších cívek jako dnes (`palette`, `free_colors`).
4. **Pořadí desek** (`layered`): zezadu dopředu podle plochy (největší vzadu) jako dnes, ale **nejtmavší barva
   vpředu** jako obrysová deska, pokud tvoří souvislé linie (u Hvězdné noci cypřiš + obrysy); možnost přetáhnout
   pořadí zůstává (`order`).
5. **Rychlý 2D náhled na stránce**: po nahrání a při každé změně `detail`/`colors_n` server vrátí posterizovaný obrázek
   (PNG, do 0,5 s, endpoint `tools/art/poster`), stránka ho ukáže vedle originálu dřív, než se staví 3D; 3D náhled až
   na „Sestavit“ nebo po 1,5 s klidu. Kontury do 3D zjednodušit (Douglas–Peucker 0,15 mm po zjednodušení oblastí; shapely
   není, napsat si vlastní) – cíl **pod 150 000 trojúhelníků** a **pod 8 s** u 8 barev na 160 mm.
6. **Tisknutelnost**: nejtenčí stěna každé desky ≥ 2 × tryska (`shape2d.printability`), jinak oblast rozšířit nebo
   sloučit; varování `edit.art.warn.*` v jazyce zákazníka; `stack` režim stejné zjednodušení (schody po barvách).
7. **Texty** (`lang/src/art_painting.json` → `edit.art.*`): volba vzhledu, posuvník detail, věta o tom, kolik oblastí a
   barev vzniklo, tip „fotky a malby vypadají nejlépe s 5–6 barvami a detailem 2–3 mm“.

## 2. Soubory a pravidla

- Worktree `C:\matplace-colorpick-wt`, větev **`feature/art-painting`** z main, dokument `docs/Y.md` (nový oddíl).
- `engines/python/shape2d.py`: `colors()` používají i přívěsky a podtácky (`shape_kinds`) – **výchozí chování beze
  změny**; nový režim jen přes volby (`look`, `detail`, `min_area`, `protect_accents`) nebo nová funkce `poster()`,
  kterou `colors()` zavolá, když je `look = painting`. `art_tool.py`, `resources/js/calc/art.ts`,
  `tools/filament_art.blade.php`, `EditApiController` (artPreview + nový poster), `ArtGenerator.php` – patřily
  session 3, která neběží; smíš do nich.
- Testy: `FilamentArtTest` (nebo stávající) – syntetický „obraz“ (šumové přechody + pár sytých skvrn) dá ≤ 300 oblastí,
  ≤ 150 k trojúhelníků, syté skvrny přežijí jako vlastní barva; grafika z knihovny dává stejné výsledky jako dnes
  (hash/objem); poster endpoint do 0,5 s; `tsc`, `build`, `pint`, `check_preview_colors.mjs` na filament_art.
- V prohlížeči: Hvězdná noc (`docs/img/filament-art/zdroj-hvezdna-noc.jpg`), jedna fotka psa, jedno logo; snímky
  před/po do `docs/img/filament-art/`.

**Hotovo =** Hvězdná noc dává 8 desek s velkými čistými plochami, měsíc a hvězdy žluté, cypřiš vpředu, stavba pod 8 s,
2D náhled hned; grafika beze změny; testy, Y.md.

## 3. Co není v zadání

- Nové fotky karet (E později). HueForge-styl (průsvitné vrstvy podle tloušťky) – jiný nástroj, případně později.
