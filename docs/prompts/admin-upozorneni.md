# Zadání pro session 2: upozornění obsluze farmy (zaplaceno, tiskne se, dotištěno, pokazilo se)

Od Romana (18. 10. 2026): „Potřebuji jako admin dostávat info, že někdo něco tiskne, že se něco dotisklo, nebo se něco
pokazilo, než pustím komerční přijímání zakázek.“ Dnes chodí obsluze (`FarmSettings` `admin_email`, `OrderFlow::alertAdmin`,
`App\Mail\FarmAdminAlert`) jen výjimky: zakázka ke schválení, selhání výpočtu, ruční tiskárna, tiskárna offline, start
selhal, chyba čeká, cizí měna, video ke schválení. Běžný průběh (zaplaceno → tiskne se → dotištěno) obsluze nic
neřekne.

## 0. Kde pracuješ a pravidla

- Větev **`feature/admin-notify`** z aktuálního `main`, dokument **`docs/AL.md` §8**.
- Soubory: `app/Domain/Farm/OrderFlow.php` (hook po zaplacení, `move()`), `app/Domain/Farm/AgentService.php` (start
  úlohy, `done`, `failed`/`cancelled`, offline), `app/Domain/Farm/FarmSettings.php` + `/admin/farm/settings`
  (`FarmSettingsController`? najdi podle route `admin.farm.settings.save`, view `resources/views/admin/farm/settings.blade.php`),
  `app/Mail/FarmAdminAlert.php` + jeho šablona (rozšířit, ne nový mail), `lang/*/farm.php` (`admin.mail.*`), testy.
  Nesahat: agent, slicer, kalkulace, nástroje, platby (`Wallet`, `OrderFlow` jen přidat volání po `STATUS_PAID`).
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, testy (`php -d memory_limit=2G vendor/bin/phpunit --filter …`);
  nemergovat, nenasazovat; hotovo = push + zpráva řídící session (conceprt19092026-8c). Žádný skutečný e‑mail (Mail::fake).

## 1. Co posílat (e‑mail na `admin_email`, přes `alertAdmin`, jeden mail = jedna událost)

| událost | kde vzniká | předmět (cs) | obsah |
|---|---|---|---|
| zaplaceno (někdo tiskne) | `OrderFlow` po `move(STATUS_PAID)` | „Nová zakázka F26‑000123: zaplaceno“ | číslo, zákazník (jméno/e‑mail, host?), model (název souboru / nástroj), materiál + barva (kód cívky), tiskárna a fronta („začne hned“ / „za X min, před ní N“), kusy, cena, doprava, odkaz do adminu |
| tiskne se | `AgentService` při přechodu úlohy do `printing` (první hlášení) | „F26‑000123 se tiskne na U #1“ | tiskárna, cívka, odhad dotištění, odkaz; u ručního režimu nic (obsluha ví) |
| dotištěno | `AgentService` `done` (u zakázky po dílech až poslední díl) | „F26‑000123 dotištěno (U #1, 1 h 47 min)“ | doba tisku, filament, další krok (balení / Zásilkovna / vyzvednutí), odkaz; poslední snímek z kamery jako příloha (JPEG ≤ 300 kB), když je |
| pokazilo se | `AgentService` `failed`/`cancelled`, start selhal, `OrderFlow::fail` | dnes existuje (`failed`, `start_failed`, `offline`…): sjednotit předměty na „F26‑000123: …“ a doplnit totéž tělo (co, kde, co dál) | |
| zrušeno zákazníkem | `OrderFlow` cancel | „F26‑000123 zrušil zákazník“ | kdy, co se vrátilo (kredit), zda už se tisklo |

- Všechno v jazyce obsluhy (cs), bez překladu pro zákazníka; částky v Kč.
- **Přepínače** v `/admin/farm/settings` vedle „E‑mail obsluhy“: zaškrtávátka „zaplaceno“, „tiskne se“, „dotištěno“,
  „problémy“ (výchozí všechny zapnuté; klíče `FarmSettings` `notify.paid`, `notify.printing`, `notify.done`,
  `notify.problem`), `alertAdmin($subject, $order, $lines, $kind)` posílá jen zapnuté druhy; `admin_email` prázdný = nic.
- Žádné opakování: jedna událost = jeden mail (u „tiskne se“ hlídat, ať hlášení agenta každých 5 s nepošle víc mailů:
  značka na úloze, např. `notified_at` sloupec nebo `events` zakázky).
- Mail je ve frontě (`queue`), stejně jako dnes; selhání jen do logu.

## 2. Testy a hotovo

`tests/Feature/FarmAdminNotifyTest.php` s `Mail::fake()`: zaplacení pošle jeden mail s číslem a odkazem; start tisku
jeden; dotištění jeden (po dílech až poslední); selhání jeden; vypnutý přepínač nic; prázdný `admin_email` nic;
hlášení agenta 3× za sebou = pořád jeden mail. Stávající testy `FarmOrderFlowTest`, `FarmAgent*` zelené.
**Hotovo =** push `feature/admin-notify`, AL.md §8 (tabulka událostí, kde se volá, přepínače), testy, zpráva řídící.
