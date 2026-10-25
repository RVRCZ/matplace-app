# Start nové řídící session (koordinátor) – 25. 10. 2026 večer

Jsi **nová řídící session** webu matplace.com. Nejdřív si přečti, v tomhle pořadí:

1. `docs/prompts/koordinator.md` ve worktree **`C:\matplace-farmcolors-wt`** (stálá pravidla: co děláš, pracoviště,
   nasazení, zákazy). Platí celé, níže jsou jen změny od 16. 10.
2. Paměť: `C:\Users\roman\.claude\projects\c--Users-roman-Documents-MatPlace-conceprt19092026\memory\MEMORY.md`
   a hlavně `project_coordinator_handoff.md` (stav den po dni do 25. 10., gotchas nasazení) a `reference_home_printers.md`.
3. `docs/TODO.md` (řádky #1 až #42; otevřené jsou hlavně #41 a #42, zbytek čeká na Romana).

Pracuješ v `C:\matplace-farmcolors-wt` na větvi `feature/farm-colors` (= main, HEAD 297edc3, na GitHubu i na
serveru). Piš česky, krátce, bez pomlček. Roman je majitel a rozhoduje; klíče a .env dává jen on.

## Co se změnilo od stálého promptu

- **Session teď:** `conceprt19092026-6f` = **session 2** (farma a zakázky, worktree `C:\matplace-gcode-wt`, větve
  `feature/order-flow-big`, `feature/big-model-one-helper`, `docs/al-11-production-numbers`, vše sloučeno),
  `conceprt19092026-01` = **session 3** (web, kalkulace, statistika, worktree `C:\matplace-edit-wt`, větev
  `feature/calc-estimate-range`, sloučeno). Session 1 a 4 jsou zavřené. Adresy ověř přes `ListAgents`; zprávy jim
  posílej jen když je to potřeba. Dirty worktree, kterých se nikdo nedotýká: `C:\matplace-app`, `-web-wt`, `-base-wt`, `-yt-wt`.
- **Nasazení (doplňky k receptu):** na serveru merguj s identitou
  `git -c user.name="matplace deploy" -c user.email="deploy@matplace.com" merge -q --no-edit origin/main` a místo
  `set -e` použij `trap "php artisan up" EXIT` (23. 10. zůstal web 20 s v údržbě po pádu merge). Produkce má
  `opcache.validate_timestamps=0`: každá změna PHP/Blade/lang/config = `php artisan optimize` + `systemctl reload php8.2-fpm`
  (bez výpadku) + `php artisan queue:restart` (workery běží starý kód, dokud se nerestartují; tvrdý `systemctl restart`
  jen když fronta nic neřeže). Jen JS = `npm run build` bez `down`. Migrace: nejdřív `mysqldump` do /root.
  Push na server (`git push server HEAD:main`) občas zamítne klasifikátor jako produkční nasazení; po Romanově „nasaď“ projde.
  Během tisku nasazuj jen na Romanovo „nasaď“ (agent se připojí znovu, výpadek 10 až 30 s).
- **Testy běží na SQLite**, která nehlídá délky sloupců: nový stav/enum vždy porovnej s MariaDB (`information_schema`),
  25. 10. padlo zakládání zakázek na `farm_order_events.to` varchar(14). Testy farm/order trvají 7 až 10 minut, pouštěj
  na pozadí. PHPUnit: `php -d memory_limit=2G vendor/bin/phpunit --filter …`.
- **Python skripty s českým textem** piš nástrojem Write do scratchpadu a spouštěj `python -I soubor.py`
  (heredoc přes stdin rozbije kódování). Výstup s diakritikou: `PYTHONIOENCODING=utf-8 python -X utf8`.
- **Měření na produkci** (headless Chrome, cookie `mp_test=1`): skript `scratchpad/pup/check_dragon.mjs` z minulé
  session (puppeteer-core, Chrome v Program Files); drak = `C:\Users\roman\Downloads\Mystical_Chine_Dragon_V_EYE(1).stl`.
- **Cache zjednodušení sítí** `storage/app/mesh-cache` (klíč = bajty souboru + limity), tiskárny doma: U1 192.168.8.21,
  U2 192.168.8.23 (Moonraker 7125), Kobra X 192.168.8.44 (soubory nevydá); stažené G‑kódy a modely z nich jsou
  v `C:\Users\roman\Documents\MODELS\{U1,U2,historie}`.

## Co je otevřené (stav 25. 10. večer)

- **#41 flow velkých modelů** je nasazené celé. Zbývá: Romanův průchod drakem stránkou zakázky (zakázka 222 čeká na
  volby: `/farm/orders/ENY4F408gYLMXsFs56gr13qbBkuvXuFL`, tlačítko „Spočítat přesnou cenu“ = jeden řez); potom poslat
  session 2 `farm_orders.timings` + `slice_run` pro AL.md §11.6. Session 2 má doplnit transakci kolem
  `OrderService::create` a zvážit, zda z kalkulace nejít rovnou na stránku zakázky (krok „Tisknout na naší tiskárně“
  stahuje 90 MB STL do náhledu). Session 3 má posunout odhad doby u tvarů s dlouhými drahami nahoru (drak: ukázáno
  1 h 30 min až 6 h 30 min, skutečnost 6 h 36 min až 7 h 08 min). Až odevzdají: sloučit, testy, nasadit.
- **#42 G‑kódy z tiskáren:** farma B (B1 až B8, K1, Kobra 3 Max) je mimo domácí síť; buď stejný postup na PC farmy B,
  nebo výpis a stahování souborů do agenta (session 4, zavřená). Prohledání disků U1/U2 přes SSH jen s Romanovým heslem a souhlasem.
- **Čeká na Romana:** agent 0.2.8 na PC farmy B (zip v `D:\matplace-dilna`), log agenta z PC U pro úlohu 154 (session 4),
  rozhodnutí o podpěrách u velkých modelů (#37, stromové zůstávají), fotky pro blog a vstupní stránky (#24, #26),
  YouTube schválení a titulky (#12, #28), Search Console (#27), Meshy klíč (#1), zadání k uspořádání stránek (#15).
- **Hotovo tento týden a nasazeno:** PHP 8.2.35, restart serveru (jádro 6.8.0‑147), vlny v statistice (#40), cache
  a zrychlení zjednodušení sítí, upload souběžně s čtením v prohlížeči a čtení velkých STL ve workeru, volby na stránce
  zakázky během řezu nemizí, #41 obě části, oprava šířky sloupce historie zakázek.

Začni tím, že ověříš `ListAgents`, stav serveru (`git log -1` v `/var/www/matplace-app`, `systemctl is-active`
php8.2-fpm matplace-worker nginx) a zda session 2 a 3 mezitím něco pushly (`git fetch origin`, `git log origin/main..origin/<větev>`).
Pak se zeptej Romana, co ho tlačí nejvíc.
