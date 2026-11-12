# Zadání: administrace farmy – jednodušší přehled „Tiskárny a fronta“, detail tiskárny, detail filamentu

Stav k 12. 11. 2026. Zadal Roman („abych měl jednodušší přehled“), píše koordinátor. Větev z `origin/main`, vlastní
worktree, nic nenasazuj, nesahej na produkci ani na tiskárny.

## Co Roman chce (jeho slovy, uspořádáno)

**1. Stránka „Tiskárny a fronta“ (`admin.farm.dashboard`) jako stručný seznam**
- Nechce vidět detail všech tiskáren najednou. Stačí **seznam tiskáren podle farmy** (skupiny: farma U, farma B, …).
- U každé tiskárny na jednom řádku:
  - **stav**: online, offline, tisk, standby, problém;
  - **tlačítko pro uvolnění / zablokování podložky**;
  - **právě tištěná zakázka**, nebo **poslední dotištěná**, a k tomu **další čekající**;
  - **proklik na detail tiskárny**.

**2. Detail tiskárny**
- Všechno ostatní, co je dnes na přehledu u tiskárny (kamera, telemetrie, hlášky, příkazy, soubory, …), se přesune sem
  a zůstane funkční jako dnes.
- Navíc **volba filamentu, jak je dnes ve stránce „Tiskárny“** (pozice/cívky tiskárny, `slots`).
- U každého filamentu **proklik na detail filamentu**.

**3. Detail filamentu**
- Stránka jednoho filamentu, kde se dají **nastavit parametry pro filament** (to, co je dnes roztroušené
  v „Materiály“ a „Ladění“: teploty, profil a jeho přepisy, cena za gram, příznaky jako `manual_only`, poznámky…).

## Výchozí stav v kódu (ověř si, než začneš)

- Pohledy `resources/views/admin/farm/`: `dashboard.blade.php` (171 ř.), `printers.blade.php` (23), `printer_edit.blade.php`
  (173), `slots.blade.php` (83), `materials.blade.php` (180), `tuning*.blade.php`, navigace `nav.blade.php`.
- Modely: `FarmAgent` (farma = agent: „Agent1 S1“ = farma U, „Farma B“; tiskárny bez agenta = ruční), `FarmPrinter`
  (`state`, `last_seen_at`, `bed_clear`, `message`, `telemetry`, `enabled`, `mode`), `FarmPrinterSlot`, `FarmColor`,
  `FarmMaterial`, `FarmPrinterMaterial`, `FarmPrintJob`, `FarmOrder`.
- Tiskárny nemají sloupec „farma/místo“. Seskupení udělej podle agenta (název agenta jako nadpis skupiny, ruční
  tiskárny jako vlastní skupina). Jestli by bylo čistší přidat tiskárně pole „místo“, navrhni to v závěrečné zprávě,
  ale nezaváděj bez Romanova rozhodnutí.

## Požadavky na provedení

- **Stav jedním slovem a barvou**, odvozený z toho, co už farma ví: offline (dlouho se neozvala), tisk, pauza,
  standby (volná, podložka čistá), čeká na uklizení podložky, problém (chyba, hláška tiskárny, nepotvrzené zrušení).
  Pravidla sepiš do dokumentace; nevymýšlej nové stavy v databázi, jen zobrazení.
- **Podložka**: jedno tlačítko, které přepíná „uvolnit“ / „zablokovat“ (dnešní akce označení podložky jako uklizené
  + nová opačná, pokud chybí). U běžícího tisku tlačítko nenabízej.
- **Zakázka**: číslo zakázky s proklikem na `admin.farm.order`, průběh v % a odhad konce u běžící; u volné tiskárny
  poslední dotištěná (číslo, kdy skončila); „další čekající“ = první zakázka ve frontě určená pro tuto tiskárnu
  (a počet dalších).
- Seznam musí být použitelný na telefonu (Roman chodí k tiskárnám s mobilem): žádné vodorovné posouvání, tlačítko
  podložky dost velké na prst.
- Přehled se má obnovovat sám tak jako dnešní stránka (zjisti, jak to dělá, a zachovej).
- Detail filamentu: rozhodni podle modelu dat, jestli je „filament“ řádek `FarmMaterial`, `FarmColor` (cívka/barva)
  nebo obojí; Roman myslí konkrétní filament, který je v tiskárně. Parametry, které patří druhu materiálu, uprav
  u druhu a z detailu barvy na něj odkaž; nic neduplikuj.
- Texty česky, anglicky, španělsky přes `lang/src/*.json` + `python scripts/lang_add.py`.
- Nic z dnešních funkcí nesmí zmizet: co se z přehledu odstěhuje, musí být dohledatelné v detailu tiskárny.

## Souběh s jinou prací

Session 4 právě pracuje na větvi `fix/agent-cancel-confirm` a přidává do administrace farmy zobrazení „tiskárna
nepotvrdila zrušení“. Počítej s tím: stav „problém“ v novém seznamu má takové zakázky ukázat, až se větve spojí;
drž změny v pohledech přehledné, ať se dají sloučit.

## Testy a výstup

- PHP testy: přehled (skupiny, stavy, tlačítko podložky v obou směrech, zakázky u tiskárny), detail tiskárny,
  detail filamentu (uložení parametrů, oprávnění jen pro administrátora).
- Snímky obrazovky nových stránek na šířce 390 px a 1280 px (místní server se seedery; recept v koordinátorově
  dokumentu a v dokumentech jiných session), uložené do `docs/img/`.
- Větev `feature/admin-farm-overview`, push, neslučovat. Krátká kapitola v dokumentaci farmy (`docs/AL.md` nebo
  vlastní soubor podle zvyklostí v `docs/`).
- Závěrečná zpráva koordinátorovi: co je hotové, snímky, co se liší od zadání a proč, otevřené otázky pro Romana.
