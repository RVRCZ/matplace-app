# P: tvar z obrázku a jména (session 1, vánoční sada)

Větev `feature/tools-shapes` (z `main` 4438a2d, 7. 10. 2026), zadání `docs/prompts/nastroje.md`, část „Session 1“.
Tenhle dokument popisuje **první várku**: obrázek v barvách filamentů a deset nástrojů, které na něm stojí – celou vánoční sadu ze zadání. Zbytek session 1
je v sekci 7 jako to, co teprve přijde.

## 1. Co vzniklo

**Deset nástrojů v katalogu**, každý s vlastní stránkou, kartou, SEO texty ve třech jazycích a třemi ukázkami:

| nástroj | adresa | kind | co přidává k obrázku |
|---|---|---|---|
| Vánoční ozdoba z obrázku | `/tools/ornament` | `ornament` | očko na stužku; tvar podle obrázku, kruh nebo hvězda |
| Přívěsek z obrázku nebo jména | `/tools/charm` | `charm` | očko kdekoli na obrysu; tvar podle obrázku, kruh, obdélník |
| Klíčenka se jménem nebo obrázkem | `/tools/keychain` | `keychain` | otevírá se se jménem na zaobleném obdélníku, očko vlevo, otvor 3–8 mm |
| Náušnice z obrázku | `/tools/earrings` | `earrings` | pár stejných nebo zrcadlových, obě na jedné podložce |
| Magnetka z obrázku | `/tools/magnet` | `magnet` | kapsa na magnet vzadu (lepení, nalisování, skrz, bez), předvolby disků |
| Podtácek s obrázkem | `/tools/coaster` | `coaster` | kruh, čtverec, šestiúhelník; drážky zespodu |
| Perníček se jménem | `/tools/gingerbread` | `gingerbread` | tvar je náš (panáček, srdce, hvězda, stromek), poleva vlnkou nebo linkou, jméno se samo vejde |
| Sušenka s polevou | `/tools/cookie` | `cookie` | tvar ze siluety nebo obrázku, zaoblená hrana, **poleva kreslená myší v náhledu** |
| Zápich do dortu se jménem | `/tools/cake-topper` | `topper` | číslo, srdce, hvězda, kruh nebo jen nápis; jméno přes tvar druhou barvou; 1–2 hroty 30–100 mm |
| Velké písmeno se jménem | `/tools/name-letter` | `name_letter` | první písmeno jména 60–200 mm vysoké a 3–15 mm silné, celé jméno na něm druhou barvou |

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

**Builder** `engines/python/shape_kinds.py` – jeden pro všech deset produktů. Díly: `body` (podklad s očkem),
`color_<n>`, `rim`. Dva způsoby, jak barvy vytisknout:

- **nad sebou (výchozí)**: každá barva je o krok výš než ta pod ní (0,4–1,2 mm po 0,2; náušnice a podtácek
  0,2–0,8). V každé vrstvě tisku je jediný filament, takže to vytiskne **každá tiskárna výměnou filamentu ve výšce**.
  `notes.color_changes` říká, kde (`z`, díl, kód cívky, hex).
- **zarovno** (`flush`) a **lem ve vlastní barvě** (`rim`): barvy sdílejí vrstvu → `notes.multi_material`. Stránka
  to řekne dřív, než člověk klikne dál.

Podklad má barvu nejnižší barvy obrázku (žádná výměna navíc: obrázek ve dvou barvách = jedna výměna, to, co farma
umí už dnes). U kruhu, čtverce, šestiúhelníku, hvězdy a obdélníku s vyříznutým motivem dostane podklad cívku, která je
od všech barev motivu nejdál (sněhulák s černým kloboukem skončí na fialové, ne na černé ani na bílé).

**Perníček se jménem** je totéž naruby: tvar kreslíme my (`_gingerbread`: panáček s obličejem, knoflíky a polevou na
nohách; srdce a hvězda s polevou po obvodu; stromek se dvěma girlandami), návštěvník přinese jméno. Jméno se zmenší tak,
aby se vešlo do nejširšího místa tvaru; pod 3 mm výšky písmen přijde upozornění. Těsto dostane cívku nejbližší perníkové
hnědé, poleva nejsvětlejší – dvě barvy nad sebou, jedna výměna. Srdce visí za zářez mezi laloky, ne za jeden z nich.
Zadání ho chtělo jako tvar cedulky (`sign`); je v rodině `shape`, protože z ní má barvy, očko tažením i projekt s výměnou.

**Sušenka s polevou** (`cookie`): těsto je silueta z knihovny, vlastní obrázek nebo nápis; horní hrana je zaoblená
o 1,5 mm po vrstvách (`_rounded_top`). Jednobarevná silueta je jen tvar, z barevného obrázku se stane poleva (barva,
která je stejně těsto, se vynechá). **Polevu kreslí návštěvník v náhledu**: tlačítko „Kreslit polevu“ otočí pohled
shora a tah myší nebo prstem je tah sáčku (`viewer.ts` `setDraw`; během tahu je vidět čára, po puštění přijde skutečný
model). Barva z okna Barva, šířka 1,5–4 mm, hrot kulatý / plochý / tečky, klepnutí = tečka; „odebrat poslední tah“,
„smazat polevu“, zpět/vpřed. Tahy se ukládají v podílech šířky obrázku (`strokes`, nejvýš 60 tahů po 48 bodech),
takže na větší sušence leží tam, kde byly. V Pythonu je tah stuha podél bodů (`_stroke`); tahy jedné cívky jsou díl
`icing_<n>` a vrstva jako každá jiná barva: později použitá cívka leží výš a pod ní se nižší vrstvy doplní, takže
i ručně zdobená sušenka má v každé vrstvě tisku jediný filament. Poleva se drží 1,8 mm od zaoblené hrany.
Dlouhá kresba se nástroji předává souborem (`@cesta`), ne příkazovou řádkou. Proti zadání chybí: výběr a posun
jednotlivého tahu (jde jen odebrat poslední), cukrovinky z knihovny, tácek na vystavení a vlastních 40 polotovarů –
tvar se bere ze 137 siluet knihovny.

**Zápich do dortu** (`_topper`): tvar (číslo tučným písmem, srdce, hvězda, kruh, nebo nic) a přes něj jméno; šířka
jména je v % šířky tvaru (smí přesahovat), poloha v % jeho výšky. Pod jménem leží jeho rozšířená kopie v barvě tvaru,
takže drží i písmena mimo tvar; co by přesto odpadlo, sváže můstek (`S.joined`). Hroty jsou ploché, 4 mm široké, se
špičkou; nástroj je posadí tam, kde nad nimi tvar opravdu je (mezeru ve jméně nebo zářez srdce obejde ke středu).
Všechno je jeden kus + jméno o krok výš druhou barvou. Stránka i FAQ říkají, že tištěný plast se nemá dotýkat jídla
(hroty do fólie nebo do brčka). **Tohle není skladba vrstev ze zadání**: žádný seznam vrstev ani gizmo ve vieweru,
rozvržení je pevné a mění se třemi posuvníky. Volná skladba (`compose`: šablona + texty + motivy, posun, otočení,
zvětšení v náhledu) zůstává otevřená; cedulka a klíčenka, které na ní zadání také staví, mají zatím své formuláře.

**Velké písmeno se jménem** (`_name_letter`): tučné písmeno (bezpatkové, patkové nebo strojové) a na něm jméno tam,
kde má písmeno nejvíc místa – `_room` hledá největší obdélník v poměru stran jména, naležato i otočený o čtvrt kruhu
(podél svislého tahu), 1,5 mm od okraje. Pod 4 mm výšky přijde upozornění, bez místa jiné. Tělo dostane nejtmavší cívku,
jméno nejsvětlejší. Volitelné **očko na zavěšení** (`hang`, otvor 3–8 mm, kdekoli na obrysu, tažením v náhledu).
Zadání ho stavělo na skladbě vrstev a chtělo i podstavec; ten tu není – od 10 mm tloušťky většina písmen stojí sama
a stránka to říká.

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

**Rozcestník `/gifts`** má oddíl „Další dárky na míru“ s kartami všech deseti nástrojů (jen těch, které jsou v katalogu).

**Knihovna**: nová kategorie `colour` (Barevné) s osmi vlastními kresbami v barvách, které mají cívku
(`engines/artwork/colour/_draw.py` je kreslí z prosté geometrie; CC0, řádky v `SOURCES.md`). Pět nástrojů se
otevírá s jednou z nich (`ParametricGenerator::SAMPLE`), klíčenka se jménem „Jana“ – dvě barvy, jedna výměna, tedy
to, co farma tiskne už dnes. První, co návštěvník vidí, je vždy hotová věc.

**Projekt pro slicer s více výměnami**: `ColorChange::addAll` zapíše do projektu Orca / Bambu i PrusaSlicer výměnu
filamentu pro každou barvu (dvě výměny ve stejné vrstvě jsou jedna); `ModelFile::colorChanges()` je čte z návrhu.
S jedinou výměnou se nic nemění (`color_change_mm` zůstává, farma ho zná).

**Ukázky a karty** se kreslí v barvách filamentů i mimo karty (`render_tool.py` bere soubor s barvami dílů).

### Po vánoční sadě (od 8. 10. 2026, jeden nástroj na commit)

| nástroj | adresa | kind | co dělá |
|---|---|---|---|
| Stojánek na tužky ze jména | `/tools/name-organizer` | `name_cup` | jméno je stojánek: písmena rozšířená o 3 mm jsou kapsa, kolem stěna 1,6 mm, dno 2 mm, výška 40–120 mm |
| Miska ve tvaru obrázku | `/tools/shape-tray` | `tray` | obrys obrázku je stěna (8–40 mm), kresba vyrytá do dna, v barvách, nebo hladké dno |
| SVG do STL | `/tools/svg-to-stl` | `logo` (předvolba `extrude`) | obrys SVG nebo obrázku vytažený na 0,6–50 mm, bez destičky, volitelně zkosená horní hrana |
| Ozdoba na navíjecí držák karty | `/tools/badge-reel` | `badge` | obrázek v barvách 25–60 mm, pod ním jméno v jedné z barev obrázku, vzadu prohlubeň na lepicí kolečko |
| Korálky s písmeny | `/tools/letter-beads` | `beads` | korálek na každý znak (kostka, kulička, srdce, hvězda 8–14 mm), písmeno nahoře, otvor ze strany na stranu |

**Stojánek na tužky ze jména** (`engines/python/name_kinds.py`, obyčejný parametrický nástroj, ne rodina `shape`):
tiskne se nastojato. Oka písmen pod 150 mm² se vyplní, písmena, která se po rozšíření nedotknou, sváže příčka na celou
výšku. Stránka vypisuje nejširší místo kapsy a varuje, kdyby bylo pod 9 mm (u běžných jmen to nenastane, sousední
písmena se slijí). Volitelný podstavec je spodní 3 mm: `color_change_mm` = 3, tedy druhá barva na farmě i v projektu.
Zadání chtělo „každé písmeno je kapsa“ – tak to je u tiskacích písem; u psacího je kapsa jedna souvislá.

**Korálky s písmeny** (`name_kinds.beads`): až 16 znaků, osm v řadě na podložce, mezera = korálek bez písmene. Písmeno je
na horní ploše vyvýšené (díl `text`, druhá barva, `color_change_mm` = výška korálku) nebo vyryté; volitelně vyryté
zrcadlově i do spodní plochy, aby se korálek četl z obou stran (zadání chtělo písmeno „na dvou protilehlých stranách“
– spodní strana leží na podložce, tam jde jen vyrýt). Otvor 1,5–4 mm vede vodorovně v půli výšky; když nad ním a pod
ním nezbývá 1,2 mm, nástroj odmítne slovy. Kulička má seříznutý vršek a spodek. Texty říkají, že malé díly nepatří
dětem do tří let. Sada čísel ze zadání = napsat číslice do textu.

**Miska ve tvaru obrázku** (`tray`, rodina `shape`, větev `dish` v `build()`): podklad je dno, kolem něj stěna podle
obrysu. Obrázek ve dně má tři podoby: **vyrytý** (výchozí – všechny barvy kromě největší se vyříznou 0,6 mm do dna,
jeden díl, jeden filament, vytiskne kdokoli), **v barvách** (vložený zarovno do dna → `multi_material`, protože stěna
stojí ve stejných vrstvách; na farmě objednat nejde a stránka to říká) a **bez obrázku**. Jednobarevná silueta dá
hladkou misku ve světlém filamentu. Obrázek drží odstup od stěny (`frame`). Při té příležitosti opravená chyba
sušenky: okraj těsta nad 4 mm odmítal builder, ač ho formulář dovoloval do 6 mm (meze v `shape_kinds.LIMITS`).

Rozcestník `/gifts` odkazuje i na misku, stojánek, korálky a ozdobu na držák karty.

**Ozdoba na navíjecí držák karty** (`badge`, rodina `shape`; „Image to Badge Reel“ předlohy). Tři věci jsou jinak,
než říkalo zadání, a proč:

- *Jméno pod obrázkem* (`_caption` v `shape_kinds.py`) se nepřidává jako další barva. Připojí se k té barvě obrázku,
  která se světlostí nejvíc liší od podkladu, a barvy pod ní ho nesou. Návrh se jménem má proto stejné díly, stejný
  počet filamentů a stejné výměny jako bez něj – jinak by jméno stálo ve stejné vrstvě jako nejnižší barva obrázku
  a z tisku „výměnou ve výškách“ by byl tisk pro AMS. Cena za to: jméno a ta barva obrázku mají vždy stejnou cívku.
  Jméno visí pod nejnižším místem obrázku nad sebou (u hvězdy mezi cípy), je nejvýš tak široké jako obrázek a velká
  písmena mají nejvýš 7 mm; pod 3 mm nástroj varuje. V kruhu se obrázek se jménem zmenší, aby se vešly oba; pod celou
  fotku v kruhu se jméno nevejde a nástroj to řekne.
- *Vzadu není nákružek, ale prohlubeň* (Ø 10–30 mm, výchozí 19; hloubka 0,4–2, výchozí 0,8; jde vypnout). Ozdoba se
  tiskne lícem nahoru, nákružek 1,5 mm na zadní straně by ležel na podložce a celá ozdoba by nad ním visela na
  podpěrách. Prohlubeň se tiskne čistě, schová tloušťku lepicího kolečka nebo suchého zipu a vystředí ozdobu. Je to
  týž kód jako kapsa na magnet (`mount` = `glue` | `none`).
- *Šířka 25–60 mm* místo 30–50: čelo běžného držáku má kolem 32 mm, menší ozdoby se dělají také.

Pro další nástroje rodiny: `ParametricGenerator::CAPTIONED` = nástroje, kde text nejde místo obrázku, ale pod něj
(stránka otevřená se jménem v adrese si nechá ukázkový obrázek); texty rodiny předávané skriptu jde přepsat pro jeden
nástroj klíčem `param.shape.<klíč>.<kind>` (tak má jmenovka vlastní větu o prohlubni); `ModelFile::printHints()` už
nepotřebuje nový řádek pro každý produkt rodiny.

**SVG do STL** (klíč katalogu `svg_to_stl`) není nový generátor, ale nástroj Logo otevřený předvolbou `extrude`
(provedení „Vyříznutý tvar“, 5 mm) a s kočkou z knihovny, aby stránka začínala hotovou věcí. Hledá se to pod jiným
slovem než logo, proto má vlastní adresu, kartu, texty a příklady; uložený návrh je dál `kind = logo`, takže úprava
návrhu, objednávka i stažení jdou stejnou cestou. Jak je to zapojené, aby to šlo zopakovat pro další „nástroj =
jiný nástroj s předvolbou“: trasa nese `kind`, `as` (klíč katalogu, z něj titulek, úvod `param.<as>.lead`, SEO
a karta) a `preset`; `ToolsController::param` je předá stránce, `param.ts` vezme předvolbu z adresy, jinak tu ze
stránky; `seo.kind` v `config/tools.php` říká, kterým generátorem se kreslí příklady; `SAMPLE[<as>]` je výchozí
obrázek. Logu při tom přibylo: tloušťka do 50 mm (bylo 10), zkosená horní hrana u vyříznutého tvaru (čtyři
schody po vrstvě, nejvýš 0,8 mm) a formulář schovává, co k provedení nepatří (tvar, tloušťka a okraj destičky jen
u reliéfů, výška podstavce jen u stojícího loga). Náhled 0,46 s. DXF ani obrysy bez výplně nástroj nečte – texty
to říkají a radí převod na obrysy.

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
8. **Kategorie „Hračky a hry“** má první nástroj (sušenku); test, který hlídal, že prázdná kategorie nemá filtr, to
   teď ověřuje na katalogu bez ní.
9. **Test `ToolPageTest::test_verified…`** padal už na `main` (od 7. 10. mají tři nástroje v configu datum ověření
   a test čekal katalog bez štítků). Test si teď data sám vynuluje; chování hlídá dál.

## 3. Co se na farmě vytiskne a co ne

Když tahle větev vznikala, uměla farma jednu nebo dvě barvy (jedna výměna ve výšce). Víc výměn postavila souběžně
jiná session na větvi `feature/farm-colors` (z `b070caf`; popis v `docs/FARM-COLORS.md` tamtéž): objednávka nese
seznam výměn, úvodní stránka objednávky nabídne cívku pro každou výměnu a předvybere ty z návrhu, `GcodeSlot` vloží
`T<n>` do každé výšky. Z návrhu čte to, co tu vzniká: `ModelFile::colorChanges()` (`z`, `hex`, `code`) a
`tool_params.part_colors.body.hex`. Limit je **čtyři různé cívky v jednom tisku** (`FarmOrder::MAX_COLORS`); barva
se smí vrátit (A, B, A) a nestojí cívku navíc.

Texty nástrojů a SEO stránek to říkají od commitu „four colours“ na této větvi – **ten smí do `main` jen spolu
s `feature/farm-colors`**, jinak by sliboval, co farma neumí.

| návrh | na farmě | ke stažení |
|---|---|---|
| jedna barva | ano | STL, projekt |
| 2–4 různé cívky nad sebou | ano, cívku pro každou barvu zákazník potvrdí při objednávce | projekt s výměnami, STL po barvách |
| 5–8 různých cívek nad sebou | ne; stránka nástroje to řekne a radí méně barev nebo sloučení | projekt s výměnami, STL po barvách |
| zarovno / lem (barvy v jedné vrstvě) | ne | STL po barvách (projekt je jednobarevný) |

Stránka nástroje se rozhoduje podle `notes.filaments` (počet různých cívek) a `notes.multi_material`.
**Vícebarevný tisk z těchto nástrojů se na farmě ještě netiskl** – první zkouška je tříbarevný přívěsek po nasazení.

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

Samotný `param_tool.py` s obrázkem běží 0,7–0,9 s (fotka v osmi barvách na 120 mm: 0,89 s). Po zjednodušení obrysů
(bod na desetinu buněk) má model 2–5 tisíc trojúhelníků; předtím 14 tisíc.

**Na produkci** (matplace.com, `main` 5355fa3, 7. 10. 2026 večer, medián z pěti, stejný požadavek):

| nástroj | požadavek | STL |
|---|---|---|
| přívěsek, duch | 1,26 s | 107 kB |
| podtácek, sněhulák | 1,28 s | 136 kB |
| sušenka, perníkový panáček | 1,31 s | 400 kB |
| velké písmeno „Ela“ | 0,82 s | 137 kB |
| perníček „Ela“ | 0,59 s | 262 kB |
| cedulka pro srovnání | 0,49 s | 110 kB |

**Cíl „do 1 s“ nástroje s obrázkem neplní** ani lokálně, ani na produkci (1,3 s + 0,35 s prodleva před dotazem);
nástroje se jménem ano. Můj odhad „kolem 0,9 s“ z první verze tohoto dokumentu byl špatně. Kam čas jde (lokální
profil): import `scipy.ndimage` 0,27 s, čtení SVG 0,1–0,15 s, barvy a obrysy 0,2 s, zbytek start Pythonu a PHP.
Po měření jsem zrychlil čtení SVG (body celé křivky jedním voláním, stejné obrysy na desetinu mm², o ~0,04 s na
obrázek) – to v číslech výše ještě není. Dál by pomohl jen běžící proces místo nového Pythonu na každý náhled, což je
věc `feature/perf`, ne této větve.

## 5. Co není ověřené

- **Nic z toho se netisklo.** Geometrie je ověřená výpočtem (testy: objemy, rozměry, díly, výšky výměn), ne tiskem.
  K vyzkoušení na farmě: přívěsek 45 mm ve dvou barvách (očko Ø 3, stěna 2 – drží?), náušnice 30 mm (tloušťka 2,4),
  magnetka s kapsou Ø 10 × 2 na lepení (vůle 0,2) a nalisování (0,05), podtácek 100 mm s drážkami. Štítek „ověřeno
  tiskem“ žádný z deseti nemá. U zápichu je k vyzkoušení hlavně pevnost hrotů (4 × 3 mm, 60 mm).
- **Projekt s více výměnami** hlídá test (`ColorChangeTest`: tři řádky ve správném pořadí, Orca i Prusa); ve
  skutečné Orce ani PrusaSliceru jsem ho neotvíral. Jedna výměna je ověřená z dřívějška (N.md §8).
- **Tažení očka a kreslení polevy** jsem zkoušel skriptem v headless Chrome (úchyt se chytí, tah se nakreslí, pošle
  a objeví jako díl polevy), ne rukou a ne na dotykovém displeji. Během tahu je čára tenká (1 px); jestli je na světlé
  sušence dost vidět, ukáže až ruka.
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

S gitem jdou: `engines/artwork/colour/` (8 SVG + `_draw.py`), `public/img/tools/{ornament,gingerbread,cookie,topper,name_letter,charm,keychain,earrings,magnet,coaster}-*`,
`public/img/tool-examples/…-{1,2,3}.png` (a totéž pro nástroje přidané po 8. 10.: `name_cup`, `beads`, `tray`, `svg_to_stl`, `badge`). Po nasazení projít `/tools` (deset nových karet), `/gifts` (oddíl „Další dárky na míru“), `/tools/cookie` (Kreslit polevu, tah myší a prstem na mobilu), `/tools/charm` (táhnout
očko, změnit cívku barvy, šipky pořadí, sloučit), `/tools/magnet` (předvolby magnetu), „Pokračovat k ceně“ a
u dvoubarevného návrhu objednávku na farmě.

Poznámka k tomuhle PC: `C:\matplace-app\node_modules` je od 7. 10. 07:37 prázdný (zmizel při úklidu worktree, které
na něj měly odkaz), takže `npm run build` nejde v žádném worktree, který tam odkazuje. V `C:\matplace-shapes-wt`
jsem odkaz nahradil vlastním `npm ci`; sdílený adresář obnoví `npm ci` v `C:\matplace-app`.

**Nasazeno 7. 10. 2026 v 18:27 UTC** (nasazovala session, která stavěla `feature/farm-colors`; podrobnosti v
`docs/FARM-COLORS.md` §5): `main` 5772959 = tato větev po `a764dcf` slitá s `feature/farm-colors`, jedna migrace
(`farm_orders.color_changes`, ne z této větve), odstávka 11 s. Po nasazení jsem ověřil, že `/tools`, `/gifts` a
stránky nových nástrojů ve třech jazycích odpovídají 200 a že náhled na produkci přiřazuje barvám skutečné cívky
(duch: `02_PLA+_bily`, `01_PLA+_cerny`, `04_PLA+_ruzovy`). Zkušební tisk je na Romanovi.

## 7. Co přijde (v tomhle pořadí)

Dluhy vánoční sady: volná skladba vrstev `compose` s gizmem (zápich je zatím formulář), u sušenky výběr a posun
tahu, cukrovinky a tácek, u velkého písmene podstavec.
Potom zbytek zadání session 1: ostatní produkty rodiny (brčko, gumičky, otvírák,
organizér podle fotky, lístečky, čep na tašku, medaile, stojan na svíčku, papel picado, klikátko), tvary
a motivy cedulky, 20+ písem.
