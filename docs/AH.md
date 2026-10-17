# AH · Zákaznický audit, etapa 1 na stránkách nástrojů (session 3, 17.–18. 10. 2026)

Zadání: `docs/prompts/audit-nastroje.md`, podklad `docs/AUDIT.md` (body 2, 5, 8, 11, 13; Romanovy odpovědi 17. 10.:
popisky podle auditu, „Náš výběr“). Navazuje na kolo 2 (`docs/AF.md`). Větev `feature/audit-tools`, worktree
`C:\matplace-edit-wt`. Kalkulaci, zakázku, platbu a hlavní stránku dělá řídící session; co jsem v nich našel, je v §5.

## 1. Popisky akcí říkají, co se stane (audit bod 5)

| kde | dřív | teď (cs / en / es) | co se stane |
|---|---|---|---|
| hlavní akce stránky nástroje (`param.go`) | Pokračovat / Continue / Continuar | **Spočítat cenu a možnosti tisku** / Get the price and print options / Calcular precio y opciones de impresión | otevře kalkulaci s tímtéž modelem (další nastavení, nic placeného) |
| menu stažení (`toolpage.download`) | Stáhnout / Download / Descargar | **Stáhnout model** / Download the model / Descargar el modelo | dostanu soubor (STL) nebo projekt pro slicer |
| věta pod tlačítky, režim „stáhnout“ (dnes pro všechny kromě admina) | „…přesnou dobu tisku a spotřebu materiálu…“ | „V kalkulaci uvidíte přesnou cenu a dobu tisku a stáhnete si model nebo projekt pro svou tiskárnu. Bez registrace.“ | slibuje jen to, co ukáže další krok |
| věta, režim „farma“ | „…Výtisk si objednáte u nás, nebo si stáhnete soubor…“ | „V kalkulaci uvidíte přesnou cenu a dobu tisku. Výtisk si pak objednáte u nás, nebo stáhnete model pro svou tiskárnu.“ | placený krok je až v kalkulaci |
| fotkové nástroje (Vytvořit model, Vytvořit figurku, Vytvořit mapu) | beze změny | beze změny | vytvoří 3D model, zdarma s limitem napsaným nad tlačítkem (AF §3) |

Na stránce nástroje jsou tedy jen dva výsledky ze tří: **soubor** (Stáhnout model) a **další nastavení** (Spočítat
cenu…). Placený krok začíná až v kalkulaci („Objednat tisk“, řídící session). Na telefonu je teď hlavní akce nahoře
přes celou šířku karty a „Stáhnout model“ pod ní: delší popisek by se vedle druhého tlačítka na 360–390 px nevešel.

Prázdný prohlížeč nástrojů se souborem (úpravy modelu, oprava, kontrola, forma) je teď plocha pro nahrání: klepnutí
otevře výběr souboru z panelu, přetažení soubor předá (parametr šablony `fileInput`). Dřív tam stála věta „Přetáhněte
sem model nebo klepněte a vyberte“, ale prohlížeč na klepnutí ani na přetažení nereagoval.

## 2. Zachování zadání a stavy (audit body 5 a 11)

**Jak to bylo:** každý nástroj s formulářem ukládal nastavení do `localStorage` (`Stage.track`), ale po návratu z
kalkulace nebo obnovení stránky jen nabídl tlačítko „Obnovit poslední nastavení“. Formulář se vrátil na výchozí
hodnoty a stránka nástroje se souborem (úpravy modelu, forma, mapa, reliéf) ztratila nahraný model. Busta a mazlíček
si nepamatovaly nic, dokud nebyl model v adrese (`?generation=`). Neúspěšné generování nechalo neplatný odkaz v
adrese a obnovení stránky ukázalo znovu chybu.

**Teď:**
- **Návrat a obnovení stránky:** `Stage.track` pozná návrat (`performance` navigace `back_forward` nebo `reload`) a
  nastavení obnoví samo, bez klepnutí. Platí pro všechny nástroje s formulářem (param, filament art, úpravy modelu,
  reliéf) a nově i pro bustu a mazlíčka (druh, styl, podstavec, velikost, jméno, věnování; fotky a souhlas ne).
  Stránka otevřená odkazem s předvolbou nebo s modelem z adresy dál jen nabízí tlačítko, aby adresa vyhrála.
- **Model v adrese:** úpravy modelu (`edit.ts`), forma (`mold.ts`), mapa (`map.ts`) a reliéf zapíšou nahraný nebo
  vytvořený model do adresy (`?from=<uuid>`, `Stage.keep`). Krok zpět z kalkulace i obnovení stránky ho otevřou znovu,
  u reliéfu i s modelem na jevišti. Všechny tyto nástroje `from` už uměly číst (z kalkulace „Upravit“), jen ho samy
  nezapisovaly.
- **Fotky:** prohlížeč soubor z `<input type=file>` po obnovení stránky nevrátí (kvůli bezpečnosti). Při kroku zpět ho
  vrátí jen ze zásobníku stránek (bfcache): tehdy stránka nic nenačítá a zůstane celá. Busta a mazlíček mají
  hotový model v adrese, reliéf má v adrese model (`?from=`) a říká „Nastavení i model jsou zpět. Fotku neukládáme: pro nový model ji vyberte prosím znovu.“
- **Neúspěch:** busta, mazlíček i reliéf nechají formulář, fotky i volby. Hláška říká, že zůstaly, a nabízí zkusit
  to znovu nebo jinou fotku. Neúspěšná busta zmizí z adresy, takže obnovení stránky začne od formuláře.
- **Žádné technické detaily:** reliéf vracel při neznámé chybě enginu jeho vlastní text výjimky (anglicky, technicky).
  Teď vrací `relief.failed` a výjimku zapíše do logu. Odpověď serveru 500 („Server Error“, anglicky) už nástroje
  param, filament art a mapa návštěvníkovi neukážou: mají místo ní vlastní větu.
- **Stavy všech stránek** (prázdno, načítání, model, chyba) prošel skript na všech nástrojích z katalogu: §4.2.
- **Slovník (bod 11):** digitální výsledek je „model“ / „3D model“ / „soubor“ (Stáhnout model, „stáhnete si model nebo
  projekt“), fyzický „výtisk“ (Výtisk si objednáte u nás). „Zdarma“ stojí vždy u limitu: busta a mazlíček „Zdarma
  1 model denně bez účtu, 3 s účtem“ nad tlačítkem, reliéf „Zdarma a bez registrace“ (reliéf má jen ochranu proti
  zahlcení, žádný denní limit). Test `test_every_text_of_the_tool_pages_exists_in_all_three_languages` prochází
  všechny šablony nástrojů (texty v šabloně i seznamy textů předávané skriptům, 470+ klíčů) a hlídá, že žádný klíč
  nechybí v cs, en ani es.

## 3. Katalog (audit bod 8, část bez dat)

- **Náš výběr** nahoře: dárek se jménem, organizér, krabička, QR cedulka, busta z fotky. Pod nadpisem stojí „Vybrali
  jsme je ručně: s nimi se dobře začíná.“, aby bylo jasné, že nejde o data. Výběr se schová, když běží filtr nebo
  hledání, a filtr ho nepočítá. Nástroj, který se zrovna nenabízí (busta bez generátoru), z výběru vypadne.
- **Karty sjednocené:** fotka (ukázka), název, **„Zadáte: …“** (fotku / text / text nebo obrázek / odkaz nebo text /
  rozměry / 3D soubor / místo na mapě), popis a jedna akce (celá karta je jeden odkaz). Co nástroj chce, se čte z jeho
  dat (`App\Domain\Tools\ToolInput`): nástroje se souborem, textová pole a obrázek generátorů, pro pár nástrojů
  jménem. Nový nástroj se tak popíše sám. Na hlavní stránce zůstávají karty beze změny (řídící).
- **Synonyma v hledání** (`tools.synonyms` v cs, en, es): jmenovka = cedulka = štítek = visačka = tabulka, krabička =
  box = krabice = pouzdro, organizér = přihrádky, klíčenka = přívěsek, stojánek = stojan = držák, fotka = obrázek =
  portrét, QR = kód, dárek, busta = figurka = soška, hrnek = kelímek. Napsané slovo najde nástroje všech slov
  skupiny (od 3 písmen). Příliš obecná slova (jméno, nápis) jsem ze skupin vyřadil: „jmenovka“ jinak našla každý
  nástroj „se jménem“.
- **Prázdný výsledek** nabídne „Zrušit hledání a filtr“ a „Možná hledáte:“ s nástroji, které odpovídají aspoň jednomu
  ze slov (např. „raketa krabička“ → Organizér, Krabička, Světelný nápis). Když neodpovídá nic, nabídne Náš výběr.
- **Návrat z detailu drží filtr a hledání:** kategorie je v adrese (`#names`, jako dřív) a nově i hledaná slova
  (`?q=krabicka`). Krok zpět z nástroje i obnovení stránky ukážou seznam tak, jak ho člověk opustil.
- Štítek „Nejčastěji tištěné“ v katalogu není (jen na hlavní stránce, přejmenovává řídící).

## 4. Průchod webem (audit body 2 a 13)

Skript `scripts/review_round2.mjs` (rozšířený, bez balíčků, popis v hlavičce) na lokálním serveru s touto větví a
`main` 10245aa, 17.–18. 10. 2026. Šířky **360 × 800, 390 × 844** (telefon, 2×, dotyk), **768 × 1024** (tablet) a
**1280 × 800**, cs i en. Snímky jsou v `docs/img/audit/` (`<stránka>-<šířka>-<jazyk>-1.webp`, `…-zoom200`, `…-bar`,
`…-keys`, `flow-<cesta>-<šířka>-cs-<krok>.webp`), čísla v `docs/img/audit/facts.json`. Anglické snímky jsou jen
na 390 px, čísla pro všechny šířky.

### 4.1 Měření na hlavních stránkách (po opravách v této větvi)

| měření | jak | výsledek |
|---|---|---|
| vodorovný posun | `scrollWidth` > zadaná šířka | **žádný** na 360, 390, 768, 1280, cs i en (`/`, `/tools`, qr, cedulka, krabička, busta, mazlíček, reliéf, vydutí) |
| text 200 % | kořenové písmo 200 %, pak totéž | **před:** přetékalo 16 z 59 nástrojů (o 5–52 px: tlačítka Zpět/Vpřed vedle názvu, pole s jednotkou, `fieldset`, řádky vrstev skladače, tabulka pod formou) → **po: 0 z 59** |
| `h1` a věta na první obrazovce | celé v okně | ano na všech stránkách nástrojů a šířkách |
| hlavní akce na první obrazovce | `#tool-go` celé v okně | ano všude kromě **formy** (galerie druhů forem nad nástrojem; session B) |
| velikost hlavní akce | ≥ 44 × 44 px | Spočítat cenu… 294 × 48 (360), 324 × 48 (390), 290 × 48 (1280) |
| malé dotykové plochy na první obrazovce | odkazy a tlačítka < 44 px | **moje:** Zpět/Vpřed 36 → 44 px, „← Nástroje“ 20 → 44 px, „Pohled“ 36 → 44 px, chipy katalogu 34 → 44 px; zbývají čísla kroků (30 px, vědomě: jsou to záložky, druhá cesta je posun stránky; 44 px by udělalo z lepkavé lišty 100 px). **Ne moje:** celá hlavička (Nástroje, Cena ze souboru, Přihlásit, cs/en/es, Kč/€: 20 px vysoké), „Nahrát soubor“ a „Popsat slovy“ na `/` (38 px) |
| cookie lišta | `--bar`, co zakryje | 119 px (cs) / 139 px (en) na 360 px; na stránce nástroje **zakryje kartu akce** (Spočítat cenu…) u QR i busty, `h1` ne |
| klávesnice | 15× Tab, kde je fokus a jestli je vidět | 15/15 na `/`, `/tools`, QR, bustě a vydutí, 390 i 1280 px |
| chyby skriptu | `Runtime.exceptionThrown` | žádné na 59 stránkách nástrojů ani na `/` a `/tools` |

### 4.2 Všechny nástroje z katalogu (`--pages=catalogue`, 360 i 390 px)

59 stránek nástrojů (každý odkaz katalogu na `/tools/…` jednou). Stav po načtení: **41 s modelem** (generátory mají
ukázku od začátku), **18 prázdných**, žádná s chybou ani bez stavu. Prázdných
je všech 14 nástrojů se souborem (11 úprav modelu, oprava, kontrola, forma), busta, mazlíček, reliéf a mapa; u busty, mazlíčka a reliéfu je místo prázdna ukázka (AF §1).
Nástroje se souborem měly v prohlížeči větu „Přetáhněte sem model nebo klepněte a vyberte“, ale klepnutí ani
přetažení na prohlížeč **nic nedělalo** (vrstva měla `pointer-events: none`). Teď je to plocha pro nahrání (§1 dole). Na
360 i 390 px má každá stránka `h1` a větu celé na první obrazovce a žádná nepřetéká. Hlavní akce chybí na první
obrazovce jen u formy.

### 4.3 Cesty z auditu (bod 13)

| cesta | kroky | co se stane | snímky |
|---|---|---|---|
| soubor → cena → objednávka | `/`, nahrát kostku 20 mm (binární STL), čekat 25 s, hledat tlačítko objednávky | kalkulace `/c/<kód>`: „Přesný výpočet 13 min“, „Orientační cena tisku u nás: 120 Kč. Přesná cena podle barvy a tiskárny až při objednávce.“ **Tlačítko objednávky host nevidí** (farma pro hosty zavřená, `farm.public` false): cesta končí u ceny a stažení | `flow-file-price-390-cs-1`, `-1280-` |
| nástroj → kalkulace → objednávka | `/tools/qr`, „Spočítat cenu a možnosti tisku“, kalkulace, hledat objednávku | tlačítko ukáže „Připravuji model…“, za ~10 s kalkulace téhož modelu: 27 min, 120 Kč, materiál 15 g; objednávka jako výše | `flow-tool-price-390-cs-1…2` |
| přihlášení uprostřed objednávky | totéž jako host | **nešlo projít:** host tlačítko objednávky nemá, a tím ani výzvu k přihlášení (zapsáno jako omezení) | – |
| model → stažení | `/tools/box`, „Stáhnout model“ | menu: projekt OrcaSlicer/Bambu (3MF), projekt PrusaSlicer (3MF), celý model (STL); menu se otevře nad tlačítkem a překryje hlavní akci, dokud je otevřené | `flow-download-390-cs-1` |
| QR web | do pole napsat `https://matplace.com/tools` | náhled se přepočítá: 70 × 83,1 × 3,4 mm, 2 barvy, „Kód má 33 × 33 polí…“ | `flow-qr-web-*` |
| QR Wi-Fi | `WIFI:T:WPA;S:Domov;P:heslo 123;;` | náhled 37 × 37 polí, 1,9 mm; čitelnost hlídaná; typ Wi-Fi s poli pro síť a heslo zatím není (audit bod 9, volná session) | `flow-qr-wifi-*` |
| neplatný soubor | nahrát `broken.stl` (text) | soubor se přijme, kalkulace `/c/<kód>` ukáže **„Cena je odhad: přesný výpočet teď není k dispozici. Zkuste Přepočítat.“** a prázdné rozměry; neřekne, že soubor není 3D model | `flow-bad-file-390-cs-1` |
| překročený limit (fotka) | reliéf, fotka 16 MB, Vytvořit model | „Fotka je větší než 15 MB, zmenšete ji prosím.“ hned u tlačítka, nic se neodešle | `flow-photo-limit-*` |
| překročený limit (busta) | – | neprošlo: lokální generátor by spotřeboval kredity; hláška s odkazem na registraci je z kola 2 (AF §3) | – |
| neúspěšný výpočet | – | nešlo vyvolat bez zásahu do sliceru; text pro ten případ má řídící session (17. 10.) | – |

## 5. Nálezy podle auditu

Třídění: **chyba** (nefunguje nebo říká nepravdu), **překážka** (funguje, ale zdržuje nebo mate), **neověřený
návrh**. U každého je, kdo ho řeší.

| # | druh | co | kde | stav |
|---|---|---|---|---|
| 1 | chyba | prázdný prohlížeč nástrojů se souborem slibuje „klepněte nebo přetáhněte“ a nic nedělá | 14 nástrojů se souborem | **opraveno** (session 3) |
| 2 | chyba | text 200 %: 16 nástrojů přetéká do šířky | stránky nástrojů | **opraveno** (session 3) |
| 3 | chyba | reliéf při neznámé chybě enginu ukázal jeho anglický text; param, filament art a mapa ukázaly „Server Error“ | stránky nástrojů | **opraveno** (session 3) |
| 4 | chyba | krok zpět z kalkulace nebo obnovení stránky ztratilo zadání a nahraný model | stránky nástrojů | **opraveno** (session 3, §2) |
| 5 | chyba | neplatný soubor (text místo STL) skončí „Cena je odhad: přesný výpočet teď není k dispozici“, místo „tohle není 3D model“ | nahrání na `/`, kalkulace | **řídící** |
| 6 | překážka | cookie lišta na 360 px (119/139 px) zakryje na stránce nástroje kartu s hlavní akcí, dokud člověk nevybere | layout | **řídící** |
| 7 | překážka | hlavička na telefonu: všechny odkazy 20 px vysoké (Nástroje, Cena ze souboru, Přihlásit, jazyk, měna) | layout | **řídící** (audit bod 4) |
| 8 | překážka | kalkulace nemá vlastní `h1`: jediný `h1` stránky `/c/<kód>` je skrytý slogan „Od myšlenky k výrobku.“; čtečka ani vyhledávač nevidí, o jaký model jde | `calculator/index` | **řídící** |
| 9 | překážka | host v kalkulaci nemá „Objednat tisk“ ani výzvu k přihlášení (farma pro hosty zavřená, Roman 17. 10.) | kalkulace | vědomé rozhodnutí; až se farma otevře, projít cestu „přihlášení uprostřed objednávky“ |
| 10 | překážka | v kalkulaci zůstává u formy „Zdarma, pár vteřin“ (`mold.hint`); forma trvá desítky sekund | kalkulace | **řídící** (text) |
| 11 | překážka | forma: hlavní akce není na první obrazovce (galerie druhů nad nástrojem) | `/tools/mold` | **session B** |
| 12 | překážka | čísla kroků v panelu 30 px vysoká | stránka nástroje | vědomě ponecháno (§4.1) |
| 13 | neověřený návrh | „Náš výběr“ nahradit pořadím podle `events`, až bude dost dat (dnes 6–12 lidí denně) | katalog | etapa 2 |
| 14 | neověřený návrh | typ Wi-Fi v QR (pole pro síť a heslo) | QR | volná session (audit bod 9) |

## 6. Testy

- `ToolPageTest`: `test_the_button_leads_to_the_calculation_and_the_next_step_is_said_once` (nové popisky ve třech
  jazycích, věta pod tlačítky v obou režimech), `test_every_text_of_the_tool_pages_exists_in_all_three_languages`,
  `test_the_catalogue_starts_with_our_picks_and_says_what_each_tool_asks_for`,
  `test_the_empty_viewer_of_a_file_tool_takes_the_file`.
- `ToolsFlowTest::test_tool_pages_address_the_visitor_formally` čekal starý text karty busty („za minutu“), opraveno
  na „za 1 až 3 minuty“ (změna z kola 2).
- Běh: viz zpráva řídící session; `pint`, `tsc`, `build` čisté.

## 7. Známá omezení

- Vše ověřeno na lokálním serveru (stejný kód jako větev), ne na produkci. Generátor busty a mazlíčka jsem nespouštěl
  (kredity); obnovení jejich voleb po návratu je ověřené jen kódem a testem šablony, ne průchodem.
- Fotky po obnovení stránky prohlížeč nevrátí (§2); u kroku zpět je vrátí, jen když stránku drží v paměti (bfcache).
- Text 200 % měří skript zvětšením kořenového písma (`rem`); skutečné zvětšení textu v telefonu může zvětšit i
  písmo zadané v `px` (žádné jsem na stránkách nástrojů nenašel).
- Cesty přes objednávku a platbu (přihlášený zákazník, kredit, karta) skript neprochází: potřebují účet, farmu otevřenou
  pro zkoušku a testovací režim banky (řídící, audit bod 7).
