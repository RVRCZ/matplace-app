# AQ. Administrace farmy: stručný přehled, detail tiskárny, detail filamentu, farmy jako místa

Stav k 12. 11. 2026, větev `feature/admin-farm-overview`. Zadání: `docs/prompts/admin-tiskarny-prehled.md` + Romanovy
odpovědi z téhož dne (farma je místo s adresou; pozastavený tisk je problém). Nenasazeno, nesloučeno.

**Pozor při nasazení: větev nese migraci** `2026_11_12_100000_create_farms.php` (viz §1). Nikde sdíleně neběžela; běžela
jen v testech (paměťová databáze) a na soukromé kopii sqlite pro snímky.

## 1. Farma je místo (tabulka `farms`, `farm_printers.farm_id`)

- Model `App\Models\Farm`: `code` (U, B, S), `name`, `street`, `city`, `zip`, `country` (CZ), `note`, `sort`, `enabled`.
  `label()` = „Farma U · Útušice“, `addressLine()` = adresa na jednom řádku.
- `FarmPrinter::farm()`; `farm_id` je nepovinné (tiskárna bez farmy = „Nezařazené tiskárny“).
- Migrace založí farmy **U (Útušice), B (Plzeň, Bory), S (Stod)** s prázdnou ulicí a PSČ (doplní Roman) a jednorázově
  přiřadí tiskárny podle agenta: agent „Agent1 S1“ → U, agent „Farma B“ → B. Tiskárny bez agenta a tiskárny jiných
  agentů zůstanou nezařazené. **Kód tiskárny se nepoužívá** (U3 dnes stojí na farmě B). Další změny už jen ručně.
- Správa: `/admin/farm/farms` (seznam s úpravou na místě a založením nové farmy; mazání záměrně není, farma se vypne)
  a výběr „Farma (místo)“ v nastavení tiskárny. Formulář tiskárny bez pole `farm_id` farmu nemění.
- **Agent farmu nemá.** Farmu nese tiskárna; agent je program na počítači a jeho farma je vidět z jeho tiskáren.
  Kdyby jeden agent řídil tiskárny na dvou místech, nic se nerozbije.
- **Výdejní místo (zatím není):** pověsí se na farmu. Stačí farmě přidat `pickup_enabled` a nepovinnou adresu výdeje
  (`pickup_name`, `pickup_street`, …; prázdná = adresa farmy, vyplněná = spolupracující kamenná prodejna v místě),
  případně otevírací dobu. Zakázka by pak nesla `pickup_farm_id`.

## 2. Přehled „Tiskárny a fronta“ (`/admin/farm`, pohled `admin/farm/overview.blade.php`)

Seznam tiskáren po farmách v pořadí `sort`, nezařazené na konci. V záhlaví skupiny název farmy, adresa (když je
vyplněná ulice) a počet tiskáren. Blok „Čeká na vás“ zůstal nahoře beze změny. Řádek tiskárny:

| část | co ukazuje |
|---|---|
| název | kód, název (proklik na detail), model a agent |
| stav | jedno slovo a barva (§3); u problému pod zakázkou důvod |
| zakázka | běžící: číslo (proklik na zakázku), %, „hotovo asi HH:MM“ a proužek; jinak poslední dotištěná a kdy skončila |
| další | první zakázka ve frontě tiskárny (stav `queued`, pořadí `queued_at`) a „a dalších N“ |
| podložka | jedno tlačítko: „Uvolnit podložku“ (obsazená → volná), nebo „✓ Volná · zablokovat“ (volná → obsazená); při běžícím tisku jen text, tlačítko není |
| detail | odkaz na detail tiskárny |

- Tlačítko podložky posílá dnešní `POST /admin/farm/printers/{id}/bed` s `clear=1/0` (oba směry už existovaly, nic
  nového na serveru). Uvolnění dál spouští další zakázku z fronty (`Dispatcher::kick`).
- Obnovování je stejné jako dřív: obal `#farm-dashboard` s `data-refresh`, `bootFarmDashboard` v `farm.ts` ho každých
  30 s překreslí na místě a po každém tlačítku ukáže hlášku; bez JavaScriptu `meta refresh` v `<noscript>`. V JS se nic
  neměnilo.
- Telefon (390 px): řádek se skládá pod sebe, nic neleze do šířky (`scrollWidth` = 390), tlačítko podložky je přes
  celou šířku a vysoké 46 px. Od `lg` je řádek mřížka o pěti sloupcích.
- Poslední dotištěná = poslední zakázka tiskárny ve stavu `done` nebo `handed_over` podle `finished_at`.
  Odhad konce = `Dispatcher::estimate` (zbytek podle průběhu a korekce času tiskárny).

## 3. Stav tiskárny jedním slovem (`App\Domain\Farm\PrinterStatus`)

Jen zobrazení, v databázi nic nepřibylo. Platí první pravidlo, které sedí:

| stav | kdy |
|---|---|
| **Vypnutá** (`disabled`) | tiskárna není „v provozu“ |
| **Offline** (`offline`) | tiskárna řízená agentem se neozvala déle než `offline_after_seconds` (nastavení farmy, výchozí 120 s) |
| **Problém** (`problem`) | (a) agent hlásí stav `error` (důvod = poslední hláška tiskárny); (b) stav `unknown` nebo úloha ve stavu `unknown`; (c) **nepotvrzené zrušení**: k běžící úloze existuje příkaz `cancel`, který selhal, nebo je starší než 120 s a úloha stále běží; (d) **pozastavený tisk** (úloha nebo tiskárna `paused`; důvod = živá hláška tiskárny, jinak „Tisk je pozastavený“); (e) živá hláška tiskárny (`telemetry.message` = `message`), pokud tiskárna právě netiskne |
| **Tisk** (`printing`) | tiskárna má aktivní úlohu (i odesílanou a nahřívanou), nebo sama hlásí tisk; ruční tiskárna: má zakázku ve stavu `printing` |
| **Uklidit podložku** (`bed`) | volná, ale podložka není potvrzená jako uklizená (`bed_clear = false`) |
| **Standby** (`standby`) | volná a podložka je čistá: další zakázka se může spustit |

- „Online“ není zvláštní slovo: všechno kromě Offline a Vypnutá znamená, že je tiskárna online. Ruční tiskárna je
  online vždy (stojí u ní člověk), stav má podle zakázek a podložky.
- Hláška během tisku je jen poznámka (není to problém); stará hláška (už není v telemetrii) je historie.
- **Souběh s `fix/agent-cancel-confirm`:** jediné místo, kde se přehled ptá na nepotvrzené zrušení, je
  `PrinterStatus::cancelUnconfirmed()`. Až ta větev přinese vlastní značku, stačí ji tam přidat (`||`).

## 4. Detail tiskárny (`/admin/farm/printers/{id}/detail`, `admin.farm.printers.show`)

Pohled `admin/farm/printer.blade.php` **vznikl přejmenováním** `dashboard.blade.php` (git to vidí jako přejmenování):
karta tiskárny je v něm beze změny, takže úpravy karty z jiných větví se při slučování přenesou sem. Obsahuje:

- živou polovinu (překresluje se jako přehled, ze své adresy): stav, telemetrie, hláška tiskárny, poslední příkaz,
  kamera, podložka, reset chyby, světlo, sušení, běžící úloha s pauzou / pokračováním / zrušením a hláškami, celá
  fronta s odhady, cívky;
- **Filamenty v tiskárně** (`partials/printer_slots.blade.php`): pozice, výběr filamentu, zbývající gramy, „nabízet“.
  Ukládá stejná akce jako „Založení cívek“ (`saveSlots`, pravidlo `manual_only` platí), jen pro tuto tiskárnu, a vrací
  se na detail. U každého založeného filamentu odkaz **Detail filamentu**. Je mimo překreslovaný obal, takže obnovení
  rozepsanou volbu nesmaže. Nová cívka mimo katalog a názvy pozic zůstaly v nastavení tiskárny;
- **Soubory na tiskárně** (`partials/files.blade.php`) se sem přestěhovaly z nastavení tiskárny; akce souborů se vrací
  na `…/detail#files`. V nastavení tiskárny je místo nich odkaz na detail.

Nastavení tiskárny (`/admin/farm/printers/{id}`) zůstalo: profil, kalibrace, časosběr, sloty včetně nové cívky, farma.

## 5. Detail filamentu (`/admin/farm/colors/{id}`, `admin.farm.colors.show`)

Filament = řádek `FarmColor` (barva katalogu = cívka založená v tiskárně). Pohled `admin/farm/filament.blade.php`:

- **Kde je založený**: tiskárna a pozice (proklik na detail tiskárny), kód, gramy.
- **Tento filament**: název, anglicky, druh (přeřazení), výrobce, hex, skladem, nabízet, **vlastní teploty** (tryska,
  1. vrstva, podložka jako čísla; prázdné = podle druhu), další vlastní nastavení tisku (JSON), výsledky testů, odkaz
  na e‑shop, fotka. Ukládá dnešní `saveColor` (`back=filament` vrací na detail). Teploty se zapisují do
  `print_overrides` (klíče `nozzle_temp`, `nozzle_temp_first`, `bed_temp`), tedy tam, kde byly dosud; pole `temps`
  má přednost před stejnými klíči v JSON.
- **Druh**: teploty, cena za gram, hustota, profil a jeho přepisy, `manual_only`, poznámka. Jen ke čtení, s odkazem
  „Upravit druh“ do Materiálů (`#kind-<id>`). Hodnoty druhu se tu nemění ani neduplikují.
- **Na tiskárnách (ladění)**: řádky `FarmPrinterMaterial` druhu a této cívky se stavem a odkazem do Ladění; u tiskárny,
  kde cívka je a vlastní řádek nemá, tlačítko „založit vlastní řádek cívky“ (dnešní `tuning.spool`).

Všechny tři stránky jsou ve skupině `auth` + `role:admin`.

## 6. Testy, snímky, co zbývá

- `tests/Feature/FarmAdminOverviewTest.php`: skupiny podle farem a pořadí, stavy, tlačítko podložky oběma směry,
  zakázky v řádku, detail tiskárny, uložení filamentů, detail filamentu (uložení, rozsahy, oprávnění), farmy a migrace.
- Starší testy, které hledaly kartu tiskárny na `/admin/farm` nebo soubory v nastavení tiskárny, teď čtou detail.
- Snímky: `docs/img/admin-farm-{overview,printer,filament,farms}-{390,1280}.png` (místní server, soukromá kopie
  sqlite s ukázkovými daty).
- Otevřené: pojmenování stavů a tlačítka podložky podle Romana; má-li se u offline nebo vypnuté tiskárny tlačítko
  podložky skrýt; jestli do „další čekající“ počítat i zaplacené zakázky čekající na schválení.
