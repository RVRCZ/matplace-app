# V: ladění tiskových parametrů farmy (session F)

Větev `feature/print-tuning` (z `main` 95ea9d6, 10. 10. 2026), zadání `docs/prompts/ladeni-tisku.md`. Roman:
začít částí 5 – šev (scarf joint). Tenhle dokument je průběžný: každý řádek ladění, každé kolo a výsledek sem.

Vlastní soubory session: `TuningAdvisor.php`, `ProfileLibrary.php`, `TestPrintService.php`,
`database/data/farm_profile_library.json`, `engines/orca/profiles/*`, `engines/python/calib_tool.py`,
`tuning*.blade.php`, `FarmTuningController`, testy `FarmTuningTest`, `FarmNozzleTest`, `TuningAdvisorTest`.

## 1. Šev (část 5 zadání)

### 1.1 Proč ne objekt `quick`

Zadání chtělo vytisknout `quick` dvakrát a porovnat „válcovou část kostky / pilířů“. Na `quick` ale šev není na čem
posoudit:

- kostka je hranatá, šev `aligned` jde do ostrého rohu a **podmíněný scarf** (`seam_slope_conditional = 1`,
  `scarf_angle_threshold = 155°`) se na obvodu s ostrým rohem nepoužije vůbec,
- pilíře na stringing mají Ø 4 mm, tedy obvod 12,6 mm – kratší než samotný scarf (20 mm).

Ověřeno řezem (Anycubic Slicer Next 2.0.0.3 z příkazové řádky, naše profily `machine.json` + `process_standard.json`
+ `filament_pla.json`, podložka 250 mm, výplň 15 %): `quick` se scarfem má šikmé pohyby (G1 se Z uvnitř vrstvy)
**jen na třech pilířích**, na kostce, převisech, mostu ani stěně žádný.

### 1.2 Nový zkušební objekt `seam`

![objekt seam](img/tuning-seam-object.png)

`calib_tool.py seam` – jedna destička 161 × 35 × 21 mm, prvky 20 mm od sebe, zleva:

| prvek | rozměr | k čemu |
|---|---|---|
| válec | Ø 30 × 20 mm | šev je čára po stěně, nemá se kam schovat |
| hranol se zaoblenými rohy | 20 × 20 × 20, R 6 | rovné plochy, ale žádný ostrý roh – scarf se použije |
| kostka | 15 mm | kontrola: šev zůstává v rohu, rozměr a rohy se scarfem nesmí změnit |
| kužel rozšířený nahoru | Ø 16 → 34,65, sklon 25° | scarf na převisu (0,09 mm na vrstvu ≈ 22 % šířky stěny, pod prahem 40 %) |

V administraci: *Vytisknout test* → objekt **Šev**; zaškrtávátko **Šikmý šev (scarf joint)** přidá k nastavení řádku
`TestPrintService::SCARF` (hodnoty ze zadání); pole *Proces navíc (JSON)* je přebije. Bez zaškrtnutí se tiskne šev
podle řádku. U testu je napsáno, s čím se tiskl („šev: scarf external, délka 20 mm, mezera 15%“ / „bez scarfu“).
Hodnocení testu `seam` se ptá na **šev** (není vidět / slabá linka / zřetelná linka / hrubý), **vadu na švu**
(žádná / boule / díry), rozměry kostky a rohy; otvor, převisy, stringing, most a ostatní pole u něj nejsou.
**Hodnocení z fotek (AI)** – `TestPhotoJudge` (po dohodě s řídící session, 10. 10.): pole `seam` a `seam_fault`,
v pokynech popis švu, boule, díry a scarf přechodu. Každý objekt se ptá jen na to, co na něm je
(`TestPhotoJudge::fieldsFor`): test `seam` na šev, rohy kostky, sloní nohu a podložku; ostatní objekty na šev nikdy.
Model **neví, který z dvojice tisků má scarf** – nastavení švu se mu neposílá, aby nehodnotil podle očekávání.
Bez fotky strany se švem zblízka a s bočním světlem odpoví „nelze posoudit“ a řekne si o ni.

Řez téhož objektu dvakrát (místní Anycubic Slicer Next 2.0.0.3, ne serverová Orca – čísla jsou orientační):

| | čas | filament | šikmé pohyby na stěnách (vrstvy 5–15 mm) |
|---|---|---|---|
| dnešní nastavení (`seam_slope_type = none`, `seam_gap = 10%`) | 28 min 9 s | 15,81 g | žádné |
| scarf (`external`, podmíněný, délka 20, 10 kroků, rychlost 100 %, `seam_gap = 15%`, `staggered_inner_seams = 1`) | 30 min 17 s | 15,69 g | válec, zaoblený hranol, kužel: vnější i vnitřní stěna; **kostka žádné** |

Scarf tedy prodlouží tisk oblých dílů o jednotky procent (tady +7,5 %) a hranaté díly nechá být.

### 1.3 Stav tisku

| test | stroj, cívka | nastavení | výsledek | fotky |
|---|---|---|---|---|
| T26-000030 | S1 #1, PLA+ bílá (slot 4) | scarf | **nedoběhl** – tiskárna na 110 min ztratila spojení s agentem, Roman tisk vypnul, zakázka `failed`; nehodnotí se | – |
| T26-000031 (B) | S1 #1, PLA+ bílá (slot 4), nový přítlak extruderu | dnešní (bez scarfu) | **hotovo 10. 10.** – kostka 14,96 × 14,95 (−0,04/−0,05), rohy ostré, stěny válce, hranolu i kužele hladké; na 8 fotkách z foto‑boxu (měkké čelní světlo, 3840 × 2160, výřezy v plném rozlišení) **šev jsem nenašel** (AI ho na válci našla, viz níže – můj výřez mířil jinam); horní plochy s viditelnými čarami (válec, kužel), žádné vlásky kromě prachu; nažloutlý nádech paty kužele = stín, ne vada | u testu v adminu |
| T26-000032 (S) | S1 #1, PLA+ bílá (slot 4), nový přítlak extruderu | scarf (předpoklad podle pořadí, Roman potvrdí) | **hotovo 10. 10.** – 11 fotek kusů odlomených od sebe, **ostré boční světlo zleva** (jiné než u B). Válec: na jedné straně slabá svislá linka bez boule, ostatní strany čisté. Zaoblený hranol: nic vidět (osvětlená stěna přepálená). **Kužel: na jedné straně zřetelná rovná čára shora dolů a vedle ní asi 15 mm široký zdrsněný pás s drobnými značkami; na další straně pole teček v šikmé mřížce přes zhruba čtvrtinu obvodu**; zbylé dvě strany čisté | u testu v adminu |

| T26-000037 (S2) | S1 #1, PLA+ bílá (slot 4) | scarf jen na svislých vnějších stěnách (zaškrtnutí + JSON, Roman potvrdil) | **hotovo 10. 10.** – 10 snímků při expozici −6, kusy odlomené (válec 1×, kužel 1×, hranol 4×, kostka 4×) | Camera Roll 20:57–20:59 |

**Srovnání B × S ve stejném ostrém bočním světle** (Roman 10. 10. večer dofotil B stejně jako S, kusy odlomené;
T26-000032 má u testu „šev: scarf external, délka 20 mm, mezera 15%“):

| prvek | B = bez scarfu (T26-000031) | S = scarf (T26-000032) | lepší |
|---|---|---|---|
| válec (svislá stěna) | ostrá svislá čára (potvrzeno i při expozici −6, 19:32) | při expozici −6 (19:29) **stejně zřetelná svislá čára** se slabým schodkem; „měkčí linka“ z přepálených snímků byla klam expozice | žádný rozdíl |
| zaoblený hranol | zřetelná svislá čára u rohu, mírně vystouplá (snímek s ruční expozicí −6, 19:26) | nic rozeznatelného (stěna přepálená, přefotit s −6) | nelze říct |
| kužel (stěna 25° ven) | **jedna tenká čistá čára**, stěna kolem hladká | čára **+ asi 15 mm zdrsněný pás vedle ní + pole teček v šikmé mřížce** na další straně; při expozici −6 potvrzeno na třech snímcích (zdrsnění zabírá velkou část osvětlené strany) | **B, zřetelně** |
| kostka | rohy ostré, 14,96 × 14,95 | rohy ostré (neměřeno) | stejné |

**Závěr: scarf v téhle podobě (`TestPrintService::SCARF`) do řádku nepřebírat.** Na svislé stěně nepomůže viditelně, na
šikmé stěně vymění tenkou čáru za široký zdrsněný pás. Pás = rampa scarfu tištěná na převisu (kužel má 22 % šířky
stěny přes okraj, pod prahem `scarf_overhang_threshold = 40%`, takže se scarf použil); šikmá mřížka teček odpovídá
`staggered_inner_seams` a scarfu na vnitřních stěnách prosvítajícím vnější stěnou – to druhé je domněnka.

Další pokus **S2** (jeden tisk, zaškrtnutý scarf + *Proces navíc*):
`{"seam_slope_inner_walls":"0","staggered_inner_seams":"0","scarf_overhang_threshold":"5%"}` – scarf jen na vnější
stěně a jen na (skoro) svislých stěnách. Místní řez: válec a hranol mají šikmé pohyby jen na vnější stěně, kužel
a kostka žádné, vnitřní stěny všude rovné; 29 min 42 s. Když S2 dá válec jako S a kužel jako B, tyhle hodnoty
nahradí `SCARF` a jdou do řádku; jinak scarf pro PLA+ nezavádět a zkusit ho až na silku, kde je šev vidět nejvíc.

**Focení od 10. 10. 19:26**: kamera Trust Teza na ruční expozici −6 (automatika dávala osvětlenou stranu bílého
PLA+ na 215–235 z 255, kde kamera kresbu slévá; s −6 je na 174 a šev i čáry horní plochy jsou vidět). Snímky
z aplikace Fotoaparát jsou v `Obrázky\Camera Roll`. S2 a přefocení hranolu a válce z B a S už s touhle expozicí.

**Výsledek S2 (T26-000037) proti B a S, stejné světlo, expozice −6:**

| prvek | B (bez scarfu) | S (scarf podle zadání) | S2 (scarf jen na svislých vnějších stěnách) |
|---|---|---|---|
| zaoblený hranol | zřetelná svislá čára u rohu, mírně vystouplá | neposouzeno | **na čtyřech pohledech žádný hřebínek**, jen měkký přechod lesku |
| válec | ostrá svislá čára | stejná čára | čára měkčí (jeden pohled) |
| kužel | jedna tenká čistá čára | čára + zdrsněný pás + pole teček | **jedna tenká čistá čára jako u B** |
| kostka | rohy ostré, 14,96 × 14,95 | rohy ostré | rohy ostré, **14,96 × 14,97** – scarf rozměr nemění |

**S2 je první varianta, která je lepší než dnešní stav a nikde horší.** Proto od tohoto commitu
`TestPrintService::SCARF` = hodnoty S2 (zaškrtávátko „Šikmý šev“ už dá přímo je, JSON netřeba). Meze důkazu: jeden
tisk, jedna tiskárna, bílé PLA+, válec a kužel jen z jednoho pohledu. Do knihovny `kobra s1`
zatím nejde – až řádek potvrdí druhý tisk (jiná barva nebo silk) a Roman šev nehtem.

**Rozhodnutí Romana 10. 10. večer:** nastavení S2 zapsat „globálně“ pro PLA+ na Kobra S1, stav nechat „testuje se“;
nehtem je rozdíl švu mezi B a S2 „sotva“. Provedeno:

- **Knihovna** `farm_profile_library.json` → `kobra s1` → `PLA+` → `*` → `process` = hodnoty S2 (stejné jako
  `TestPrintService::SCARF`, hlídá to test). Přes `*` je dostane i PLA+ matný. **Max je dědí, ale scarf na něm nikdo
  netiskl**, proto má jeho `PLA+` v knihovně výslovně hodnoty švu z profilu (`seam_slope_type = none`, mezera 10 % …).
- **Knihovna platí jen pro nově zakládané řádky** (`ProfileLibrary::sync` existující řádky nikdy nemění). Stávající
  řádky PLA+ na S1 je potřeba doplnit v administraci: *Přepisy procesu (JSON)* → přidat klíče S2 → *Uložit jako novou
  verzi*, stav „testuje se“. Řádek ve stavu „vyladěno“ se bez testu nepřepisuje – tam nejdřív test `seam`.
- Zápis do stávajících řádků na produkci: přímý zápis do databáze řídící session neprošel, Roman proto schválil
  **tlačítko na stránce řádku „Použít i na ostatní tiskárny stejného typu s tímto druhem“** (kliká ho sám). Přidá
  zaškrtnuté skupiny přepisů (výchozí jen proces; volitelně filament, teploty) k tomu, co cílové řádky mají – stejné
  klíče přepíše, ostatní nechá –, jako novou verzi s historií a stavem „testuje se“. Cíl = řádky téhož druhu
  (PLA+ a PLA+ matný zvlášť) na tiskárnách se stejným klíčem bez čísla (`kobra-s1-*`) a stejnou tryskou; Max se s S1
  nikdy nemíchá. **Vyladěné řádky přeskočí a vypíše.** Před akcí potvrzení s počtem řádků, po ní výpis změn (v → v).
  **Postup pro S2:** na řádku PLA+ Farm U #1 vložit klíče S2 do *Přepisy procesu*, uložit jako novou verzi, pak
  tlačítko se zaškrtnutým jen „přepisy procesu“ → všechny ostatní S1. Pozor: přenese se **celý** proces zdrojového
  řádku, ne jen šev – co na ostatní S1 nemá jít, do zdrojového řádku před kliknutím nedávat.
- **Stav na produkci 10. 10. 19:49 UTC** (podle řídící session, ověřeno jí v DB): nasazen ac6aac3; Roman zapsal S2 ručně
  do řádků druhu PLA+ i PLA+ matný na U #1 (v2) a U #2 (v5 / v4), stav „testuje se“, poznámka „šev S2 z testu
  T26-000037“; Max beze změny. S1 farmy B dostanou S2 novým tlačítkem po nasazení.
- Od chvíle, kdy řádek scarf má, tiskne test „Šev“ **bez zaškrtnutí** už se scarfem (šev podle řádku). Srovnávací tisk
  bez scarfu jde přes *Proces navíc* `{"seam_slope_type":"none"}`.

**AI hodnocení T26-000032** (10. 10. 17:04, 12 fotek, 3 přiblížení): *Šev: nelze posoudit – na válci a kuželu ho
nenacházím, strana je přeexponovaná nebo rozmazaná*, rohy ok, sloní noha 0, podložka ok, 4/5; chce ostré nepřepálené
boční záběry. Zdrsněný pás na kuželi (foto 5, 6) nenašla – ve výřezu v plném rozlišení vidět je. Pokyn se tedy chová
poctivě (nehádá), ale na přepálených fotkách bílého PLA+ vadu přehlédne; fotit s menší expozicí. Roman formulář
odeslal se 4/5 bez pole švu. **Tlačítko „označit jako vyladěné“ u T26-000032 nemačkat** – přeneslo by scarf do řádku.

**AI hodnocení T26-000031** (10. 10. 16:04, 11 fotek, 3 přiblížení) – první ostrý běh pokynu pro šev: *Šev 2 (spíš):
na válci svislá čára uprostřed stěny (foto 9, 2)*, *Vada na švu: none – čára v rovině se stěnou, bez hrbolu a drážky*,
rohy ok, sloní noha 0, podložka ok, celkem 4/5; k tomu poznámka o malém výstupku u okraje vršku kužele a drsném
vršku, a žádost o fotky kužele a hranolu zblízka z boku při bočním světle. Pokyn tedy funguje: šev našlo, správně
odlišilo rovný šev od boule/díry a řeklo si o správné světlo. Roman potvrdil formulář (10. 10.): **šev 1 (slabá
linka), vada žádná, rohy ostré, 4/5**; rozměry kostky do formuláře nezapsal (14,96 × 14,95 jsou tady). Poradce:
„není co měnit“. Řádek zůstává v1 (`tuned`); tlačítko „označit jako vyladěné“ u B nemačkat, dokud není porovnán S.

Poučení z B: v měkkém světle foto‑boxu není vidět ani běžný `aligned` šev bílého matného PLA+; dvojici B/S je nutné
porovnat **za stejných podmínek s bočním (ostrým) světlem** – Roman nemá lampu, stačí svítilna mobilu položená
na stůl vedle kusu, nebo denní světlo z okna – nebo nehtem po obvodu válce a hranolu. Výsledek B sám
o sobě neříká „šev 0“, říká „šev není vidět v tomhle světle“.

Plán tisků po zprovoznění farmy U (10. 10. večer, filament jde přes ACE, Roman nemá konkrétní příznak – chce
nejlepší nastavení): S1 #1 postupně `seam` bez scarfu (B), `seam` se scarfem (S), `quick` dnešní (Q16), `quick`
s `{"filament_max_volumetric_speed":["12"]}` (Q12); Max souběžně `quick` s `{"top_surface_speed":"120"}` na modré PLA+.
Každý výtisk zvážit (podtlak toku = nižší hmotnost než odhad), u Q16 poslouchat cvakání extruderu při výplni.
Roman nemá dost přesnou váhu → místo hmotnosti **posuvka na tenké stěně** `quick` (2 čáry, nominálně 0,84 mm;
podtlak toku = tenčí) a fotka horní plochy kostky. Extruder necvaká. **10. 10. večer Roman upravil přítlak
podávacích koleček extruderu na S1 #1** (jen tam; S1 #2 a Max beze změny; před testy) – všechny dřívější testy a vyladěný řádek PLA+ na S1 vznikly se starým
přítlakem; Q16 i Q12 už s novým, takže dvojice je srovnatelná.

**Co ukázal G-kód T26-000030** (serverová OrcaSlicer 2.4.0-beta, staženo z administrace): nastavení scarfu v něm je
(`seam_slope_type = external`, délka 20, mezera 15 %, střídání vnitřních švů), šikmé pohyby na válci, hranolu
a kuželi, na kostce žádné; čas 28 min 10 s, 15,88 g (serverová Orca dává se scarfem stejný čas, jaký místní slicer
bez něj – z času se scarf poznat nedá). **Šev `aligned` padl u tří prvků ze čtyř na stranu k sousedovi** (válec
vpravo, hranol vpravo u předního rohu, kužel vlevo; kostka levý přední roh) – do 5mm mezer první verze destičky,
kam se nedá fotit. Proto má objekt od druhého commitu rozestupy 20 mm (161 mm na délku, místní řez 29 min 22 s bez
scarfu / 31 min 23 s se scarfem, 16,1 g).

Stav 10. 10. večer podle řídící session: S1 #1 má silky a mramor, S1 #2 jen PETG oranžovou, PLA+ je jen na Maxu.
Pro test je potřeba **založit do S1 #1 jednu cívku PLA+** (černá nebo bílá, slot 4 místo mramoru) a zapsat ji
v adminu. Stav řádků a fronty na matplace.com jsem sám nečetl (čtení z produkce tahle session nepovolila).

### 1.4 Co dál podle výsledku

1. Scarf lepší → *3 · Test je dobrý* u scarf testu: řádek PLA+ na tom stroji dostane hodnoty testu (stav Vyladěno);
   pak do knihovny `kobra s1` → PLA+ (`process`), ať je mají i ostatní S1.
2. Silk (šev je na lesku nejvíc vidět) stejný pár testů; PETG s nižší `scarf_joint_speed`; ASA až po základním ladění.
3. Globálně do `process_standard.json` / `process_fine.json` až po bodu 1 a 2.
4. Když bude výsledek nejasný: scarf mění tři věci najednou (šikmý přechod, mezeru 10 → 15 %, střídání vnitřních
   švů). Třetí tisk se scarfem a `{"seam_gap":"10%","staggered_inner_seams":"0"}` v poli procesu je oddělí.

## 2. Oprava formuláře hodnocení

U testu, který ještě nikdo nehodnotil, měl formulář předvybrané odpovědi **stringing: žádný**, **sloní noha: žádná**
a **převis čistý do: žádný** (`null == 0` je v PHP pravda). Odeslání bez doteku tak zapsalo tři odpovědi, které nikdo
nedal, a z „žádný převis čistý“ poradce navrhne víc chlazení a podpěry pod vším plošším než 60°. Opraveno
(`isset(...)`), test `test_a_test_nobody_has_judged_yet_shows_no_answer_as_chosen`. Starší hodnocení, která mají
`overhang_ok = 0` bez důvodu, stojí za kontrolu.

## 2a. Objekt „Žehlení“ se nedal vyhodnotit (opraveno 10. 10.)

Objekt `ironing` vznikl jen kvůli žehlení, ale formulář u něj pole „Žehlená plocha“ neukazoval (jen u `detailed`)
a nabízel rozměry kostky, otvor, převisy a stringing, které na něm nejsou; `TestPhotoJudge` mu navíc říkal „na tomhle
objektu žehlená plošina není“ a AI odpověděla „nelze posoudit“. Teď: formulář u `ironing` má jen „Žehlená plocha“,
celkové hodnocení a poznámku; AI se ptá na žehlení a podložku a ví, že celý objekt je žehlená plošina 30 × 30 mm.
Testy ve `FarmTestPhotosTest`. První ostrý test žehlení na S1 #1 (bílá PLA+, výchozí 10 % / 30 mm/s / 0,15 mm)
běží od 10. 10. večer.

## 3. Rychlost a kvalita (Romanova otázka 10. 10.)

Profil S1 dnes: vnější stěna 200 mm/s, vnitřní 300, výplň 270, horní plocha 200, zrychlení 10 000 (vnější stěna
5 000). Místní řez objektu `seam` (malý díl, kde vládne zrychlení a doba vrstvy; u velkých dílů bude rozdíl větší):

| nastavení | čas |
|---|---|
| dnešní | 28 min 9 s |
| vnější stěna a horní plocha 100 mm/s, zbytek beze změny | 30 min 14 s (+7 %) |
| všechny tiskové rychlosti na polovinu | 31 min 37 s (+12 %) |

Vzhled dělá vnější stěna a horní plocha; vnitřní stěny a výplň vidět nejsou. Kandidát k otestování: `outer_wall_speed`
100–120 a `top_surface_speed` 100–150 na `quick` / `seam`, nejdřív u silku (lesk ukáže každou změnu rychlosti).
Netestováno tiskem.

### 3.1 Posun filamentu = objemový tok, ne rychlost (Romanův dojem 10. 10. večer)

Z G-kódu T26-000030 (serverová Orca, PLA+ na S1, profil Standard): slicer už dnes **kapuje každý pohyb na
`filament_max_volumetric_speed = 16 mm³/s`**. Nominální rychlosti se proto skoro nikde nedosáhnou:

| prvek | v profilu | skutečně v G-kódu (medián) | tok (p95) | podíl času nad 12 mm³/s |
|---|---|---|---|---|
| vnější stěna | 200 mm/s | 193 | 14,8 | 69 % |
| vnitřní stěna | 300 | 193 | 16,0 | 74 % |
| řídká výplň | 270 | 193 | 16,0 | 100 % |
| plná výplň | 250 | 216 | 16,0 | 100 % |
| horní plocha | 200 | 200 | 15,2 | 100 % |

Anycubic dává svému profilu PLA pro S1 **12 mm³/s** (`filament_pla.json`); knihovna farmy to zvedla na 16 (PLA+) a 18 (PLA).
Snížení čísel rychlostí (300 → 250 u vnitřních stěn) tedy neudělá nic – ty se nedosahují; páka na podkluzování
extruderu je ten jeden strop toku. Místní řez objektu `seam` (prvky 20 mm od sebe):

| strop toku | čas | skutečná rychlost stěn / výplně |
|---|---|---|
| 16 mm³/s (dnes PLA+) | 27 min 5 s | 174–190 mm/s |
| 12 (Anycubic) | 29 min 22 s (+8 %) | 150–162 |
| 10 | 32 min 4 s (+18 %) | 125–135 |

„Dynamické zpomalení malých stěn“ slicer dělá sám, per pohyb: strop toku, `small_perimeter_speed` 50 % (smyčky do
~41 mm obvodu), `slow_down_layer_time` 8 s s `slow_down_min_speed` 20, rychlosti převisů a mostů, a v tiskárně
Klipper pressure advance 0,035. Vlastní přepočet rychlostí v G-kódu by to dubloval a hádal se s pressure advance
a plánovačem – nedělat. Další páka přímo na změny toku: `max_volumetric_extrusion_rate_slope` (dnes 0 = vypnuto).
U S1 Combo může stejné příznaky dělat i odpor filamentu v hadičkách ACE – rozliší to tisk s cívkou z přímého vstupu.
Kandidáti k testu (až Roman popíše příznak): `quick` s `t_filament = {"filament_max_volumetric_speed":"12"}` proti
dnešku; případně nový objekt „věž toku“ (patra 10 → 20 mm³/s přepisem F v G-kódu jako u teplotní věže) pro zjištění
skutečné hranice hotendu s danou cívkou.

### 3.2 Pruhy na výškách, kde končí nižší prvky (samostatná věc, otevřeno)

Pozorováno na `quick` z Maxu (T26-000033 modrá, T26-000035 tyrkysová): na boku kostky vodorovný schodek asi ve 3/4
výšky, pod horní hranou pás svislých zoubků, na pilířích prstence – vždy ve výškách, kde končí lamely (10 mm), most
(12 mm) a kostka (15 mm). Jmenovitá rychlost stěny (120 → 100) na to vliv neměla. Vysvětlení k ověření: v těch vrstvách
se skokem zkrátí doba vrstvy, slicer zpomalí (`slow_down_layer_time` 8 s, `slow_down_min_speed` 20) a vnější stěna
jede úplně jinou rychlostí, tedy s jiným leskem a jinou šířkou čáry. Kandidáti na test, po jednom: `slow_down_layer_time`
(8 → 5), `slow_down_min_speed` (20 → 40–60), případně `slow_down_for_layer_cooling`. Nejdřív ale objekt `seam` na
Maxu (stálý průřez po výšce): ukáže, jestli je stěna bez skoků v době vrstvy čistá. Profily se kvůli tomu zatím nemění
(dohodnuto s řídící session 10. 10.). U zákaznických modelů s více díly různé výšky na jedné podložce to bude vidět stejně.

## 4. Ruční testy Romana mimo farmu (část 6 zadání)

| kdy | stroj, cívka | co | hodnota | výsledek |
|---|---|---|---|---|
| 10. 10. večer | Kobra 3 Max, PLA+ modrá | horní povrch, `top_solid_infill_flow_ratio` (řezáno v Orce na Romanově PC) | nejlepší 1,0 (= dnešní hodnota) | Roman i tak nespokojen |

Závěr: poměr toku horní plochy zůstává 1,0; podezřelý je tok – horní plocha jede 200 mm/s × 0,42 × 0,2 = 16,8 mm³/s,
tedy na stropu 16 (viz 3.1), kde tryska nestíhá. Další test na Maxu: `quick` s `{"top_surface_speed":"120"}` (10 mm³/s).

**T26-000033 = M120** (10. 10. večer, Kobra 3 Max, PLA+ modrá, `quick` s `{"top_surface_speed":"120"}` – Roman
potvrdil, že je z Maxu; „test s procesem navíc“ z 19:1x byl tenhle, **S2 na S1 se zatím netiskl**). Snímky: 7 z nízkého
úhlu při expozici −6 (pro tmavě modrou málo) a jeden s deskou naklopenou ke kameře při −5 (19:44) – ten je čitelný.

| co | nález |
|---|---|
| horní plocha kostky | uzavřená, bez děr, ale čáry stojí jako oddělené hřebínky s rýhami mezi sebou (ne slitá plocha) |
| bok kostky | **nejvýraznější vada**: lesklé a matné pruhy po vrstvách, zřetelný vodorovný schodek asi ve 3/4 výšky, šikmý vzor – nerovnoměrné kladení vnější stěny |
| pilíře | vodorovné prstence a hrbolky po výšce, bez vlásků mezi pilíři |
| most, převisy | most rovný, lamely čisté |
| tenká stěna | vodorovně pruhovaná |

Pruhy a schodek sedí na výšky, kde na `quick` končí nižší prvky (lamely 10 mm, most 12 mm, kostka 15 mm): doba vrstvy
se tam skokem zkrátí, slicer zpomalí (`slow_down_layer_time` 8 s) a vnější stěna jede jinou rychlostí → jiný lesk.
Zčásti je to vlastnost zkušebního objektu, ale ukazuje, jak moc je vzhled stěny na Maxu citlivý na rychlost.
Srovnávací tisk z Maxu s 200 mm/s z farmy není (Romanův ruční test byl mimo farmu), takže jestli 120 horní plochu
zlepšilo, umí říct jen Roman. Další kroky na Maxu, po jedné změně: (1) `quick` s
`{"top_surface_speed":"120","outer_wall_speed":"100"}` – bok kostky; (2) objekt `ironing` se zaškrtnutým žehlením –
horní plocha (dnes `ironing = no`).

**Oprava podle stránky řádku (screenshot 10. 10. večer):** řádek PLA+ na Farm U #3 (Max) je **verze 3, stav
„testuje se“, 4/5** a už přepisuje: tryska 210 / 215 °C, `outer_wall_speed = 120`, žehlení 12 % / 30 mm/s / 0,1 mm,
`support_threshold_angle = 25`, `max_bridge_length = 25`. T26-000033 tedy tiskl vnější stěnu nominálně 120, ne 200 –
pruhy na boku kostky vznikly už při 120. Řádek PLA+ na Farm U #1 (S1) je **verze 1 z knihovny, stav „testuje se“**,
ne vyladěný, jak psalo zadání.

Spuštěno 10. 10. ~19:50: **Max** `quick` z cívky tyrkysová (ne modrá jako T26-000033 – jiná cívka, srovnání boku je
jen orientační) s `{"top_surface_speed":"120","outer_wall_speed":"100"}`; **S1 #1** objekt `ironing` (bílá, žehlení
zapnuté) s JSON pro S2 v poli procesu – ten u objektu `ironing` nic nedělá (bez `seam_slope_type` se scarf nezapne),
je to tedy zkouška žehlení na S1, **ne S2**. S2 (objekt `seam` + zaškrtnutý scarf + JSON) zbývá.

**T26-000035 – tyrkysový `quick` z Maxu** (10. 10. ~20:45; `{"top_surface_speed":"120",
"outer_wall_speed":"100"}`; 3 snímky při expozici −5, jeden s deskou naklopenou):

| co | nález | proti modrému T26-000033 (stěna 120) |
|---|---|---|
| horní plocha kostky | rovná, uzavřená, čáry jemné, nestojí jako hřebínky | lepší na pohled, ale jiná barva a lesk – ne čisté srovnání |
| bok kostky | vodorovný schodek/rýhy asi ve 3/4 výšky zůstaly; pod horní hranou **pás svislých zoubků** vysoký asi 2 mm, zbytek stěny zrnitý | pruhy nezmizely |
| pilíře | prstence a hrbolky, **jemné vlásky** mezi pilíři a na nich | vlásky u modré nebyly (jiná cívka) |
| most, převisy | rovný, čisté | stejné |

**Závěr: zpomalení vnější stěny 120 → 100 vady boku neodstranilo – nepřebírat.** Vady sedí na výškové pásy, kde na
`quick` končí nižší prvky, tedy na skokovou změnu doby vrstvy (slicer tam zpomalí kvůli `slow_down_layer_time` 8 s),
ne na jmenovitou rychlost stěny. `quick` je na posouzení stěny špatný objekt. Další krok: objekt `seam` **bez scarfu
na Maxu** – válec a hranol mají po celé výšce stejný průřez, takže ukážou, jak stěna vypadá bez skoků v době vrstvy.
Horní plocha při 120 vypadá dobře; když to Roman potvrdí, `top_surface_speed = 120` do řádku Maxu jako nová verze.

Hodnoty Orca profilu, které řádek Maxu nepřepisuje: `top_solid_infill_flow_ratio = 1`, `top_surface_speed = 200`, `top_shell_layers = 5`,
`top_surface_pattern = monotonicline`, `only_one_wall_top = 1`; Max jede s procesem S1 bez přepisů pro velkou
podložku. Až Roman napíše výsledek: kandidát do řádku Max × PLA+ a ověření testem `quick` z farmy (horní plocha
kostky) spolu s `top_surface_speed` 100–150.

## 5. Testy

`FarmTuningTest` (+2: test švu od tisku po převzetí do řádku; nevyhodnocený formulář), `FarmNozzleTest` (objekt
`seam` vodotěsný pro trysku 0,4 i 0,2), `FarmTestPhotosTest` (+2: test `seam` se ptá na šev a ne na to, co na
objektu není; ostatní objekty se na šev neptají), `TuningAdvisorTest` beze změny. Skutečné volání AI nad fotkou švu
proběhne až s prvním tiskem – pokyny pro model jsou zatím neověřené na reálné fotce. Vzhled stránky v prohlížeči jsem neověřoval,
jen obsah HTML v testech.
