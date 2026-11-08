# Kopie jednoho kusu jako instance jednoho objektu (TODO #77)

Pro session 2, 8. 11. 2026. Roman: „stromeček se slicuje hodně dlouho a pokud dám 4 ks, tak se nedoslicuje, čekal jsem
dlouho“. Co se stalo: 7. 11. stromeček (po ztenčení 187 226 trojúhelníků, 140 × 138 × 200 mm, 10 % povrchu v převisu přes
45°, tedy podpory potřebuje) 1 ks na Kobře S1 se stromovými podporami připravený za 61 s; po přepnutí na 4 ks slice
nedoběhl za 9 minut, Roman zrušil. 8. 11. totéž pro Kobru 3 Max: dva pokusy po 600 s (`ORCA_TIMEOUT`) zabity, ve
`storage/app/slicer/job_SS5HTgJyCodX` a `job_C6DqVUqFqlra` zůstaly `out_sup/plate_1.gcode.tmp` o 250 a 200 MB a log plný
`Influence area could not be increased` (organické podpory). Bez podpor 4 ks na Core One za 1 min 45 s.

Worktree `C:\matplace-gcode-wt`, nová větev `feature/copies-as-instances` z `origin/main` (na startu `git fetch origin`).
Souvisí s #72 (`fix/one-plate-copies`, stejný worktree, čeká na „spusť“): obě úlohy mění `ThreeMfWriter::write`
o posuny kopií, udělej je za sebou na jedné větvi nebo #77 postav na větvi #72, ať se parametr nepíše dvakrát. Po pushi
napiš řídící. Nic nenasazuj, žádný `migrate*` ve worktree (sdílená dev.sqlite, testy běží v paměti), git add jen jménem,
žádný stash, commit s `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`. Texty: nové JSON klíče přes
`lang/src/copies_instances.json` + `python scripts/lang_add.py`; `farm.*` v `lang/{cs,en,es}/farm.php` uprav přímo ve
všech třech. Každý JSON, kterého se dotkneš, projdi `json.load` s kontrolou duplicitních klíčů. PHPUnit po jedné sadě
(`php -d memory_limit=2G vendor/bin/phpunit --filter …`), nikdy dvě naráz.

## Proč to dnes trvá tak dlouho

- `PrepareFarmOrder::handle` (řádky ~197–215): u `copies > 1` se kus zopakuje do jednoho STL
  (`PlateLayout::replicate($piece, plate.stl, $perPlate, $usable)`, mřížka nebo řady podle stínu) a to STL jde do
  `OrcaSlicer::slice` jako jeden objekt. Orca tedy počítá všechny kusy znovu a stromové podpory všech kusů naráz, které se
  navíc navzájem ovlivňují. Server (Hetzner CX33, 4 sdílená vCPU) potřebuje na JEDEN kus s podporami asi 2 min, čtyři
  v jednom objektu přesáhnou 600 s; pak `ProcessTimedOutException` → `fail('slicing_failed')`, zákazník vidí „Tisk se
  nepodařilo připravit“.
- Podpory farma zapíná jako `tree(auto)` se stylem `default` = organic (`SliceParams(treeSupports: true)`), což je
  z Orcových stylů ten nejrychlejší; jiné nastavení nepomůže (měření níže).
- Odhad doby slicu pro velké modely (`BigModel::expected`, `farm.big_model.seconds_per_million`, na produkci 60) počítá
  trojúhelníky × kopie; podpory nevidí.

## Co řídící naměřil (Orca 2.4, 12 jader, profil Kobra 3 Max, proces standard + `ONE_PLATE_PROCESS` věž vypnutá)

| co | čas slice |
|---|---|
| 1 ks bez podpor | 14 s |
| 1 ks stromové (organic, dnešní výchozí) | 31 s |
| 1 ks normální | 28 s |
| 1 ks tree_slim / tree_strong / tree_hybrid | 91 až 95 s |
| slepená podložka 4 ks, normální | 178 s |
| slepená podložka 4 ks, stromové | 253 s |
| slepená podložka 4 ks, tree_slim / hybrid | přes 900 s, zabito |
| **4 ks jako jeden objekt se 4 build items ve 3MF, stromové** | **65 s** |

V logu Orcy u instancí: `Slic3r::Print::process: total object counts 4 in current print, need to slice 1` a
`copy_layers_from_shared_object`: kus počítá jednou včetně podpor a vrstvy kopíruje. Spotřeba a čas tisku vyšly jako
4 × jeden kus (810 g, 1 d 19 h). Server je proti PC zhruba 4× pomalejší, tedy odhadem 4 až 5 min místo přes 10.
Pozor: s rozestupem 12 mm Orca skončila `gcode path conflicts found between instances` (podpory přesahují stín kusu),
s 30 mm prošla. Generátor testovacího 3MF má řídící ve scratchpadu (`tree/make_instances_3mf.py`: jeden mesh objekt,
N `<item objectid="1" transform="1 0 0 0 1 0 0 0 1 dx dy dz"/>`), když ho budeš chtít, řekni.

## Co udělat

### A. Instance místo slepeného STL

- V `PrepareFarmOrder` u `copies > 1` (obě podložky: plná `perPlate` i poslední `rest`) postavit vedle `plate.stl`
  projekt `plate.3mf`: jeden objekt (ztenčený kus `print.thin.stl`, nebo `print.stl`, když se neztenčoval; stejný výběr
  jako dnes `$sliceRel`) a `perPlate` build items s posuny tam, kam `PlateLayout::replicate` dnes kusy klade. Z
  `replicate`/`replicateGrid`/řad vytáhni výpočet pozic do veřejné metody (seznam `[dx, dy, otočení]`; mřížka umí kus
  otočit o 90°, to je v matici transformace `0 1 0 -1 0 0 …`), slepené STL z nich dál piš, slouží náhledu
  (`print_stl_path`, `model.stl?v=`), rozměru pro `ModelValidator::judge` a `supports.bin`.
- `ThreeMfWriter::write`: nepovinný parametr s posuny kopií na objekt (totéž, co chce #72 pro díly nástroje). Souřadnice
  nech, farma umisťuje sama a slicuje s `--arrange 0` (`OrcaSlicer::slice`: `$params->project` → `$placed = project`,
  mesh vedle jen pro velikost; `thinMesh` se u projektu neztenčuje znovu, kus už ztenčený je).
- Do `SliceParams` projekt předat (`project: plate.3mf`), `CachedSlicer::key` dnes hashuje `md5_file($meshPath)` a
  `params->toArray()`: ověř, že u projektu je v klíči i obsah projektu (md5 souboru), ne jen cesta, jinak se po změně
  rozestupu vrátí starý G-kód z cache.
- Rozestup instancí: `PlateLayout::GAP_MM` 5 mm je pro kusy bez podpor. Když podpory mohou být (`enable_support` 1 v
  overrides, nebo `supports === null`, protože druhý pokus `attempt(true)` je zapne sám), vezmi rezervu na podpory:
  navrhuji `max(GAP_MM, min(30, 0.15 × výška kusu))` a kapacitu podložky (`capacityOf`, `plan`) počítat se stejným
  rozestupem, ať sedí `max_copies` na stránce i počet podloží. Když Orca přesto skončí kolizí drah
  (`CLI_GCODE_PATH_CONFLICTS`, v `00000.log` „gcode path conflicts“, bez G-kódu), spadnout zpět na dnešní slepené STL,
  ne na `slicing_failed`; do `slice_result` zapsat `instances: true|false`, ať je ve zprávě vidět, kudy to šlo.
- `BigModel::expected`: u instancí počítat trojúhelníky × 1 (plus malý příplatek za instanci, třeba 10 %), ne × kopie;
  verdikt `copies` nad `big_slice_s` by jinak čtyřkusové podložky odmítal zbytečně.
- Limit sliceru: `engines.orca.timeout` nechat 600 s pro jeden kus, u podložky s více kusy dovolit víc (návrh
  `600 + 60 × (perPlate − 1)`, strop 1200; Roman rozhodne číslo, dej mu do zprávy obě varianty). Job má `timeout` 900 s
  a čeká na slot (`slot()`), součet musí zůstat pod `timeout` jobu, jinak ho fronta zabije a nechá `job_*` složku.
- Co se nemění: tool changer (`toolPlan`, výměny podle výšky přes `ThreeMfWriter` `$toolChanges`, řádky ~1134–1151),
  malovaný 3MF (`repaint` už instance píše), díly nástroje (#72), časosběr (`TimelapseGcode`), `rest.gcode` poslední
  podložky, `supports.bin` (čte se z G-kódu, ten má všechny instance).

### B. Stránka a zpráva

- Stránka zakázky ani kalkulačka se nemění; jen kdyby nový rozestup snížil `max_copies`, hláška „na podložku se vejde N“
  už existuje (`check.max_copies`), ověř, že se přepočítá po `reslice`.
- Do mailu obsluze o přípravě nic nepřidávej; do `slice_result` `instances` a `gap_mm`.

### C. Testy

- `ThreeMfWriterTest`: s posuny vznikne N build items se správnými maticemi, bez posunů jeden s jednotkovou maticí
  (stejný test ať sdílí #72).
- `PlateLayoutTest`: pozice z mřížky i z řad odpovídají tomu, co dnes končí ve slepeném STL (porovnej obálky kusů).
- `FarmOrderFlowTest`/`PrepareFarmOrder` s falešným slicerem: u `copies = 4` dostane slicer projekt a vedle něj
  `plate.stl`, `slice_result.instances` true; u kolize drah (falešný slicer vrátí chybu s textem „gcode path
  conflicts“) druhý běh se slepeným STL a `instances` false; u `copies = 1` beze změny.
- `BigModel` test: očekávání s instancemi × 1.
- Skutečná Orca (jen na Romanově PC, `ORCA_LOCAL`, vzor `ThreeMfColorsTest`): kostka 4× s podporami → G-kód má 4
  `; start printing object`/instance a log `need to slice 1`. Řídící ti dá cestu k místní Orce 2.4.

### D. Zpráva řídící

Čísla před/po na stromečku (řídící dodá `piece.stl`), co se stane při kolizi drah, jaký rozestup a limit jsi zvolil
a co má Roman rozhodnout (limit sliceru, rezerva na podpory).
