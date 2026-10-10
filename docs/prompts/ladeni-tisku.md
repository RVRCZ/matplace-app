# Zadání pro session F: ladění tiskových parametrů farmy (všechny nabízené materiály × stroje)

Cíl: každá kombinace **typ stroje × druh filamentu**, kterou farma nabízí, má v administraci **vyladěný řádek**
(`/admin/farm/tuning`, stav `tuned`), ověřený zkušebním tiskem a fotkami, a zapsaná pravidla, podle kterých poradce
navrhuje úpravy. Dnes je vyladěné jen PLA+ na S1 a rozpracované PETG na S1 #2 (řádek #27, viz
`docs/prompts/petg-s1-02.md` ze 4. 10. – přečti si ho celý, část „Jak ladění ve farmě funguje“ je mapa kódu
a administrace, platí beze změny).

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-tuning-wt`**, větev **`feature/print-tuning`** (z `main`, 10. 10. 2026). Připravené: `.env`
  (sqlite, `APP_URL=http://localhost:8019`, `PYTHON_BIN`), `node_modules` junction do `C:\matplace-app\node_modules`
  (nemaž, nepřepisuj), `vendor`, `public/build`. Dokument: **`docs/V.md`**.
- Většina práce je **provozní**: administrace farmy na matplace.com (Roman ti dá přístup nebo kliká sám podle tvých
  pokynů), tiskárny a foto‑box. Kód se mění jen tam, kde ladění ukáže, že poradce, knihovna profilů nebo stránka
  ladění neumějí, co je potřeba (část C).
- Na server se připojuj **jen pro čtení** (`ssh -i ~/.ssh/hetzner_matplace root@178.104.162.164`,
  `/var/www/matplace-app`, `php artisan tinker` jen s dotazy; žádné zápisy do databáze mimo administraci, žádné
  `queue:retry all`, žádný restart služeb). Nasazuje řídící session (`conceprt19092026-50`), ty **nemerguješ do `main`**;
  commity po celcích, push, zpráva řídící session.
- **Nikdy `taskkill /F /IM python.exe`** (na tomhle počítači běžel agent farmy; teď běží na jiném počítači, ale pravidlo
  platí). Jedna session na worktree, `git add` jen jmenovitě, žádný `git stash`, texty jen v `lang/{cs,en,es}/*.php`.
- **Souběh:** soubory farmy (`app/Domain/Farm/*`, `app/Jobs/PrepareFarmOrder.php`, `resources/js/calc/farm.ts`,
  `resources/views/admin/farm/*`) má řídící session; ty v nich měníš jen `TuningAdvisor.php`, `ProfileLibrary.php`,
  `TestPrintService.php`, `database/data/farm_profile_library.json`, `engines/orca/profiles/*` a stránky ladění
  (`tuning*.blade.php`, `FarmTuningController`) – a před každým commitem `git fetch origin main && git merge origin/main`.
  Když chceš sáhnout jinam (třeba `PrintProfile`), napiš řídící session první.
- Model: Fable 5.1 (dlouhé smyčky s hodnocením fotek a čísel) nebo Opus 5.5.

## 1. Co se ladí (podle rozložení cívek z `docs/FARM-COLORS.md` §7)

| Stroj | Druhy filamentu k vyladění | Poznámka |
|---|---|---|
| Kobra S1 (10 ks, uzavřená, tryska 0,4) | **PLA+** (je vyladěné – ověř, že řádek sedí i pro řadu PLA+ 2.0), **PLA Silk**, **PLA svítící**, **PLA+ matný / PLA matný**, **PLA třpytivý**, **PETG** (řádek #27 dokončit), **ASA** | silk: nižší rychlost stěn, vyšší teplota, lesk; svítící: abrazivní (tryska), vyšší teplota; matný: slabší most; PETG: sušení, retrakce, ventilátor; ASA: uzavřený prostor, podložka 90–100 °C, bez ventilátoru |
| Kobra 3 Max (1 ks, otevřená, 420 mm) | **PLA+** (velké díly: průvan, deformace, adheze na velké ploše) | jen PLA+; PETG a ASA se na Max nenabízejí |

Pořadí podle toho, co se bude tisknout nejdřív (prodeje: PLA+ světle modrá, svítící zelená a červená, silk měděná a zlatá,
PETG oranžová; vánoční sada = PLA+ a silk):
1. PLA+ na S1: ověřit, že vyladěný řádek platí pro nové cívky (PLA+ 2.0), jinak nová verze.
2. PLA Silk na S1 (Farm B #6, #7).
3. PLA svítící na S1 (Farm B #8).
4. PETG na S1 (Farm U #2) – dokončit řádek #27 podle starého zadání.
5. PLA+ matný / PLA matný a třpytivý (Farm U #1).
6. ASA na S1 (Farm U #2).
7. PLA+ na Kobra 3 Max (Farm U #3).

## 2. Jak postupovat u každého řádku (smyčka)

1. **Výchozí hodnoty**: `/admin/farm/tuning/{row}` ukáže účinný profil (knihovna `farm_profile_library.json` →
   řádek → přepisy tiskárny). Když knihovna druh nezná (silk, svítící, matný, ASA), doplň do ní rozumný start (část C)
   z profilu výrobce (Anycubic/Orca profily v `engines/orca/profiles/`) a z obecně známých hodnot, s poznámkou, odkud.
2. **Sušení** tam, kde to má smysl (PETG, ASA, svítící po otevření): tlačítko *Sušit* u tiskárny, 4–6 h.
3. **Zkušební tisk**: objekt `quick` (35 min: kostka 15 mm, převisy 30–70°, most 20 mm, pilíře na stringing, stěna
   0,8, díra Ø8); `temp_tower` jen když si nejsi jistý teplotou (silk, svítící, ASA). Před každým tiskem napiš Romanovi,
   co má udělat (cívka ve slotu, podložka, foto‑box), a čekej.
4. **Hodnocení**: fotky z foto‑boxu nebo z mobilu → *Vyhodnotit z fotek (AI)* → zkontrolovat a doplnit měřené hodnoty
   (posuvka: kostka, díra) → *Uložit hodnocení* → buď *Zkusit navržené úpravy* (nová verze, max. 3 kola), nebo
   *Test je dobrý – označit jako vyladěné*.
5. **Zápis**: každý řádek, každé kolo a výsledek do `docs/V.md` (tabulka: stroj, druh, verze, co se změnilo, výsledek,
   test T26‑…, fotky). Hodnoty, které se osvědčí, zapiš i do knihovny, ať nový stroj stejného typu začíná správně.

## 3. Kód (část C – jen co ladění vyžádá)

- **`TuningAdvisor`**: pravidla jsou psaná pro PLA (ventilátor 100 %, retrakce 0,8 …). Rozšiř je **podle druhu**
  (`FarmMaterial::code` + `finish`): PETG (ventilátor 30–60 %, retrakce 1,0–1,4 mm / z‑hop, pomalejší stěny, teplota
  podle stringingu), silk (teplota +5–10 °C, stěny 40–60 mm/s, ventilátor 50–80 %), svítící (teplota, abrazivita jen
  poznámka), matný (most, slabší mezivrstvová vazba → teplota), ASA (bez ventilátoru nebo 10 %, podložka 95 °C,
  uzavřít, první vrstva pomalu). Testy `tests/Unit/TuningAdvisorTest.php` pro každý druh: vstup hodnocení → návrh.
- **`ProfileLibrary` / `farm_profile_library.json`**: položky pro `kobra s1` × silk / luminous / matte / glitter / ASA
  a `kobra 3 max` × PLA+ s velkou podložkou; `ProfileLibrary::sync()` a `startingValues()` je převezmou.
- **Stránka ladění**: jen když ti při smyčce něco chybí (např. pole, které se nedá přepsat, chybějící objekt testu);
  každá změna s testem ve `FarmTuningTest`.
- **`TestPrintService`**: objekt `quick` pokrývá většinu; pro ASA/PETG zvaž `warp` (plochá deska 80 × 20 s rohy) jen
  pokud deformace rohů bude problém – nový objekt = `calib_tool.py` + položka v `OBJECTS`, s testem.

## 4. Co hlídat

- Farma musí zůstat v provozu: zkušební tisky jsou zakázky `kind = test`, nic neplatí, ale berou stroj; domluv s Romanem,
  kdy který stroj ladíš, a nikdy neměň řádek, podle kterého právě běží zákaznický tisk (`PrepareFarmOrder` čte řádek
  při řezání, běžící tisk už to neovlivní, ale fronta ano).
- Každá změna řádku je nová verze s historií; nikdy nepřepisuj řádek `tuned` bez testu.
- Výstup: na konci má `/admin/farm/tuning` zelené (vyladěné) řádky pro všechny kombinace z tabulky v části 1, `docs/V.md`
  s historií a čísly, knihovna profilů doplněná, poradce s pravidly podle druhu a testy. Co se nestihlo nebo nešlo,
  napiš s důvodem.
