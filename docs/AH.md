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

## 8. Dialog „Přidat fotky“ u busty a mazlíčka (Roman, 18. 10. 2026; zadání §6)

Roman: „přidat mazání fotek, kdyby se klient spletl, a přidat do toho dialogu rovnou generovat, ať nemusí na konec
stránky.“ Větev `feature/photo-dialog` z `main` 2556c68. Soubory: `tools/_figure_drop.blade.php`, `figure.ts`,
`figure.blade.php`, `pet.blade.php`, `app.css`, `lang/*/toolpage.php`.

- **Dialog už není jedno velké tlačítko.** Je to karta s vlastními prvky: „Přidat fotky“ (klepnutí otevře výběr:
  nejdřív fotku zepředu, potom volné strany; přetažení je rozdělí stejně), náhledy, souhlas, řádek s limitem a časem,
  „Vytvořit figurku“ / „Vytvořit model“ a místo pro hlášku.
- **Křížek u každého náhledu** (zepředu, zleva, zezadu, zprava) fotku odebere stejně jako křížek v panelu: je to
  tentýž `input`, panel i dialog se drží v souladu. Po odebrání fotky zepředu se nadpis vrátí na „Přidat fotky“
  a „Vytvořit“ se vypne, dokud fotka zepředu znovu nebude.
- **Vytvořit přímo v dialogu:** oranžové tlačítko, povolené, jakmile je fotka zepředu. Klepnutí spustí totéž
  odeslání jako tlačítko v panelu (`form.requestSubmit()`), takže platí stejné kontroly, zmenšení fotek a hlášky.
  Dialog pak jako dosud vystřídá tečkové logo a na telefonu se stránka posune na prohlížeč. Souhlas v dialogu je
  zrcadlem zaškrtávátka v panelu (oběma směry; odesílá se jen to z panelu) a má stejný trvalý červený rámeček.
  Chybějící souhlas hlásí dialog sám, ne řádek na konci stránky.
- **Jedna oranžová akce:** dokud je dialog na obrazovce, je „Spočítat cenu a možnosti tisku“ (bez modelu stejně
  vypnuté) šedě obrysové (`app.css`, pravidlo s `#figure-create`). Tlačítko v panelu zůstává inkoustové.
- **Čas:** řádek s limitem v dialogu říká „Zdarma 1 model denně bez účtu, 3 s účtem. Model vzniká 2 až 4 minuty.“
  (text řídící session). Samostatnou větu o čase v dialogu už neopakuji. Věta ukázky
  `toolpage.sample.minutes` je přepsaná na „2 až 4 minuty“ ve všech třech jazycích.
- **Telefon:** dialog s náhledy, souhlasem a tlačítkem se do prohlížeče 46 vh nevešel (tlačítko bylo useknuté). Dokud
  je dialog vidět, má prohlížeč na telefonu 60 vh (506 px na 390 × 844, 480 px na 360 × 800). Po vybrání fotky
  zepředu se schová nápověda „Klepněte nebo sem fotky přetáhněte…“. Na 360 i 390 px je celý dialog v okně a stránka
  nepřetéká do šířky.
- **Ověřeno v prohlížeči** (skript v chromu, 390 px, odeslání zachycené před sítí, žádné generování): dvě fotky →
  dva náhledy s křížky, „Přidat další stranu“; křížek u fotky zepředu → fotka zmizí z panelu i z dialogu, „Přidat
  fotky“, Vytvořit vypnuté; Vytvořit bez souhlasu → „Zaškrtněte prosím souhlas.“ v dialogu; souhlas v dialogu
  zaškrtne i panel; Vytvořit se souhlasem → formulář odeslán jednou.
- **Snímky:** `docs/img/audit/{figure,pet-figurine}-{390,1366}-cs-dialog-before.webp` (před) a
  `…-{360,390,1366}-cs-dialog-after.webp` (po, se dvěma fotkami a klepnutím na Vytvořit bez souhlasu),
  `pet-figurine-390-cs-dialog-empty.webp` (prázdný dialog).
- **Test:** `ToolPageTest::test_the_photo_dialog_has_remove_buttons_the_consent_and_create` (pick, místo pro náhledy,
  zrcadlený souhlas bez `name` a s `required`, limit a čas, vypnuté oranžové Vytvořit, místo pro hlášku, text pro
  křížky, pravidlo jedné oranžové). Test jedné oranžové akce na stránce (`test_every_tool_route_renders_the_shared_page`)
  počítá u busty a mazlíčka s tlačítkem dialogu.
- **Neověřeno:** skutečné vygenerování modelu z dialogu (kredity); odeslání je tatáž funkce jako z panelu.

## 9. Etapa 1, zbytek: mobil, hlavní stránka, akce a stavy, cena a doručení, slovník, cesty (18. 10. 2026)

Zadání `docs/prompts/audit-etapa1-zbytek.md` (body 3, 4, 5, 6, 11 a úkol A auditu; dosud řídící). Větev
`feature/audit-rest` z `main` dab9592, worktree `C:\matplace-edit-wt`. Platby (`OrderFlow`, `Wallet`) a trychtýř
měření jsem neměnil. Měřeno skriptem `scripts/review_round2.mjs` na lokálním serveru s touto větví, čísla v
`docs/img/audit/facts.json` (klíče `*-rest*` a `flow-*`).

### 9.1 Mobil (bod 4)

**Hlavička.** Dřív dva řádky drobných odkazů, všechny 20 px vysoké (Nástroje 56 × 20, Cena ze souboru 113 × 20,
Přihlásit 54 × 20, cs/en/es 28 × 20, Kč/€ 26 × 20). Teď na telefonu jeden řádek: logo, **Nástroje**, **Přihlásit**
(nebo Můj účet) a tiché menu „☰ CS · Kč“. V menu je Cena ze souboru, jazyk (celé názvy), měna a Odhlásit. Na
počítači zůstává celá řada a v menu „CS · Kč ▾“ jsou jen jazyk a měna. Každý odkaz a tlačítko má aspoň 44 px
(`.nav-link`). Menu se zavírá klepnutím vedle a klávesou Esc (`resources/js/site/menu.ts`). Snímky:
`home-360-cs-before` → `home-360-cs-rest`, `home-360-es-menu`, `home-1280-es-menu`.

**Úvod hlavní stránky** (360 × 800, podle snímků): dřív končilo pole pro soubor na 650 px a tlačítka na 700 px. Teď
pole končí na 500 px a tlačítka na 557 px, pod nimi jsou výhody a začátek „Mám 3D soubor“. Pomohly k tomu formáty na
jeden řádek („STL, 3MF, STEP a další formáty, do 120 MB“; celý seznam má čtečka a `title`), cesta Myšlenka → Návrh →
Výrobek na jeden řádek, nižší pole a hlavička 52 px. Nadpis „Váš nápad vytiskneme ve 3D.“ a věta pod ním jsou
řídící (už v main), en a es jsem zkontroloval. **Cena** je na kalkulaci na první obrazovce na 360 i 390 px: karta s
dobou tisku a orientační cenou je na telefonu nad prohlížečem (`flow-file-price-390-cs-1`).

| měření | před | po |
|---|---|---|
| malé dotykové plochy na první obrazovce (`/`) | 11 (9 v hlavičce, Nahrát soubor 130 × 38, Popsat slovy 143 × 38) | **0** na 360, 390, 768, 1280 (cs, en) |
| totéž na kalkulaci `/c/…` | pole velikosti 42 px, Kusů 38, rozbalovací řádky 20, výběry formy 37, Poradit s tiskem 34 | **0** na telefonu |
| přihlášení a registrace | pole 38 px, odkazy 20 px, patička 20 px (18 ploch) | **0** |
| katalog | „All“ 43 × 44 | 0 |
| vodorovný posun (360, 390, 768, 1280; `/`, `/tools`, `/login`, `/c/…`, qr, busta) | žádný | žádný |
| text 200 % | `/` cs přetékal na 418 px (slovo „vytiskneme“), `/c/…` na 463 px (řádek „Na podložku…“ vedle Kusů), qr 360 o 2 px (pole Velikost) | **0 ze 40** stránek a šířek, qr, cedulka, krabička, reliéf 0 na 360 a 390 |
| cookie lišta, výška na 360 px | 119 px cs, 139 px en, tlačítka 34 px | **115 px** cs i en, tlačítka 44 px |
| klávesnice (okno o 40 % nižší, každé pole zaostřené: je vidět a nic ho nezakrývá) | – | qr 5/5, cedulka 9/9, busta 3/3, přihlášení 2/2, registrace 3/3, kalkulace 4/4; s lištou i bez ní |

- **Cookie lišta** dál zakrývá na stránce nástroje kartu s hlavní akcí, dokud člověk nevybere (qr, busta na 360 i 390).
  Stránka si teď pod koncem drží místo na výšku lišty a pole zaostřené klávesnicí nebo tabulátorem se zastaví nad ní
  (`--consent-h`, `resources/js/site/bar.ts`). Zmenšit ji víc nejde bez menších tlačítek.
- **Skript** umí `--keyboard` (pole s otevřenou klávesnicí), hlásí slova, která přečnívají ze svého boxu
  (`wideText`; tak se našla věta vedle Kusů), a cesty mají kroky `reload` a `read`. V Git Bash je potřeba
  `MSYS_NO_PATHCONV=1`, jinak se z `--pages=/c/…` stane `C:/…`.

### 9.2 Akce a stavy v kalkulaci a zakázce (bod 5)

- **„Objednat tisk“** (dřív „VYTISKNOUT“; en „Order the print“, es „Pedir la impresión“) s větou: „Zatím nic
  neplatíte: otevře se objednávka, kde vyberete barvu a doručení a uvidíte konečnou cenu. Platí se až na jejím konci,
  kreditem nebo kartou.“ Host čte, že se nejdřív přihlásí. Zakázka vzniká nezaplacená (bez čísla a `paid_at`; test).
  Po klepnutí tlačítko říká „Zakládám objednávku…“ a nejde stisknout znovu. Odmítnutí (zavřeno, limit, neověřený
  e-mail) vrátí tlačítko a ukáže důvod.
- **Chyba:** tlačítko hledalo kalkulaci jen v adrese `/c/<kód>`. Na `/en/c/…` a `/es/c/…` ji nenašlo a ukazovalo jen
  „počkejte na přesný výpočet“. Opraveno (`farm.ts`).
- Kalkulace: „Mám tiskárnu, stáhnout“ → **„Stáhnout model“** (jako na stránce nástroje). „Přepočítat“ zůstává
  inkoustové, oranžová patří objednávce.
- **Zakázka, „Přepočítat“ a „Přepočítat pro zvolenou barvu“:** při čekání „Počítám znovu…“, tlačítka vypnutá, chyba
  v `#farm-error` a tlačítko se vrátí.
- **Zpět a obnovení:** kalkulace zapíše každý přepočet do adresy `/c/<kód>` i s nastavením. Průchodem `calc-keep`:
  kostka, Kusů 3, Přepočítat, F5 → „3 ks, /c/qj4zo2mfbdl6, Přesný výpočet“. Nastavení, které člověk nepřepočítal,
  obnovení nevrátí (kalkulace ho hlásí jako neaktuální). Zakázka má token a stav čte ze serveru.
- **Neúspěšný výpočet** zachová zadání i odhad (kód: `catch` jen přepne stav). **Neplatný soubor** (text místo STL)
  dřív končil „Cena je odhad… Zkuste Přepočítat.“, což nepomůže. Teď: „Tenhle soubor neumíme přečíst jako 3D model:
  je poškozený, prázdný nebo jiného druhu. Zkuste jiný soubor (STL, 3MF, STEP a další).“ (`calc.status.not_model`,
  když server vrátí `file_processing_failed`; `flow-bad-file-390-cs-1`).
- Kalkulace má vlastní **`h1`** s názvem modelu (dřív jen skrytý slogan; §5 #8). Na hlavní stránce zůstává jediným
  `h1` slogan.
- „Pokračovat“ / „Spočítat cenu a možnosti tisku“ na stránce nástroje jsem neměnil. **Rozpor k ověření:** zadání píše
  „Roman 17. 10. rozhodl Pokračovat“, `docs/AUDIT.md` (odpovědi 17. 10.) „platí audit“ a v main i na produkci je
  „Spočítat cenu a možnosti tisku“.

### 9.3 Cena a doručení (bod 6)

- Zakázka má pod cenou blok **Tisk / Odeslání / Doručení**: „Tisk: 2 h 10 min na tiskárně; začne, jakmile je tiskárna
  volná“ · „Odeslání: v den, kdy tisk skončí, v pracovní dny (co se dotiskne o víkendu, odešleme v pondělí)“ ·
  „Doručení: Zásilkovna obvykle do 1 až 2 pracovních dnů po odeslání v Česku, do zahraničí déle“ (u osobního odběru:
  „výtisk je připravený v den, kdy tisk skončí, napíšeme vám“ a řádek Odeslání zmizí) · „Časy jsou odhad: když je
  tiskárna obsazená, tisk začne později, a doručení závisí na dopravci.“ **Ke kontrole Romanem:** „1 až 2 pracovní dny“
  Zásilkovny je obecný údaj dopravce, ne naše měření.
- **Proč se cena změnila:** po přepočtu (jiná barva v jiném stroji, jiné nastavení) stránka napíše například „Cena se
  po přepočtu změnila ze 120 Kč na 150 Kč: tiskne jiná tiskárna (U #1 → K1), jiný materiál (PLA+ → PETG), doba tisku
  1 h 5 min → 1 h 40 min.“ Porovnává cenu, tiskárnu, druh barvy, nastavení a čas před přepočtem a po něm. Když se cena
  nezmění, nic nepíše.
- **Souhrn před platbou proti účtování (jen čtení):** stránka posílá zobrazenou částku (`total()` = cena barvy +
  doprava z nabídky). `OrderFlow::pay` spočítá cenu znovu (`priceFor` se strojem a barvou z volby) a při rozdílu nad
  0,009 platbu odmítne s „Cena se mezitím změnila na …“. Z kreditu se drží `price_total`, tedy totéž číslo. Doplatek
  kartou je zaokrouhlený nahoru na celé (`Math.ceil`), zbytek do koruny zůstane jako kredit. Zaplacenou zakázku, kterou
  je potřeba nařezat pro jiný stroj, server nařeže znovu a cena zůstává, jak byla nabídnutá.
- **Příklady s cenou:** blok `calculator/_examples.blade.php` za `config('site.examples')` (`SITE_EXAMPLES`, vypnuto).
  Busta 80 / 120 / 150 mm, PLA+, 14 / 40 / 72 g, 99 / 130 / 200 Kč bez dopravy (kalkulace z produkce, AG.md), odkaz na
  kalkulaci, obrázek karty nástroje se štítkem „Náhled modelu, ne fotka výtisku“. V eurech cenu nevymýšlí („Cenu ve
  vaší měně ukáže kalkulace.“). Až budou fotky: `photo` u položky v `config/site.php`, pak zapnout.

### 9.4 Slovník (bod 11)

- Digitální výsledek „model“ / „3D model“ / „soubor“ (Stáhnout model, „Tenhle soubor neumíme přečíst jako 3D model“),
  fyzický „výtisk“ / „tisk“ (Objednat tisk, „výtisk je připravený“). Akce: vytvořit (nástroje) / stáhnout model /
  objednat tisk / zaplatit („Zaplatit kreditem a spustit tisk“, beze změny).
- „Zdarma“ s limitem: katalog dřív sliboval „Všechno je zdarma a bez registrace“, teď „Nástroje jsou zdarma a bez
  registrace; tvorba z fotky má denní limit.“ Výhoda na hlavní stránce „Nástroje zdarma, z fotky 1 model denně bez
  účtu“ bere číslo z `ai.daily_limits.generate_guest` (mění se v /admin/ai). Forma v kalkulaci: „Zdarma. Forma vzniká
  desítky sekund…“ (dřív „pár vteřin“; §5 #10).
- **Test chybějících klíčů** (`AuditRestTest::test_every_text_of_the_home_page_the_calculation_the_order_and_the_account_exists_in_all_three_languages`):
  šablony `calculator/*`, `farm/*`, `account/*`, layout a cookie lišta, texty v šabloně i seznamy předávané skriptům
  (i seznam `$keys` zakázky). Našel 3 klíče `check.*_ok.impact` bez překladu. Skript je u kontrol „v pořádku“ nikdy
  neukazuje, proto jsem je ze seznamu vyřadil. Skener je společný se stránkami nástrojů (`ToolPageTest::keysIn`).

### 9.5 Cesty (úkol A)

`node scripts/review_round2.mjs --flows=all --widths=390,360,768,1280 --langs=cs,en`: 64 průchodů, všechny doběhly.
Snímky jsou pro 390 px cs a en a pro 1280 px cs, čísla všech šířek ve `facts.json`.

| cesta | kroky | co se stane | snímky |
|---|---|---|---|
| soubor → cena → objednávka | `/`, kostka 20 mm, 25 s | `/c/<kód>`: „Přesný výpočet“, 13 min, orientační cena 120 Kč, karta ceny první na telefonu; host tlačítko objednávky nevidí (farma pro hosty zavřená) | `flow-file-price-390-cs-1`, `-2` |
| nástroj → kalkulace → objednávka | `/tools/qr`, Spočítat cenu… | „Připravuji model…“, za ~10 s kalkulace téhož modelu, 6 ks na podložku | `flow-tool-price-390-cs-1…3` |
| model → stažení | `/tools/box`, Stáhnout model | menu 3MF OrcaSlicer/Bambu, PrusaSlicer, STL | `flow-download-390-cs-1` |
| QR web | adresa `https://matplace.com/tools` | náhled 70 × 83,1 × 3,4 mm, 2 barvy, 33 × 33 polí | `flow-qr-web-390-cs-1` |
| QR Wi-Fi | typ Wi-Fi, síť „Domov“, heslo `heslo 123;"x` | 37 × 37 polí, 1,9 mm, čitelnost ověřená (skript dřív psal `WIFI:` do adresy; pole Wi-Fi jsou od 17. 10.) | `flow-qr-wifi-390-cs-1` |
| neplatný soubor | `/`, `broken.stl` | „Tenhle soubor neumíme přečíst jako 3D model…“ | `flow-bad-file-390-cs-1` |
| fotka nad limit | reliéf, 16 MB | „Fotka je větší než 15 MB, zmenšete ji prosím.“, nic se neodešle | `flow-photo-limit-390-cs-1` |
| kalkulace po obnovení | kostka, Kusů 3, Přepočítat, F5 | 3 ks, stejná adresa, přesný výpočet | `flow-calc-keep-390-cs-1` |
| zakázka (přihlášený) | – | **neprošlo v prohlížeči:** lokální účet není admin a roli admina mi automatický režim nedovolil přidat. Stránka zakázky je ověřená testy (vykreslení ve 3 jazycích, blok Tisk/Odeslání/Doručení, texty pro skript) | – |

### 9.6 Nálezy

| # | druh | co | stav |
|---|---|---|---|
| 1 | chyba | „Objednat tisk“ nefungovalo na `/en/c/…` a `/es/c/…` (hledalo jen `/c/`) | **opraveno** |
| 2 | chyba | neplatný soubor radil „Zkuste Přepočítat“ | **opraveno** |
| 3 | chyba | text 200 %: `/`, kalkulace a pole Velikost u nástrojů přetékaly na 360 a 390 px | **opraveno** |
| 4 | překážka | hlavička, přihlášení, patička, kalkulace: plochy menší než 44 px | **opraveno** |
| 5 | překážka | kalkulace neměla `h1` | **opraveno** |
| 6 | překážka | cookie lišta (115 px) zakrývá na stránce nástroje kartu akce, dokud se nevybere | zmírněno (místo pod stránkou, pole nad lištou); úplně jen jiným tvarem lišty |
| 7 | překážka | z `/en/tools/qr` vede „Get the price…“ na `/c/<kód>` bez `/en` (stránka je anglicky podle relace, sdílený odkaz otevře česky); API kalkulace vrací `route('calc.share')` bez jazyka | **opraveno** (větev `feature/calc-locale`): příčina nebyla v `CalculationController` (API bere jazyk z Refereru, `Locales::forApi`), ale v `calculator.ts`, které po `?open=` přepsalo adresu na pevné `/`; teď na `MP_ROUTES.home` (`/en`, `/es`). Průchod: `/en/tools/qr` → `/en/c/…` |
| 8 | překážka | po nahrání na `/` zůstává `h1` stránky skrytý slogan (adresa už je `/c/…`), vlastní `h1` dostane kalkulace až po obnovení | neopraveno, drobné |
| 9 | neověřený návrh | „1 až 2 pracovní dny“ u Zásilkovny: potvrdit, nebo nahradit vlastním měřením | Roman |
| 10 | chyba (mimo tuto větev) | `SeoTest::test_every_tool_has_its_page_content_in_three_languages`: nástroj `gcode` nemá `lang/*/tools_seo/gcode.php` | session 2 / řídící |

### 9.7 Testy

`tests/Feature/AuditRestTest.php` (6 testů: hlavička a menu, krátké formáty a `h1` kalkulace, „Objednat tisk“
nezaplacené, zakázka Tisk/Odeslání/Doručení a texty pro skript, příklady za přepínačem, chybějící klíče).
`ToolPageTest::keysIn` je sdílený skener. Běh: `AuditRestTest`, `ToolPageTest`, `WebRound2Test`, `CalculationFlowTest`,
`FarmPagesTest`, `ShippingCurrencyTest`, `CatalogTest`, `ToolsCatalogTest`, `ToolsFlowTest`, `AccountsTest`, `AuthTest`,
`SeoTest`, `FarmOrderFlowTest`, `FarmCardPaymentTest`: 146 testů zelených po poslední změně, `SeoTest` zelený kromě nálezu 10. `pint`, `tsc` a `build` jsou čisté.

## 10. Registrace proti robotům (18. 10. 2026)

Zadání `docs/prompts/registrace-boti.md` (TODO #23). Za den vzniklo 57 účtů „Test“ (55× gmail) z šesti adres, jeden
každých 20–30 minut. Robot vyplní skutečný formulář: honeypot `website` ani `throttle:6,1` ho nezastaví. Každá
registrace poslala ověřovací mail přes Gmail SMTP na cizí adresu. Větev `feature/register-guard` z `main` 4b96b59,
pět kroků, každý v jednom commitu. Přihlášení přes Google/Facebook, účet, kalkulaci ani farmu jsem neměnil.

| krok | co | kde |
|---|---|---|
| 1 | limiter `register`: **3 registrace za hodinu a 5 za den z jedné adresy** (IPv6 podle /64). Počítá se jen registrace, která založila účet (překlep v hesle se nepočítá). Nad limitem stránka registrace s hláškou „Z této adresy dnes přibylo už hodně registrací…“, formulář zůstane vyplněný, kód 429. `throttle:6,1` zůstává. | `AppServiceProvider`, `RegisterGuard::ipKey`, `routes/web.php` |
| 2 | **podepsaný čas vyplnění**: formulář nese pole `t` (`Crypt` s `APP_KEY`). Odesláno do 4 s, po 2 h, bez pole nebo s cizím → zpět na formulář: „Zkuste to prosím znovu: formulář vypršel nebo byl odeslán příliš rychle.“ Vyplněné zůstane všechno kromě hesla. Odmítnutí jde do logu (`register refused`, důvod a adresa). | `RegisterGuard::stamp/refusal`, `AuthController` |
| 3 | **ověřovací mail jen první registraci z adresy za den**. Další účet z téže adresy týž den vznikne, ale mail nedostane. Hláška po registraci řekne „pro ověření klepněte v účtu na Poslat odkaz znovu“ a tlačítko na stránce účtu mail pošle. Člověk, který se registruje sám, nic nepozná. | `RegisterGuard::firstToday`, `User::$verificationMailWithheld` |
| 4 | **Cloudflare Turnstile připravený, vypnutý.** S `TURNSTILE_SITE_KEY` a `TURNSTILE_SECRET` v `.env` má formulář widget a `register` ověří token přes `siteverify`. Chybějící nebo odmítnutý token → zpět s „Potvrďte prosím ověření…“. Když Cloudflare neodpoví, registrace projde a zapíše se varování do logu (limity a čas pořád platí). Bez klíčů se nemění nic. | `config/services.php`, `.env.example`, `RegisterGuard::turnstile*` |
| 5 | **úklid**: v `/admin/users` filtr „neověřené“ a tlačítko „Smazat neověřené (n)“ s potvrzením, plus `php artisan users:prune-unverified --days=7 [--dry-run] [--force]`. Příkaz vždy vypíše seznam, maže jen s `--force` a `--dry-run` má přednost. Maže natrvalo jen neověřené účty starší než N dní **bez** zakázky (i nezaplacené), řádku kreditu, modelu, kalkulace, přihlášení přes Google/Facebook, designérského profilu a role mimo zákazníka. Každý účet se těsně před smazáním zkontroluje znovu. | `App\Domain\Account\UnverifiedAccounts`, `PruneUnverifiedUsers`, `Admin\UserController` |

**Co Roman zapne (Turnstile, zdarma, bez přesunu DNS):** dash.cloudflare.com → účet → Turnstile → Add widget, doména
`matplace.com`, režim „Managed“. Klíče Site Key a Secret Key dát do `.env` na serveru (`TURNSTILE_SITE_KEY=…`,
`TURNSTILE_SECRET=…`), potom `php artisan config:cache`. Vypne se smazáním obou řádků.

**Po nasazení (řídící):** `php artisan users:prune-unverified --days=1 --dry-run` ukáže účty „Test“ starší než den
(`--days` má minimum 1). Smazat s `--force` až po Romanově slově. Po sedmi dnech stačí tlačítko v adminu.

**Podmínka limitu:** `$request->ip()` musí být adresa návštěvníka. Dnes je, protože nginx předává PHP přímo a šest
adres robotů je vidět v datech, která používají totéž `ip()`. Kdyby web někdy šel přes proxy (Cloudflare proxy, load
balancer), je potřeba `trustProxies` v `bootstrap/app.php`, jinak by limit 3 za hodinu sdíleli všichni.

**Testy:** `tests/Feature/RegisterGuardTest.php` (7 testů): 4. registrace za hodinu = 429 se stránkou a vyplněným
formulářem, za hodinu další, po pěti za den konec, jiná adresa nedotčená; překlep se nepočítá, IPv6 podle /64; čas do
4 s, po 2 h, chybějící a vymyšlený = chyba a vyplněný formulář, po 5 s projde, texty ve třech jazycích; mail jen první
registraci z adresy a dne, druhá dostane mail přes „Poslat znovu“, další den zase hned (`Notification::fake`);
Turnstile vypnutý = bez widgetu a bez dotazu, zapnutý = widget s jazykem, odmítnutý a chybějící token neprojde,
přijatý projde, nedostupný Cloudflare projde; příkaz: `--dry-run`, bez `--force` a `--dry-run --force` nic nesmaže,
`--force` smaže jen dva roboty, `--days` platí; admin: počet na tlačítku, bez potvrzení nic, zákazník nic, admin smaže.
Dál `AccountsTest`, `AdminUsersTest`, `SeoTest`, `AdminContentTest` (registrace v testech posílají razítko
`registerStamp()` z `tests/TestCase.php`): 54 testů OK, `pint` čistý. JS se neměnil (`build` netřeba).

## 11. Vstupní stránky a Core Web Vitals (18. 10. 2026)

Zadání `docs/prompts/vstupni-stranky.md` (TODO #24–27, audit SEO). Větev `feature/landing-pages` z `main` 874ce6c.
Šest stránek: hlavní (kalkulace, nástroj `calc`), busta `/tools/figure`, mazlíček `/tools/pet-figurine`, QR cedulka,
organizér, krabička. Logiku nástrojů, kalkulaci, farmu ani platby jsem neměnil.

### 11.1 Bloky pod nástrojem

Šablona `tools/_landing.blade.php`, vložená do stávajícího `tools/_content.blade.php` (obsah pod nástrojem, kde už je
úvod, postup a časté otázky). Texty `lang/{cs,en,es}/landing.php`, vazby `config/landing.php` (vlastní soubor místo
šesti dlouhých řádků v `config/tools.php`), logika `App\Support\LandingPage`.

- **Takhle to dopadlo**: příklady s fotkou, rozměry, materiálem, hmotností, dobou tisku, cenou a odkazem na kalkulaci.
  Blok `calculator/_examples` (z audit-rest) teď umí jen jeden nástroj. Ukáže se jen s `SITE_EXAMPLES=true`, do té
  doby nikde. Čísla jsou z matplace.com: busta 80/120/150 mm 99/130/200 Kč (AG.md), a nově výchozí modely nástrojů
  změřené 18. 10. (skript, cesta `price-<nástroj>`, kalkulace bez objednávky): **QR cedulka** 70 × 83 × 3 mm, 14 g,
  25 min, 99 Kč (`/c/lomf0wzlshw4`); **organizér** 200 × 120 × 40 mm (3 × 2), 115 g, 2 h 27 min, 250 Kč
  (`/c/l1gr0izblzwr`); **krabička** 84 × 54 × 32 mm, 28 g, 34 min, 99 Kč (`/c/nku29wz4vieb`). Vše PLA+, 1 kus, bez
  dopravy. Mazlíček čísla nemá (generování stojí kredity).
- **Než objednáte**: 5–7 otázek na stránku ve třech jazycích (doba tisku a doručení, doprava, materiál a barva,
  přesnost a míry, co když se nelíbí nebo nesedí, účet, stažení, poškozený model, čitelnost a velikost QR, počet kusů).
  Nové otázky neopakují ty, které stránka už má v Častých otázkách. **Otázky o tisku u nás** (doba doručení,
  doprava, kusy, cena, reklamace) mají příznak `farm` a ukážou se jen tomu, komu se farma nabízí
  (`FarmGate::offered`). Dnes tedy adminům, a až Roman farmu otevře, všem. Doprava se bere z `config/farm.php` v měně
  návštěvníka (Česko 99 / 149 Kč, Slovensko od 149 Kč, EU od 199 Kč), limit modelů z fotky z `ai.daily_limits`.
- **FAQPage JSON-LD**: jedna na stránku, otázky nástroje i „Než objednáte“, vždy jen ty, které stránka ukazuje
  (layout).
- **Pro koho**: tři použití s rozměrem (QR: kavárna 70 mm, ubytování s Wi‑Fi 100 mm, obchod u pokladny; busta: dárek
  120 mm, kolega/trenér, pro sebe 80 mm; organizér: dílna, kuchyň, psací stůl; krabička: elektronika, drobnosti, dárek).
- **Kam dál**: podobné nástroje (jen veřejné) a články blogu (jen publikované v jazyce stránky). Odkazy na
  `kolik-stoji-3d-tisk-na-zakazku-v-roce-2026`, `jak-pripravit-stl-soubor-pro-3d-tisk`,
  `pla-vs-petg-vs-asa-jaky-material-vybrat` a `jak-vznika-busta-z-fotky` se ukážou samy, až je session 1 vydá. Kdyby
  změnila adresu, stačí upravit `config/landing.php`.
- Mimochodem: ve španělském textu reliéfu bylo azbukové „а“ ve slově „flojа“ (opraveno).

### 11.2 Core Web Vitals

Měřeno Lighthousem 12.6 (výkon, mobil = simulované pomalé 4G a 4× pomalejší procesor, desktop = preset), skript
`scripts/lighthouse_landing.mjs` (Lighthouse z `npx`, žádná závislost v repu). **Produkce před** je matplace.com
18. 10. večer. **Lokálně před a po** je stejný server `php artisan serve` (bez komprese, proto jsou čísla horší než
produkce a porovnávat dává smysl jen lokál s lokálem).

**Produkce před (mobil):**

| stránka | skóre | LCP | FCP | CLS | TBT | JS | font |
|---|---|---|---|---|---|---|---|
| `/` (→ `/en`) | 66 | 3,92 s | 3,91 s | **0,243** | 0 | 238 kB | 345 kB |
| busta | 74 | 4,23 s | 4,20 s | 0 | 0 | 238 kB | 345 kB |
| mazlíček | 73 | 4,15 s | 3,91 s | 0,049 | 228 ms | 238 kB | 345 kB |
| QR | 63 | 4,75 s | 4,09 s | 0,015 | 400 ms | 238 kB | 345 kB |
| organizér | 68 | 4,49 s | 4,10 s | 0,017 | 303 ms | 238 kB | 345 kB |
| krabička | 70 | 4,45 s | 4,11 s | 0,015 | 244 ms | 238 kB | 345 kB |

Desktop měl 97–100 (LCP 0,5–0,9 s). Příčiny: font Inter 353 kB a jeden JS 244 kB (gzip, 885 kB bez komprese, z toho
three.js většina; „unused JS“ 55–77 %) se stahují hned s HTML. CLS na hlavní stránce dělala výměna fontu: odstavec se
přelomil jinak a pole pro soubor pod ním poskočilo. TBT je spuštění 3D náhledu.

**Opravy (levné):**
1. **Font**: jen latinka, kterou píšeme (čeština, angličtina, španělština, interpunkce, €, šipky, ★ ♥ ◇ ○ ●),
   `public/fonts/InterLatin.woff2`, **114 kB místo 352 kB** (pyftsubset, všechny vlastnosti fontu zachované: `cv11`,
   `ss03`, `tnum`; osy `wght` a `opsz`). `InterVariable.woff2` zůstává jako zdroj.
2. **Náhradní font se stejnými metrikami** (`Inter Fallback`: Arial s `size-adjust 107,4 %` a přepsanými výškami):
   text při výměně fontu neposkočí. Font se navíc přednačítá (`<link rel="preload">`).
3. **JS ve dvou částech**: vstupní `app.js` má **16 kB (6 kB gzip) místo 885 kB** a obsahuje jen věci webu (měření,
   menu, lišta). Kalkulačka, nástroje a three.js jsou chunk `calc/boot.ts`, který se stáhne hned a spustí po
   `DOMContentLoaded`. Stránka se tedy vykreslí, než se začne parsovat 3D. Náhledy modelů v seznamech
   (`site/thumbs.ts`) načítají prohlížeč až u prvního obrázku. Zkoušel jsem i start až po prvním vykresleném snímku:
   FCP vyšlo stejně, ale TBT a skóre hůř, takže jsem ho nepoužil.
4. **LCP obrázek** stránek busty a mazlíčka (ukázka v prohlížeči) má `fetchpriority="high"`. Rozměry obrázků už
   všude jsou a mimo první obrazovku `loading="lazy"`. Skripty třetích stran žádné nejsou.

**Lokálně před → po (mobil):**

| stránka | skóre | FCP | LCP | CLS | TBT |
|---|---|---|---|---|---|
| `/` | 57 → 64 | 8,36 → 4,54 s | 8,90 → 7,92 s | 0,032 → 0 | 0 → 0 |
| busta | 45 → 60 | 8,25 → 5,99 s | 8,72 → 7,69 s | 0,216 → 0 | 164 → 27 ms |
| mazlíček | 44 → 60 | 8,26 → 6,13 s | 8,72 → 7,83 s | 0,221 → 0 | 171 → 27 ms |
| QR | 51 → 58 | 8,41 → 6,02 s | 8,71 → 9,63 s | 0,065 → 0,005 | 270 → 133 ms |
| organizér | 49 → 57 | 8,41 → 6,21 s | 9,23 → 8,11 s | 0,082 → 0 | 309 → 172 ms |
| krabička | 50 → 57 | 8,41 → 6,24 s | 8,94 → 7,87 s | 0,071 → 0 | 282 → 199 ms |

Desktop lokálně 83–89 → 88–95 (FCP 1,45 → 0,87–0,93 s, CLS na 0–0,027). Font 344 → 112 kB na každé stránce.
Lokální LCP zůstává vysoko, protože lokální server neposílá komprimované HTML (110–150 kB místo 25–33 kB) ani CSS
(88 kB místo 14 kB). To na produkci odpadá. **Po nasazení** změřit produkci:
`node scripts/lighthouse_landing.mjs https://matplace.com prod-after` (stejné stránky, čísla do tabulky výše).

**Ověřeno, že nástroje fungují i s kódem načteným zvlášť** (lokálně, 390 px, cs): `--pages=catalogue` přes 60 stránek
nástrojů a `/`, `/tools`, `/login`, `/account`: 41 s modelem, 19 prázdných (nástroje se souborem, busta, mazlíček,
reliéf, mapa; stejně jako v §4.2), **žádná chyba skriptu** a žádný vodorovný posun. Cesty nástroj → kalkulace,
soubor → cena, stažení, QR Wi‑Fi, obnovení kalkulace (3 ks zůstanou) a neplatný soubor dopadly stejně jako v §9.5.
`scripts/check_bundle.mjs` teď čeká i na spuštění nástrojů z druhé části.

**Testy:** `tests/Feature/LandingPagesTest.php` (5 testů: bloky na všech stránkách ve třech jazycích, platné JSON-LD,
otázky stránky = otázky ve FAQPage, žádná dvakrát, čísla doplněná; otázky o farmě jen při nabízené farmě i pro admina;
odkazy na veřejné nástroje a jen publikované články v jazyce stránky; výsledky za přepínačem a jen pro svůj nástroj;
5–7 otázek a tři použití v každém jazyce se stejnými příznaky farmy). `DesignTokensTest` hlídá podmnožinu fontu,
náhradní font a přednačtení. Běh: `LandingPagesTest`, `ToolPageTest`, `WebRound2Test`, `AuditRestTest`, `SeoTest`,
`DesignTokensTest`, `ToolsCatalogTest`, `ToolsFlowTest`, `CalculationFlowTest`: 72 testů OK. `pint`, `tsc` a `build`
(s kontrolou bundlu) jsou čisté.

### 11.3 Co chybí od Romana

- **Fotky výtisků** pro „Takhle to dopadlo“ (busta 80/120/150 mm, QR cedulka, organizér, krabička; ideálně čtverec
  800 × 800 px na neutrálním pozadí). Cesta do `photo` u položky v `config/site.php`, pak `SITE_EXAMPLES=true`.
- Otevření farmy (`farm.public`): do té doby návštěvník neuvidí otázky o objednávce, dopravě a reklamaci.
- Potvrzení „Zásilkovna 1 až 2 pracovní dny“ (stejné jako v zakázce, AH §9.3).
