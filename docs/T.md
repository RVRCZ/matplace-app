# T: papel picado – portrét z fotky (session B)

Větev `feature/papel-photo` (z `main` cee51b1, 10. 10. 2026), zadání `docs/prompts/papel-foto.md`, předloha
https://stlbuddy.com/photo-to-papel-picado (pět snímků v `docs/img/stlbuddy-papel-*.png`). Nástroj `/tools/papel-picado`
(druh `papel`, session 1, `docs/P.md`) uměl vyříznutou siluetu. Teď má druhý režim, **Portrét**: fotka tváře ve dvou
barvách na pevném podkladu, v prolamovaném rámu, s koncem na farmě (jedna výměna filamentu) nebo ve stažení.

Nic se netisklo a na serveru jsem nebyl. Co je ověřené a čím, je v sekci 6.

## 1. Co vzniklo

### Režim Portrét (`treatment = portrait`)

- **Geometrie** (`creative_kinds._papel_portrait`). Celý panel je podkladová deska `body` tloušťky `base` (1–3 mm,
  výchozí 2). Na ní leží vrstva `details` výšky `relief` (0,3–1,2 mm, výchozí 0,6): celý okraj kolem okna a tmavé plochy
  obrázku v okně. Díry okraje (vzor, dírky zoubků, otvory na šňůru) jdou skrz obě vrstvy. Výška je `base + relief`.
  Portrét nepotřebuje spojky, `_papel_ties` se pro něj nevolá.
- **Díly a barvy.** `ParametricGenerator::partsOf('papel')` vrací `['body', 'details']` jen v režimu portrét. Nástroj
  vrací `notes.parts`, `notes.paint`, `notes.part_colors` a `notes.color_changes = [{z: base, part: 'details', code, hex}]`
  (plus `color_change_mm` pro starší čtenáře). Výchozí barvy: podklad nejsvětlejší a rám nejtmavší cívka z
  `ParametricGenerator::spools()`; volba návštěvníka (`part_colors`) vyhrává. Stejná cívka pro oba díly = žádná výměna.
  `create()` ukládá `part_colors`, `color_changes`, `multi_material = false` a nepočítá `parts_bbox` (díly leží na jedné
  desce). Farma návrh nebere jako díly k tisku zvlášť (`OrderService::ASSEMBLED` `papel` neobsahuje, test to hlídá).
- **Jedno těleso** (`all`, STL a cena) = panel v plné výšce, do kterého jsou zapuštěná světlá místa
  (`back.extrude(base + relief) − light.extrude(…)`). Je to třikrát rychlejší než sjednocení dvou vrstev a nevznikají
  plochy ležící na sobě. Objem `body + details` se s ním shoduje na 1 mm³ (test).

### Fotka → kresba ve dvou barvách (`engines/python/papel_portrait.py`)

Nový modul, `_papel_photo` ho volá, když dostane `look` (parametry portrétu). Režim silueta jde stejnou funkcí jako
dřív a dává stejné výsledky (testy session 1 prošly beze změny).

1. `subject()` – kdo je na fotce. PNG s vlastní průhledností je sám sobě maskou. Jinak `rembg` (u2net), když je
   zaškrtnuté „Oddělit postavu od pozadí“ a server ho má. Maska se ukládá do `tempdir/matplace-papel/<sha1>.png`
   (klíč: cesta, velikost, čas souboru), protože posuvníky se ptají na tutéž fotku mnohokrát; soubory starší dvou dnů
   se při zápisu mažou. Když `rembg` chybí nebo spadne, `notes.rembg = false` a fotka se použije celá.
2. `lay()` – fotka v okně. Ořez pod rameny (`trim`, od spodního okraje postavy, když ji známe; fotka se pak ořízne na
   postavu s malým okrajem nad hlavou), vsazení do okna (pokrytí, tvář nahoře), kontrast počítaný jen z postavy,
   velikost, otočení, posun. Vše v rastru na mřížce `PAPEL_PX`, takže po zvětšení nebo zmenšení mají čáry pořád
   tisknutelnou šířku.
3. `split()` – tmavá a světlá. Obyčejný práh dělá z tváře skvrnu (půlka je ve stínu). Obraz se proto nejdřív zostří
   proti vlastnímu širokému rozostření (XDoG): oči, rty, brýle a prameny vlasů vyjdou jako čáry, velké tmavé plochy
   zůstanou plné. `detail` řídí jemnost čtení i nejmenší zachovaný kousek (2,4 → 1,2 mm), `darkness` posouvá práh
   od Otsuovy hodnoty.
4. Úklid: otevření a zavření čtvercem o straně dvou tiskových stop (0,9 mm), ostrůvky a dírky pod limitem pryč.
5. Obrys se netrasuje po buňkách (schody), ale mezi nimi (`shape2d.mask_outline`), takže šikmá čára je čára.
6. `preview()` – výsledek jako jednobitové PNG do 150 px v `notes.preview` (base64, 1,3–1,9 kB).

Když portrét nevyjde (pod 2 % nebo přes 90 % tmavé), vrací se chyba `portrait_blank` s radou, co zkusit. Portrét
odsunutý nebo zmenšený návštěvníkem chybu nedostane nikdy (v rohu okna může zbýt jen kabát; to není špatná fotka).

### Pozadí kolem postavy (`backdrop`) – navíc proti zadání

Zadání popisuje světlé okno s tmavou kresbou. Předloha to ale dělá jinak: postava je světlá, **pozadí kolem ní je
tmavé a posázené květy**, kterými prosvítá podklad (viz `docs/img/stlbuddy-papel-frame.png`). U světlovlasého člověka
na světlém okně hlava nemá obrys; na tmavém poli ho má vždy. Proto jsou obě varianty:

- `plain` (výchozí v API, přesně podle zadání): světlé okno, tmavá kresba. Postava oddělená od pozadí dostane tenký
  obrys, protože se čte proti bílé.
- `pattern`: tmavé pole kolem postavy (`ground()`), v něm výřezy vzoru okraje na pevné roztřesené mřížce
  (`scatter()`, hustotu řídí `density`). Kde je postava u kraje tmavá (vlasy, kabát), zůstane mezi ní a polem světlá
  linka. Potřebuje masku; bez ní se chová jako `plain`.

**Stránka při přepnutí na portrét volí `pattern`.** Je to jediná věc, kde se výchozí vzhled liší od textu zadání;
změna je jeden řádek (`PAPEL_MODES.portrait.backdrop` v `param.ts`).

### Kresby z knihovny

Barevná kresba (SVG s aspoň dvěma barvami, `_papel_drawing`) se v režimu portrét čte jako fotka s vlastní
průhledností; jednobarevná silueta zůstává obrysem. Do knihovny přibyla **kreslená tvář** `lib:colour/portrait-woman`
(vlastní kresba z geometrie, CC0, `engines/artwork/colour/_draw.py`, řádek v `SOURCES.md`, názvy cs/en/es). Je to
obrázek, na kterém se portrét ukazuje, aniž by v repozitáři byla fotka cizího člověka.

### Rám

- Vzor `folk` („lidové květy“): květ z pěti kapek kolem tečky, mezi květy lístky (`_papel_unit`: `folk`, `sprig`).
- `density` (0,2–1): rozestup vzoru v pásu; 0,5 je rozestup, jaký byl vždy.
- `border_mm` (6–30, výchozí 12): šířka okraje. Když parametr chybí (starý návrh, API bez něj), platí dřívějších
  13 % kratší strany; `clean()` ho proto nedoplňuje.
- `scallop` zůstal zaškrtávátkem (uložené návrhy a testy session 1), k němu volba `scallop_edge`: `bottom` (dřívější
  zoubky pod panelem) nebo `all` (zoubky po celém obvodu, jeden v každém rohu, dírka v každém). Zoubky po obvodu jsou
  **součástí zadaného rozměru**: panel 190 × 190 je 190 × 190 i s nimi.
- Oprava v `_papel_ties`: rámem je kus, který sahá přes celý panel, ne kus s největší plochou. Úzký okraj se vzorem
  má méně papíru než lebka v něm a celý rám se dřív ztratil (projevilo se až s `border_mm`).
- Rám bez obrázku: v režimu portrét jde postavit panel s prázdným oknem (destička na nápis). Silueta bez obrázku
  dál vrací 422.

### Umístění

`portrait_scale` (0,5–1,5), `portrait_x`, `portrait_y` (mm od středu okna, střed obrázku zůstává v okně),
`portrait_turn` (±45°). Co přesáhne okno, se ořízne. V náhledu je rámeček `Viewer.setFrame` (tažení = posun, roh =
velikost, úchyt = otočení, po puštění přestavba), vedle čtyři pole s posuvníky a „Výchozí umístění“. `notes.portrait`
nese `box` (rámeček v mm souboru), `z`, `window`, `crop` a `dark_pct`. Umístění je v `tool_params`, jde do STL, 3MF
i na farmu.

### Stránka

- Šest kroků: **Fotka · Rám · Velikost · Umístění · Barvy · Tisk/stažení**. Obecná šablona `tools/param.blade.php`
  uměla čtyři; nové jsou `ParametricGenerator::SECTIONS` (krok navíc → za kterým stojí), `FIRST` (volba, kterou první
  krok začíná) a rozšířené `PLACE` (co do kterého kroku patří, v pořadí zápisu). Šablony `tools/_section`,
  `tools/_placed`, `tools/_papel_input` (dvě miniatury), `tools/_papel_placement`. Ostatní nástroje se vykreslují
  stejně jako dřív.
- Silueta / Portrét je první volba. Nahraná fotka (a barevná kresba z knihovny) přepne na portrét, SVG a silueta
  z knihovny na siluetu, dokud návštěvník nezvolí sám. Co nechal na výchozích hodnotách jednoho režimu (vzor, zoubky,
  pozadí, šířka okraje, rozměr 150 × 200 / 190 × 190), přejde na výchozí hodnoty druhého.
- Miniatury **Původní** a **Portrét**; portrét je obrázek ze serveru, tedy to, co se tiskne.
- Když server neumí oddělit postavu (`notes.rembg === false`), zaškrtávátko se vypne, zešedne a pod ním je vysvětlení.
- Barvy: řádky **Rám a detaily** a **Podklad portrétu** s oknem palety, pod nimi věta o výměně filamentu. Stažení:
  projekt 3MF pro OrcaSlicer a PrusaSlicer (dvě barvy, stávající exportér), STL celku, ZIP a díly zvlášť.
- Texty cs/en/es v `lang/*/param.php` (`papel.*`, `f.papel.*`, `c.papel.*`, `o.papel.*`, `flag.isolate`,
  `warn.portrait_*`, `error.portrait_blank`), SEO `lang/*/tools_seo/papel.php`, karta `lang/*/tools.php`.
- Ukázky a karta (`config/tools.php`): portrét z kreslené tváře (krémová + tmavě modrá), silueta cukrové lebky,
  rám bez obrázku. `ToolExamples` kreslí `papel` po dílech v barvách. Obrázky jsou v repozitáři.

## 2. Rozhodnutí a proč

1. **Tmavé pozadí se vzorem jako volba a jako výchozí stav stránky** (viz výše). Cíl zadání je být lepší než
   předloha; bez toho bychom byli horší u každé fotky se světlými vlasy.
2. **Průhledné PNG je maska.** Kdo nahraje výřez z jiného nástroje, nepotřebuje `rembg`. Zároveň tak jdou testy bez
   závislosti.
3. **`rembg` přímo z Pythonu, s mezipamětí.** Model se nahrává při každém volání (nový proces), první stavba s novou
   fotkou proto potrvá o 1–2 s déle, další jsou z mezipaměti. Až se slije `feature/tools-sell`, přepojení na
   `BackgroundRemover` je v `papel_portrait._cut_out`: místo `rembg.remove` zavolat `photo_cut.py` (nebo si nechat
   masku připravit v `ParametricGenerator::forTool` a poslat její cestu jako `mask_path`).
4. **API zůstává zpětně stejné.** Chybějící `treatment` = `cutout`, chybějící `border_mm` = 13 %, `scallop` je dál
   zaškrtávátko. Uložené návrhy a testy session 1 dávají totéž co před dneškem.
5. **Chyba `portrait_blank` místo `image_blank`.** Text `image_blank` je ve starém `lang/*.json` a radí „Obrátit
   světlé a tmavé“, což u portrétu nepomáhá. Nový kód má vlastní text v `lang/*/param.php`.
6. **Náhled portrétu je malý.** Jde v hlavičce `X-Model-Meta` vedle ostatních údajů; celá hlavička má 2,3–2,8 kB.
   Proto 150 px a jeden bit, ne 300 px z textu zadání.
7. **Výchozí šířka okraje.** Pole má výchozích 12 mm (zadání). Stránka se otevírá jako silueta a ta dostane 20 mm,
   protože tenký list s úzkým okrajem je křehký; portrét má 12 mm.
8. **Mřížka `PAPEL_PX = 280` zůstala.** U panelu 190 mm je buňka 0,56 mm a nejtenčí čára 1,1 mm. Jemnější kresba by
   znamenala hustší mřížku a pomalejší náhled.

## 3. Lepší než předloha: co je hotové

| | předloha | my |
|---|---|---|
| cena | 7 kreditů za stažení | zdarma, bez registrace |
| tisk | jen stažení | tisk u nás, farma předvybere světlou a tmavou cívku |
| barvy | dvě volné barvy | cívky farmy, výměna ve výšce v 3MF i na farmě |
| pozadí | tmavé s květy | tmavé se vzorem, nebo světlé |
| vzor | květy, hustota | sedm vzorů, hustota, zoubky dole nebo dokola, otvory na šňůru |
| umístění | panel Move / Rotate / Scale | jeden rámeček dělá všechno najednou, čísla vedle |
| režimy | portrét | portrét i vyříznutá silueta se spojkami |
| ukázky | – | tři ukázky a texty v cs/en/es |
| jemnost | – | varování `portrait_fine`, když je přes 25 % ploch užších než dvě stopy trysky |

Náhled pod 1 s je splněný těsně, viz sekce 4. Varování o jemnosti je napsané, ale na šesti zkoušených fotkách se
nespustilo ani jednou (úklid tenké čáry odstraní dřív); beru ho jako pojistku, ne jako ověřenou funkci.

## 4. Rychlost

Lokálně (Windows, 10. 10. 2026, počítač zároveň pouštěl testy jiné session), celé volání `param_tool.py` včetně
startu interpretu, panel 190 × 190 mm s lidovým rámem a zoubky dokola:

| vstup | čas | trojúhelníků | STL |
|---|---|---|---|
| fotka JPG, bez oddělení | 0,8–1,0 s | 51 tis. | 2,5 MB |
| výřez PNG, tmavé pozadí | 0,9–1,0 s | 57 tis. | 2,8 MB |
| kreslená tvář z knihovny | 0,95–1,1 s | 52 tis. | 2,6 MB |
| silueta lebky 150 × 200 (jako dřív) | 0,45 s | 32 tis. | 1,6 MB |
| silueta z fotky 150 × 200 (jako dřív) | 0,7 s | 27 tis. | 1,3 MB |

Z toho 0,57 s je samotný interpret a knihovny (numpy, PIL, scipy.ndimage, skimage, manifold3d); čtení fotky a geometrie
jsou dohromady asi 0,35 s. Na Linuxu bývá import rychlejší, na serveru jsem neměřil. `rembg` k tomu přidá 1–2 s
jen při první stavbě s novou fotkou.

## 5. Testy

`tests/Feature/PapelPortraitTest.php`, osm testů:

- stavba: výška, díly, objemy (`details` < `body`, součet = celek), `color_changes`, barvy, náhled, bez spojek;
- uložený návrh: `tool_params`, `ModelFile::colorChanges()`, projekt 3MF s jedním `M600` ve vrstvě nad podkladem,
  farma návrh nebere po dílech, stejná cívka = bez výměny;
- fotka: `rembg` vypnutý proměnnou `PAPEL_REMBG=off` (`notes.rembg === false`, varování), ořez, obrácení, světlo
  a stín, jemnost, prázdný obrázek = 422 s textem ve třech jazycích;
- oddělená postava (PNG s průhledností jako falešná maska): světlé a tmavé pozadí, hustota, kreslená tvář z knihovny;
- umístění: rámeček, posun v náhledu, velikost, otočení, nic neopustí okno;
- rámy: šest kombinací při 80 × 80 i 250 × 250, zoubky v rozměru, šířka okraje, rám bez obrázku, silueta s novými
  rámy a s dřívějším okrajem;
- stránka: šest kroků v pořadí, pole, háčky skriptu, tři jazyky;
- farma: `/farm?file=…` zaškrtne bílou cívku jako první barvu a černou pro výměnu, zakázka nese `T<slot>` ve 2 mm.

`PapelPicadoTest` (session 1) prochází beze změny.

## 6. Co není ověřené

- **Nic se netisklo.** Čitelnost kresby 0,6 mm ve dvou barvách, soudržnost úzkého rámu s lidovými květy a zoubky
  s dírkou 2,7 mm jsou jen z náhledu.
- **`rembg` na serveru.** Lokálně není; kvalitu jsem ladil ve zkušebním prostředí mimo repozitář (rembg 2.0.85,
  onnxruntime 1.31), z něj jsou masky šesti fotek. Na serveru neověřeno: verze, jestli `new_session("u2net")` najde
  model v `/opt/matplace-py/u2net` (nastavuji `U2NET_HOME` na `<sys.prefix>/u2net`, když proměnná chybí a složka
  existuje), jestli smí PHP proces psát do dočasné složky, čas první stavby.
- **Velikost hlavičky.** `X-Model-Meta` má u portrétu 2,3–2,8 kB. Nevím, jaký limit má nginx na serveru
  (`fastcgi_buffer_size`); obrázkové nástroje session 1 posílají hlavičky podobné velikosti, takže to nečekám, ale
  neviděl jsem to.
- **Prohlížeč.** Stránku jsem prošel v headless Chromu: otevření, přepnutí režimu, znovuotevření uloženého portrétu,
  tažení rámečku skutečnou myší. Neklikal jsem nahrání fotky přes okno obrázků (větev `papelPicked`), zešednutí
  zaškrtávátka, mobil, Firefox ani Safari.
- **3MF ve sliceru.** Test čte `custom_gcode_per_layer.xml`; v OrcaSliceru ani PrusaSliceru jsem projekt neotevřel.
- **Fotky.** Šest volných portrétů z Pexels (licence Pexels, čísla 220453, 415829, 614810, 733872, 774909, 1239291),
  jen ve zkušební složce, v repozitáři nejsou. Fotka z mobilu v protisvětle, skupinová fotka, dítě, zvíře: nezkoušeno.
- **Miniatura „Původní“** se u znovu otevřeného návrhu neukáže (uložená fotka nemá adresu), jen „Portrét“.
- **Celá sada testů** běžela 10. 10. 2026 (523 testů, 32 minut): 522 prošlo. Jediné selhání,
  `ArtworkLibraryTest::test_my_pictures_are_only_mine`, způsobil můj test farmy, který nahrál fotku jako přihlášený
  uživatel a nechal ji na disku. Opravil jsem svůj test (nahrané obrázky po sobě maže) a testy knihovny a papel pak
  prošly; celou sadu jsem po té opravě znovu nepouštěl.

## 7. Nasazení (řídící session)

- Žádná migrace, žádný nový balíček PHP ani npm. `npm run build` (mění se `param.ts`).
- Python: žádná nová závislost. `rembg` je volitelný; bez něj nástroj funguje a stránka to řekne.
- Po nasazení projít `/tools/papel-picado`: nahrát fotku tváře, zkontrolovat, že zaškrtávátko „Oddělit postavu od
  pozadí“ zůstalo zapnuté a pozadí je tmavé s květy; pohnout rámečkem; „Pokračovat“ a na farmě vidět blok druhé barvy.
- **Slití se session D** (`feature/color-picker`, volná barva místo cívek; v `main` zatím není). Kód portrétu čte
  barvu dílu jako `{code, hex}` a stránka ji bere přes `colorOf()` a `pickColor()`, takže s hexem místo kódu cívky
  počítá. Po slití D změnit jeden řádek v `ParametricGenerator::forTool`: u `papel` místo `self::spools()` dát
  `self::freeColors($clean)['palette']`, aby výchozí barvy portrétu byly barvy, ne cívky. Řádky, které D mění
  (`forTool` u rodiny `shape`, nápověda `toolpage.color.builtin` v šabloně), jsem nechal být, konflikt nečekám.
- V repozitáři jsou omylem sledované tři soubory `engines/python/__pycache__/*.pyc` (ne ode mě). Každý běh je
  přepíše; necommitoval jsem je. Stálo by za to je odebrat a složku přidat do `.gitignore`.
- Sdílené soubory, do kterých jsem sáhl: `ParametricGenerator.php` (konstanty `papel`, `PAPEL_PLACE`, `SECTIONS`,
  `FIRST`, `partsOf`, `clean`, `forTool`, `create`), `tools/param.blade.php` (kroky navíc, první volba), `param.ts`
  (větve `papel`), `creative_kinds.py` (oddíl papel picado), `ToolExamples.php` (jedno slovo), `config/tools.php`
  (ukázky `papel`), `lang/*/param.php`, `lang/*/tools.php`, `lang/*/artwork.php`, `engines/artwork` (jedna kresba).
  Farma, exportér 3MF a `viewer.ts` beze změny.

## 8. Co by šlo dál

- Girlanda květů přes spodek portrétu, jak ji má předloha (květy přes ramena).
- Jemnější mřížka pro portrét (420 buněk) s měřením, co to udělá s časem náhledu.
- Maska připravená jednou v PHP (`BackgroundRemover`) a poslaná cestou, místo `rembg` v každém procesu.
- Miniatura původní fotky i u uloženého návrhu.
