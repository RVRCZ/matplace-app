# AB. Forma na odlévání, kolo 2 (13. 10. 2026, session B)

Zadání: `docs/prompts/forma-kolo2.md`. Větev `feature/mold-round2` (z main 3bfd7c5, průběžně merge main), worktree
`C:\matplace-papel-wt`. Kolo 1 je v `docs/Q.md`. Pořadí: nástroj → stránka → video (video čeká na Romanův tisk formy
a odlitek, viz §7).

## 1. Co nástroj umí nově

Vše v `engines/python/mold2.py` (nový, ~500 řádků) a napojení v `mold_tool.py`; parametry přes
`App\Domain\Tools\MoldGenerator` (konstanty `VARIANTS`, `VENTS`, `KEY_TYPES`, `FUNNELS`, `CORE_WALLS`, `CLEARANCES`;
`WALLS` rozšířeny o 15 a 20 mm) a validaci v `ModelFileController::mold`.

1. **Odvzdušnění** (`vents: auto|off`, výchozí auto). `mold2.pockets()` najde kapsy dutiny: lokální extrémy ploch
   obrácených proti směru lití (síť povrchu po buňkách XY, bez nalévacího otvoru). Dělená forma: kanálky Ø 2,5 mm
   vrtané v dělicí rovině (u dvoudílné stojaté formy) nebo kolmo ke stěně, ven. Silikonová: kolíky Ø 2,5 mm na
   modelu (`vent_pins`), které v silikonu nechají kanálek; po odlití se nálitek odřízne. Počet jde do reportu (`vents`),
   stránka napíše „Odvzdušnění: n kanálků“ nebo „není třeba“. Busta z testů: 6 kapes (nos, brada, uši).
2. **Silikonové provedení** (`variant`, jen `type=silicone`):
   - `sleeve` (výchozí, kolo 1): podstavec + objímka.
   - `shell`: tištěná skořepina kopíruje model v odstupu stěny: silikon = offset(stěna) ∩ podlaha − víko nahoře,
     skořepina = offset(stěna + 2,4) − silikon − komín Ø 12 nahoře, rozříznutá dělicí rovinou na dvě půlky; podstavec
     s modelem. Offsety voxelovou distanční transformací (`surface_grid` + `edt`), gaussovské vyhlazení σ = 1,
     marching cubes, oprava orientace, simplify. Report: `silicone_ml`, `silicone_box_ml` (kolik by vzala objímka),
     `saving_pct`. Busta: 113,9 ml proti 246,7 ml objímky, úspora 54 %. Varování `shell_supports` (půlky s podpěrami).
   - `tray`: vanička v jednom kuse (podlaha + stěny + model), pro reliéfy a plochou zadní stranu.
   - `two_part`: model otočený tak, aby dělicí rovina byla z = 0, dělicí deska s vyříznutým obrysem (offset 0,3)
     a kuželovými klíči (do 4, z pásu mezi obrysem a vnitřní stěnou boxu), čtyři nožky uprostřed stran, box s drážkou;
     silikon na obě půlky (`silicone_ml`). 3 kusy.
3. **Jádro** (`core: bool`, `core_wall` 3–8 mm, výchozí 5): model zmenšený o stěnu (voxelový offset dovnitř) se stopkou
   do nalévacího otvoru (pata.offset(−stěna) vytažená o STEM + stěna + 2) a límcem. Přidá se jako další kus na desku
   (u objímky vedle podstavce). Report `core_ml`, `cast_ml = resin_ml − core_ml`; když jádro nevznikne (tenký model
   nebo malá pata) varování `core_none`. Busta se stěnou 5: jádro 34,5 ml, odlitek 46,1 ml. Nenabízí se u vaničky.
4. **Objemy v ml** už v analýze (`resin_ml`, `silicone_sleeve_ml`, `silicone_shell_ml` při 8 mm, `pockets`;
   `ANALYSIS = 3`, starší uložené analýzy se přepočítají) i v reportu (`cast_ml`, `core_ml`, `mold_cm3`).
5. **Pro pokročilé**: `clearance` 0,1 / 0,15 / 0,2 / 0,3 mm (vůle klíčů), `keys_type` auto / ball / cone
   (`mold2.cone_keys_two` u dvou dílů), `funnel_at` bottom / top / side (nahoře zrcadlením modelu, z boku
   `mold2.side_opening`). Jen u tištěné formy.

Časy na bustě (60 × 52 × 78 mm, 4 080 trojúhelníků, Store Python): analýza 7,3 s; tištěná 2 díly ~10 s; skořepina
~30 s (voxely 0,5 mm); vanička 3 s; dvoudílná 12 s; jádro +8 s. Všechny výstupy vodotěsné (`StlTopology`).

## 2. Stránka (`tools/mold.blade.php`, `calc/mold.ts`)

- Nahoře **galerie pěti druhů** (`#mold-gallery`, 3 sloupce): obrázky z `config/tools.php mold.seo.examples`
  (`matplace:tool-examples mold`, stejná busta ve všech, `public/img/tool-examples/mold-1..5.png`), klepnutí nastaví
  typ + díly nebo provedení (`.mold-tile`, `aria-pressed`), bez modelu odkryje nahrání.
- Na telefonu je **panel první**: `@push('head')` se stylem `@media (max-width: 1023.98px) { #tool-panel { order: 1 } #tool-stage { order: 3 } }`
  (název + věta + galerie + nahrání nad prohlížečem, `docs/img/mold2/phone-top.png`).
- Nastavení: Provedení formy na silikon (s nápovědou pod výběrem), Odvzdušnění, Vnitřní jádro + stěna odlitku
  (jen silikon, ne vanička), `<details>` Pro pokročilé (vůle, typ klíčů, poloha nálevky; poslední dvě jen u tištěné).
- Analýza: řádek objemů („Odlitek ≈ 80,6 ml. Silikon: objímka ≈ 246,7 ml, skořepina ≈ 113,9 ml. Míst, kde by zůstal
  vzduch: 6.“). Report: věta podle provedení, objemy, jádro, odvzdušnění, podpěry, postup (každé provedení má svůj).
- Ověřeno headless Chromem (`C:\tmp\mold_e2e.js`): krychle přetažená na stránku → analýza → klik na dlaždici
  skořepiny → jádro → stavba → report; bez chyb v konzoli (`docs/img/mold2/phone-result.png`).

## 3. SEO a obsah (`lang/*/tools_seo/mold.php`, `_content.blade.php`)

- Title/description/H1 nově zmiňují čtyři provedení, odvzdušnění a objemy (description ≤ 160 znaků).
- **3 kroky** (nahrát → zvolit typ → vytvořit a objednat), **6 FAQ** (podřezy, rozdíl provedení, odvzdušnění,
  jádro, kolik silikonu, co nezpracuje + cena), **5 popisků** ke galerii, tabulka **„Který typ formy zvolit“** (klíč
  `which` → obecná tabulka v `_content.blade.php`, nadpisy `tools.which.*`).
- **Obecná sekce videa** (`_content.blade.php`): `config/tools.php '<tool>' => ['video' => ['youtube' => id, 'poster' => cesta, 'uploaded' => datum, 'seconds' => n]]`
  → nadpis „Jak to vypadá“, náš poster, tlačítko Přehrát; iframe `youtube-nocookie.com` se vloží až po kliknutí;
  JSON-LD `VideoObject` (`Schema::video`). Bez `video` v configu se nic nevykreslí (zatím u žádného nástroje).
- **OG / karta**: `matplace:tool-examples mold --card` kreslí `public/img/tools/mold-800.jpg` (+ webp) z prvního
  příkladu (tištěná forma kolem busty); `OgController::tool` ho použije pro `/og/tool/mold.png` (ověřeno 200, PNG).
- Texty: `lang/src/mold5.json` (50 klíčů) a `mold6.json` (postup skořepiny), `python scripts/lang_add.py`.

## 4. Testy

`MoldToolTest` 12 testů: kolo 1 beze změny + stránka (galerie, pořadí prvků, tabulka, styl panelu) + analýza objemů
a kapes + tištěná forma s odvzdušněním, 422 pro špatné hodnoty, kuželové klíče, vůle, nálevka nahoře/z boku,
objem bez odvzdušnění větší + skořepina (3 kusy, úspora > 30 %, varování podpěr) + vanička (1 kus) a dvoudílná
(3 kusy, ≥ 2 klíče) + jádro (dutý odlitek, 422 pro stěnu 7). `SeoTest` a `ToolPageTest` zelené (18/18).
Fixture `tests/fixtures/mold/bust.stl` (procedurální busta, 204 kB).

## 5. Rozhodnutí a odchylky od zadání

- Skořepina se vyhlazuje (gauss σ = 1 voxel), jinak byla kostkovaná; stěna skořepiny je 2,4 mm (jako objímka).
- Dvoudílná forma má nožky uprostřed stran (v rozích u zaoblené desky visely ve vzduchu).
- Vůle klíčů a typ klíčů jen u tištěné formy; u silikonu jsou klíče v desce vždy kuželové.
- `Manifold.compose` dotýkajících se kusů dává jedno těleso → mezery 0,12 mm na desce.
- Galerie je ve 3 sloupcích i na desktopu: panel má 340 px, pět dlaždic bylo nečitelných.

## 6. Co není ověřené

- Nic se netisklo ani neodlévalo: rozměry kanálků (2,5 mm), kolíků a klíčů (R 3, vůle 0,15) jsou podle praxe, ne
  podle zkoušky. Roman měl tisknout formu a odlít; podle toho se doladí.
- Skořepina u modelů s velmi tenkými částmi (voxel 0,5 mm) může části ztratit; jádro u tenkých modelů nevznikne
  (varování).
- Odvzdušnění u 3–4dílné tištěné formy: kanálky jdou kolmo ke stěně (ne v dělicích rovinách klínů).

## 7. Video (čeká)

Plán podle zadání: 1 min = průchod nástrojem (headless Chrome, CDP screencast, `scripts/tool_video.mjs`), otočení
formy (`render_tool.py` s parametrem yaw), časosběr z farmy (po Romanově tisku) + Romanovo odlévání; sestavení
`imageio_ffmpeg`; na YouTube přes `FarmVideos` po schválení; pak `config/tools.php mold.video` a `docs/social/mold.md`.
Sekce na stránce i JSON-LD jsou hotové, stačí doplnit id.
