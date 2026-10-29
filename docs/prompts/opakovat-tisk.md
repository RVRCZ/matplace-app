# Opakovat tisk: rovnou nová zakázka na stránce zakázky, i u běžící zakázky (session 2)

Zadal Roman 29. 10. 2026 večer u zakázky F26‑000108 (12 ks, po dílech, čtyři barvy, stav „tiskne se“): „U spuštěných nebo dokončených zakázek přidat tlačítko Opakovat, to přejde zpět na stránku objednávky s možností výběru jiných barev.“ Řídící session: `conceprt19092026-91`. Pravidla v `docs/prompts/koordinator.md`.

## 1. Co je dnes

- Tlačítko `#farm-repeat` („Opakovat tisk“, `farm.order.repeat`) se ukazuje jen u stavů done, handed_over, cancelled, failed (`resources/js/calc/farm.ts` řádek ~961).
- `GET /farm/orders/{order}/repeat` (`OrderController::repeat`) přesměruje na `/farm/start` s parametry (soubor, kvalita, pevnost, kusy, podpěry, měřítko, barva, druhá barva, změny, po dílech a barvy dílů). Zakázka vznikne až po kliknutí na startovní stránce.
- Testy: `tests/Feature/FarmOrderFlowTest.php` řádky ~420, 477, 591, 998 (přesměrování na start s parametry).

## 2. Cíl

1. Tlačítko „Opakovat tisk“ u každé zakázky od zaplacení dál: paid, queued, printing, done, handed_over, failed, cancelled. Roman jmenoval spuštěné a dokončené; failed a cancelled už tlačítko mají, nechej.
2. Klik založí novou zakázku rovnou (bez startovní stránky) a přejde na její stránku ve stavu výběru barev (sliced, `choosing`). Nová zakázka má stejný soubor, počet kusů, kvalitu, pevnost, podpěry, měřítko a jednotky, pokročilé nastavení (`print_settings`), tisk po dílech s barvami dílů, u namalovaného souboru barvy z `colors` (AL.md §14), druhou barvu a změny po výšce, poznámku. Doručení a adresa se nepřenáší, zákazník je vybere znovu (ceny dopravy se mohly změnit); pokud je přenos levný a bezpečný, předvyplň a napiš to.
3. Původní barvy jsou předvybrané, pokud jsou právě k mání; jinak stránka řekne, že barva už není, a nabídne výběr (dnešní chování startovní stránky, `repeat_hint`). Zákazník může vybrat jiné barvy, jiný počet kusů, jiné nastavení a pak zaplatit.
4. Řez: když sedí parametry řezu, použij hotový G‑kód z mezipaměti (stejná cesta jako dnes `slice_cached`), jinak se řeže znovu. Stránka zakázky nesmí čekat déle než u nové zakázky.
5. Bez duplicit: dvojklik nebo obnovení stránky nesmí založit dvě zakázky (podepsaný odkaz s jednorázovým použitím, nebo POST s throttle a kontrola „stejná zakázka založená před méně než minutou“, zvol a zdůvodni). Odkaz `GET …/repeat` může zůstat pro staré adresy a přesměrovat na novou cestu.
6. Host bez účtu (AO.md, session G): tlačítko i založení funguje přes relaci hosta jako dnešní `authorizeOrder`. Denní limit řezů platí jako u nové zakázky. Admin stejně jako zákazník.
7. Stránka „Moje tisky“ (`/account/orders`): u každé zakázky v seznamu stejné tlačítko „Opakovat“ se stejným cílem. Malý rozsah, ale uživatel tam opakuje nejčastěji.
8. Měření: `Track::event('order_started', …, ['kind' => 'repeat'])` nebo nový druh, aby bylo v `/admin/stats` vidět, kolik zakázek vzniklo opakováním.

## 3. Texty

- `farm.order.repeat` zůstává „Opakovat tisk“. `repeat_hint` uprav: nová zakázka se stejným modelem a nastavením, barvy a počet kusů lze změnit, původní barvy jsou předvybrané, pokud jsou k mání.
- Nové texty jen přes `lang/src/*.json` a `python scripts/lang_add.py`, cs/en/es, česky krátce, bez pomlček.

## 4. Kde a jak

- Worktree `C:\matplace-gcode-wt`, větev `feature/order-repeat` z main. Soubory: `OrderController::repeat`, `OrderService`, `farm.ts`, `order.blade.php`, `farm/orders.blade.php` (Moje tisky), testy.
- Zápis do `docs/AL.md` jako nová kapitola (§15): co se přenáší, co ne, jak je ošetřený dvojklik, co je ověřené.

## 5. Ověření

- PHPUnit: opakování z printing i done vytvoří novou zakázku ve stavu sliced s předvybranými barvami (jedna barva, po dílech, namalovaný soubor), host, dvojklik = jedna zakázka, limit řezů, stará adresa `/repeat` dál funguje. Uprav dnešní testy přesměrování.
- `npx tsc --noEmit -p tsconfig.json`, `npm run build`, `vendor/bin/pint --test` na změněné soubory.
- Po pushi zpráva řídící session: větev, commit, co je ověřené. Nic nenasazuj.
