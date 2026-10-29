# Agent 0.2.9: hlášky z tiskáren, uvolnění podložky na displeji, soubory na disku tiskáren (session 4)

Zadal Roman 29. 10. 2026 večer po zakázce F26‑000108 na Farm B #3. Řídící session: `conceprt19092026-91`.
Čti nejdřív `docs/AM.md` (celý ovladač, zkoušky na tiskárnách, instalace agenta na PC farmy) a `docs/prompts/koordinator.md` (pravidla).

## 1. Co se stalo a co víme

F26‑000108, 12 ks, jedna barva, B3 (Kobra S1, Rinkhals, ovladač `moonraker`), slot 3 v databázi = pozice 4 ACE (oranžová PLA+).

- 20:20 místního času zaplaceno, 20:21 agent stáhl G‑kód, 20:22 tiskárna tisk přijala.
- 20:30 tiskárna sama nahlásila `paused`. Vrstva 0 z 27, postup 0 %, filament −2 mm (jen zpětný tah při pauze), tryska 140 °C, ACE `standby`. Nic nebylo vytlačeno, ani čisticí linka.
- Zpráva od tiskárny u nás prázdná (`job.message`), žádný mail obsluze. Roman: hláška byla jen na displeji tiskárny. Stejné se stalo už dřív u jiného tisku, po „resume“ na displeji si tiskárna natáhla filament a tiskla.
- Mechanismus: server vkládá do G‑kódu po nahřátí řádek `T3` (`GcodeSlot::retarget`), tedy „zaveď cívku z pozice 4“. Přesně tam tisk skončil. ACE cívku nezavedl (konec filamentu v podavači, filament z jiné pozice v hlavě, cokoli), Rinkhals tisk pozastavil a důvod ukázal jen na displeji.
- „Pokračovat“ z adminu (příkaz 264): `POST /printer/print/resume` skončil `TimeoutError` (ovladač čeká 15 s, Rinkhals při resume nejdřív zavádí filament). Příkaz je u nás `failed`, tiskárna přesto pokračovala a tiskne. Totéž u úlohy 155 (B8, 23. 10.).
- Stav slotů ACE před startem nikdo nekontroluje: `spool_at` v LAN ovladači má `status` (5 zavedeno, 4 připraveno), Moonraker `filament_hub` stav slotů hlásí také, ale start je bere jen jako pozice.

Zjištění řídící k datům: `FarmPrintJob` 164, `FarmCommand` 259 a 264, telemetrie B3, snímek kamery (prázdná podložka). Číslování slotů je 0 až 3 v databázi i v ovladači, slot 3 tiskl na B4, B7 a U2 bez potíží.

## 2. Cíl, tři části

### A. Všechny hlášky z tiskárny u nás

1. Ovladač `moonraker` (Kobra S1 s Rinkhals: B1 až B8, U1, U2, K1, U3): posílej `display_status.message`, `print_stats.message`, stav a chybu `filament_hub` / `ota_filament_hub` (slot, co dělá, proč stojí), `webhooks.state_message` při chybě Klipperu. Zjisti, co Rinkhals při pauze skutečně vystaví (důvod pauzy, výzva k zavedení filamentu, `gcode_store`, M117). Co najdeš, zapiš do AM.md s ukázkou odpovědi.
2. Ovladač `anycubic_lan` (Kobra X C1): kromě `err_msg` posílej stav projektu, důvod pauzy a text, který tiskárna ukazuje na displeji (kobra_connect modely, `project.pause`, případně další pole hlášení). Co protokol nedává, napiš.
3. Agent posílá hlášku v každém hlášení: `job.message` (jak dnes) a nově `telemetry.message` u tiskárny, i mimo tisk (výzva na displeji, chyba ACE, sušení). Hláška se nesmí ztratit tím, že ji agent pošle jen jednou.
4. Server: hlášku ukládej na úlohu (`message`, čas) a veď krátkou historii hlášek úlohy (posledních 20, JSON na úloze nebo malá tabulka, zvol a zdůvodni). Admin ji ukazuje u řádku tiskárny (`/admin/farm`) i u zakázky, s časem.
5. Mail obsluze při pauze: nový druh `paused` v `alertAdmin` s přepínačem `notify_paused` v `/admin/farm/settings` (vzor: `notify_problem`, AL.md §8). Jeden mail při pauze s hláškou a odkazem na zakázku, žádné opakování. Při pauze bez hlášky napiš „tiskárna důvod neposlala“.
6. Příkazy pause, resume, cancel z adminu: po `TimeoutError` neoznač příkaz `failed`, ale do 60 s čti stav tiskárny. Když se stav změnil (paused → printing u resume), příkaz je `done`. Když ne, `failed` s textem.
7. Start: před startem ověř stav zvoleného slotu (Moonraker `filament_hub`, LAN `status`). Prázdný slot = odmítni start s důvodem (existující `start_failed` mail a stav v adminu), obsluha cívku doplní a tisk spustí znovu. Pokud firmware stav slotu nedává, start pokračuje jako dnes a hláška to řekne.

### B. Uvolnění podložky na displeji

Dnes: po dotištění další úloha čeká, dokud obsluha v adminu nepotvrdí „Podložka uklizena“ (`Dispatcher` stav `plate`, `FarmPrinter.bed_clear`, `FarmOrderController::bed`).

Cíl: obsluha stojí u tiskárny, sundá výtisk a potvrdí to na displeji tiskárny. Agent to pozná a server nastaví `bed_clear = true`, úloha ve frontě vyjede sama. Tlačítko v adminu zůstává.

Zjisti u obou firmware, co displej po dotištění ukazuje a co se dá číst: Kobra S1 s Rinkhals (dialog „tisk dokončen“, stav `print_stats.state` complete → standby po potvrzení, nebo jiný údaj Moonrakeru), Kobra X v LAN režimu (AM.md §6d: dokončený tisk v `last_project` není, hledej stav po potvrzení dialogu). Když firmware potvrzení nevystavuje, napiš to a navrhni nejbližší náhradu, kterou obsluha udělá u tiskárny bez adminu. Žádné odhady z kamery.

### C. Soubory na disku tiskáren

1. Nové příkazy agenta: `files` (seznam souborů na tiskárně: jméno, velikost, datum) a `fetch` (stažení jednoho souboru na server). Moonraker: `/server/files/list?root=gcodes` a `/server/files/gcodes/<jméno>`. LAN Kobra X: zjisti, jak Slicer Next nebo aplikace čte seznam místních souborů; když protokol nic nedává, napiš to a Kobra X zůstane bez.
2. Server: nový koncový bod pro agenta, který přijme soubor (token agenta jako dnes), uloží ho do `storage/app/farm/printers/{id}/files/`, limit velikosti 300 MB, jeden soubor naráz. Jméno souboru validuj, žádná cesta mimo složku.
3. Admin, stránka tiskárny: blok „Soubory na tiskárně“ s tlačítkem „Načíst seznam“ (výsledek příkazu uložený u tiskárny, s časem), řádky se jménem, velikostí a datem, u každého „Stáhnout na server“ a po stažení odkaz ke stažení do PC. Výroba modelu z G‑kódu (TODO #42, `/tools/gcode-model`) sem patří později, teď jen poznámka u souboru.

## 3. Kdo a kde

- Session 4 dělá agent, server i admin (jako dřív, AM.md). Soubory session 2 (`PrepareFarmOrder`, `OrderService`, `PlateLayout`, `GcodeSlot`) neměň, `Dispatcher` a `AgentService` jsou tvoje.
- Worktree `C:\matplace-sell-wt`, větev `feature/agent-messages` z main, vlastní `vendor` (composer install), `node_modules` odkaz, `.env` je. Build před testy stránek.
- Verze agenta `__version__ = "0.2.9"`, zápis do `docs/AM.md §10`, zip pro PC farem jako u 0.2.8.

## 4. Ověření

- Testy agenta (`agent/tests`, pytest) s falešným Moonrakerem a LAN hlášením: pauza s hláškou i bez ní, resume s timeoutem a změnou stavu, prázdný slot před startem, seznam a stažení souboru.
- PHPUnit: mail `paused` a přepínač, historie hlášek, `bed_clear` z agenta, koncový bod souborů (velikost, jméno, token).
- Na skutečné tiskárně jen na Romanovo „proveď zkoušku“ (PC farem instaluje Roman, AM.md §5): pauza při zavádění filamentu na S1, potvrzení na displeji, seznam souborů. Co bez tiskárny neověříš, napiš do AM.md jako neověřené.

## 5. Pravidla

- Texty jen přes `lang/src/*.json` a `python scripts/lang_add.py`, česky, krátce, bez pomlček. `git add` po jménech, žádný stash, žádné nasazení, klíče a hesla jen od Romana.
- Každý JSON, který upravíš, projeď validací.
- Po pushi pošli zprávu řídící session (`conceprt19092026-91`): větev, commit, co je ověřené, co čeká na tiskárnu.
