# Orientace: rovná plocha o 0,06 mm výš než ostří se nepočítá jako podložka; práh podpěr 35° (TODO #63, 1. 11. 2026, session G)

Roman 1. 11. 2026 večer: nahrál škrabku (zakázka `TiW9uMwgXu3l6scQ9r746miw3pjTNNgH`, 136 × 50,9 × 6,9 mm, Farm U #2, 1 h 32 min, 23,5 g), farma ji
postavila na hranu a Orca přidala stromové podpěry. Naležato by podpěry nebyly. Roman: „zadej a zvedni tvorbu podpěr na 35°“.
Kopie připraveného modelu (už v postavené poloze, originál nahrání není po ruce): `C:\tmp\scraper-print.stl`. Nic na produkci.

## Co řídící naměřil (engines/python/farm_tool.py, `rate()`, práh převisu 40°, podložka 250)

| Poloha | převis podle enginu | plocha na podložce | skóre (nižší vyhrává) |
|---|---|---|---|
| nastojato (zvolená) | 2 188 mm² (13,4 %) | 179 mm² (1,1 %) | +0,120 |
| naležato | 3 707 mm² (22,7 %) | 46 mm² (0,3 %) | +0,231 |
| naležato s tolerancí 0,3 mm / 3° | asi 100 mm² | 3 673 mm² (22,5 %) | asi −0,25 |

Velká plocha je rovná (3 063 mm² do 0,5° od roviny, proložená rovina 0,01°), ale ostří je o 0,06 mm níž. `on_bed = (facing > 0.9995) & (face_z - z0 < 0.05)`
bere za podložku jen trojúhelníky do 0,05 mm od nejnižšího bodu a do 1,8° od vodorovné roviny, takže plocha ve výšce 0,06 mm neprošla, naležato
se celý spodek počítal jako převis a pojistka „nahraná poloha stojí dobře“ (2 % povrchu) se nespustila. Pravidlo skončí nastojato z obou startů.
Podpěry jsou důsledek: nastojato má model 2 100 mm² vodorovných spodků (schod, konec čepele). Soubor není uzavřený (4 494 otevřených hran), oprava ho nezavřela; s otočením to nesouvisí.

## A. Tolerance podložky při hodnocení polohy

1. V `rate()` ber za plochu na podložce trojúhelníky do **0,3 mm** nad nejnižším bodem a do **3°** od vodorovné roviny (`facing > cos(3°) ≈ 0.9986`). 0,3 mm je víc než první vrstva (0,2), takže co engine označí za „stojí“, Orca opravdu položí; ostří 0,06 mm pod plochou se v první vrstvě ztratí.
   Totéž místo používá `contact` (podíl stínu, který podložka nese) a pojistka `stands`; obě tím dostanou správná čísla.
2. Ověř na `C:\tmp\scraper-print.stl`: `python engines/python/farm_tool.py prepare in.stl out.stl 1 250 250 250 40 turn` musí dát naležato (výška 6,9) z postaveného startu
   i po otočení souboru naležato; `overhang_mm2` do 150, `base_mm2` kolem 3 600. Zkontroluj, že se tím nerozhodí modely z testů (FarmPreparedCacheTest, FarmOnePlateTest,
   testy tvarů běží s `keep`, těch se to netýká) a dva tři reálné soubory z `C:\Users\roman\Documents\MODELS` (jen čtení): nic, co dnes stojí dobře, se nesmí položit na bok.
3. Test: syntetická deska 136 × 50 × 6,9 se zkoseným ostřím o 0,06 mm pod plochou → po `prepare … turn` výška 6,9 a `orientation.changed` podle startu; a protipříklad: díl na malé nožce (stín 1 600 mm², noha 69 mm², viz komentář v `rate()`) se dál položí. Python test vedle existujících testů enginu, pokud repo nějaké má; jinak feature test v PHP, který se přeskočí bez pythonu (jak to dělají ostatní testy enginů).
4. Cache připravených modelů (`CachedPrintPreparer::key()`) musí poznat nový engine: přidej do klíče verzi orientace (např. `orient-v2`), jinak „Přeřezat“ u staré zakázky vrátí starou polohu. Ověř FarmPreparedCacheTest.

## B. Práh podpěr 35°

Výklad Romanova „zvedni tvorbu podpěr na 35°“: Orca `support_threshold_angle` = **35**, tedy podpěru dostane plocha plošší než 35° od vodorovné roviny (dnes výchozí 30 v `FarmSeeder`
a `TuningAdvisor`, ladění tiskárny ho přepisuje na 95 − čistý úhel z testu převisů s mezemi 15–60, takže po ladění bývá vyšší). Pokud Roman myslel něco jiného, řídící to upřesní; napiš do docs větu, co 35 znamená.

1. Farmová hodnota na jednom místě: `config('farm.support_threshold_angle', 35)`. `PrepareFarmOrder::sliceSetup()` ji zapíše do `process` nad profil a pod přepisy tiskárny, ať platí i pro tiskárnu bez přepisu.
2. Tiskárny mají dnes v `process_overrides` vlastní `support_threshold_angle` (30 ze seedu, po ladění jiné). Datová migrace nastaví 35 všem tiskárnám, které klíč mají; seed na 35.
3. `TuningAdvisor::supportSettings()`: ladění smí práh jen **snížit** pod farmovou hodnotu (tiskárna, která převisy nezvládá), nikdy zvednout nad 35. Text zdůvodnění (řádek ~105) a FarmTuningTest podle toho.
4. `max_bridge_length` 10 mm neměň (Roman o mostech nerozhodl); napiš do docs jako další volbu.

## Testy a odevzdání

- `php -d memory_limit=2G vendor/bin/phpunit --filter "FarmPreparedCache|FarmTuning|FarmOnePlate|FarmOrderFlow"`, nikdy dvě sady najednou. Python test enginu zvlášť.
- Větev `feature/orient-base-tolerance` z main 716fc82f. Texty jen přes `lang/src/*.json` + `python scripts/lang_add.py` (tady asi žádné). JSON po úpravě zkontroluj. Git add po jménech, bez stash. Soubory session 2 (#62: PruneData, ModelFile, OrderController, účet) nech být.
- Docs: docs/AL.md nový oddíl **§17 Orientace: tolerance podložky a práh podpěr 35°** (§16 má session 2 pro úklid souborů), s tabulkou výše a s tím, co 35° znamená. Řádek TODO #63 doplň po pushi.
- Po pushi napiš řídícímu (`conceprt19092026-b7`): sha, testy, výsledek `prepare` na škrabce. Po nasazení řídící požádá Romana o „Přeřezat“ u zakázky, nebo o novou objednávku.
