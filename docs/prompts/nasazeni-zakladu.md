# Zadání: nasazení základu nástrojů (větev `feature/tools-base`) a dokončení po nasazení

Datum: 7. 10. 2026. Navazuje na `docs/N.md` (session 0, základ nástrojů) a předchází session 1 ze zadání
`docs/prompts/nastroje.md`. Pracuj ve worktree `C:\matplace-base-wt` (větev `feature/tools-base`, be3f2f1, pushnutá
na origin). Model: Claude Opus 5.5. Účel session: **dostat větev na matplace.com bez odstávky nad pár minut, ověřit
ji naživo, doplnit, co N.md nechává Romanovi, a předat session 1.**

Pravidla: nikdy `git add -A`, nikdy `git stash`; testy `php -d memory_limit=2G vendor/bin/phpunit`; nové texty jen do
`lang/{cs,en,es}/<skupina>.php`. **Nikdy `taskkill /F /IM python.exe`** ani jiné hromadné zabíjení procesů – na tomhle
PC běží agent tiskové farmy (`C:\farm-agent`, úloha `matplace-farm-agent`); session 0 ho tím 6. 10. shodila. Zabíjej
jen PID, které jsi sám spustil. Na serveru nikdy `queue:retry all` (jednou to stálo 379 Kč za staré AI joby).

## Co je hotové a co ne (z `docs/N.md`)

Hotové a otestované: tři commity nad `main` 963b0d0, fast-forward bez konfliktu; 13 dotčených testovacích tříd znovu
puštěno 7. 10. ráno – 76 testů OK. Není hotové: data „ověřeno tiskem“, hex a anglické názvy cívek na produkci, ruční
průchod kalkulačky a objednávky farmy v opravdovém prohlížeči, otevření dvoubarevného projektu 3MF ve skutečném
sliceru. Ze screenshotu `docs/img/base-after-sign.png`: řádek kroků v levém panelu se ořezává („4 St…“) při 340 px.

## 0. Než začneš – jedna zpráva Romanovi

1. Souhlas s nasazením teď (odstávka `php artisan down` ~3 minuty, build + restart).
2. **Seznam nástrojů, které se už skutečně tiskly, s datem** – pro `'verified' => 'RRRR-MM-DD'` v `config/tools.php`
   (cedulka, QR, krabička, váza, vykrajovátko…? ví jen Roman).
3. Jestli má na PC OrcaSlicer nebo PrusaSlicer (pro otevření dvoubarevného projektu), nebo to má zkusit server (bod 4).
4. Stav katalogu cívek: kolik je jich v `/admin/farm/materials` s fotkou (hex se počítá z fotky) – Roman ho plní
   celý tento týden, `colors-fill` se pouští opakovaně, pokaždé jen na to, co chybí.

## 1. Lokálně: `main` (5 minut)

```
cd C:\matplace-base-wt
git fetch origin
git log --oneline -1 origin/main                      # musí být 963b0d0; jinak STOP a napiš Romanovi, kdo posunul main
git merge-base --is-ancestor origin/main HEAD && echo "fast-forward OK"
git status --short                                    # jen docs/prompts/nastroje.md (poznámka o vánoční sadě) – commitni ji
git push origin feature/tools-base:main               # fast-forward GitHub main
```

Holý repo na Hetzneru (`remote server`) dostane totéž, pokud z něj server bere: `GIT_SSH_COMMAND="ssh -i
~/.ssh/hetzner_matplace" git push server feature/tools-base:main`. Zjisti si na serveru `git remote -v`, co je `origin`.

## 2. Server (15 minut; postup z `docs/DEPLOY-BETA.md` a poučení z 28. 9.)

```
ssh -i ~/.ssh/hetzner_matplace root@178.104.162.164
cd /var/www/matplace-app
git status --short                       # prázdné (snad až na engines/orca/profiles/result.json)
git fetch --all
git log --oneline origin/main..HEAD      # MUSÍ být prázdné – commity, které má jen server → STOP, napiš Romanovi
git log --oneline HEAD..origin/main      # přesně 3 commity session 0 (+ případně poznámka k briefu)
cp .env /root/matplace-app.env.bak-$(date +%Y%m%d-%H%M)
mysqldump matplace_app > /root/matplace_app-$(date +%Y%m%d-%H%M).sql
php artisan down --retry=30
git merge --ff-only origin/main || { echo STOP; php artisan up; exit 1; }
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate:status | grep -i pending          # N.md: žádná migrace; kdyby něco čekalo, STOP a zjisti proč
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache public/build
systemctl restart php8.2-fpm matplace-worker 'matplace-worker@*'
php artisan up
```

Server má lokální větev `feature/farm`, která dosud brala `origin/main` sloučením (identita `deploy`); když
`--ff-only` odmítne, **nic nepřepisuj**: podívej se `git log --oneline origin/main..HEAD`, napiš Romanovi a čekej.
Hned po `up`: `curl -s -o /dev/null -w '%{http_code}\n'` na `/`, `/tools`, `/tools/box`, `/tools/qr`, `/api/config`
(musí vracet `colors`), `/api/artwork/library?q=srdce`; `tail -f storage/logs/laravel.log` a
`/var/log/nginx/error.log` 10 minut; `systemctl status matplace-worker 'matplace-worker@*'`; `/admin/farm` –
tiskárny online (agenti nic nepoznali, nasazení se jich netýká, ale ověř).

## 3. Po nasazení

1. **Cívky**: `php artisan farm:colors-fill --dry-run` → výpis Romanovi (kolik hexů z fotky, kolik překladů, co
   zůstane prázdné) → se souhlasem bez `--dry-run`. Překladač: `ENGINE_TRANSLATOR=claude` už v `.env` je. Výsledek
   zkontroluj namátkou: 10 cívek, hex vedle fotky (`/admin/farm/materials`), nesmysl (duhová jako šedá) zapsat do
   `test_notes` je v pořádku, hex z lesklé černé jako `#3a3a3a` taky. Až Roman dodá CSV, import s náhledem změn.
2. **„Ověřeno tiskem“**: data od Romana do `config/tools.php` – commit na `main` přímo (jediný soubor, bez testu
   nic nezmění; `ToolsCatalogTest` projde), malé nasazení: `git pull`, `php artisan optimize`, restart `php8.2-fpm`.
3. **Řádek kroků v panelu** (ořez „4 St…“): oprava v `tools/page.blade.php` / CSS – kroky zalomit, nebo zkrátit
   popisky na mobilu/úzkém panelu; screenshot po opravě do `docs/N.md` §2. Stejné nasazení jako v bodě 2.
4. **Dvoubarevný projekt 3MF**: z produkce stáhni projekt Orca i Prusa pro `/tools/qr` (dvě barvy) a `/tools/box`
   (víčko jinou barvou). Když má Roman slicer na PC, otevře je on a řekne, co vidí (dva objekty / barvy ve slotech /
   výměna filamentu ve výšce). Když ne: na serveru `xvfb-run -a /opt/orca/squashfs-root/AppRun --slice 0 --export-3mf
   /tmp/test.3mf <projekt>` do `/tmp` (jen čtení aplikace, nic ve `storage`), a zkontroluj, že Orca projekt načte
   bez chyby a v G-code je `M600` nebo výměna slotu ve správné vrstvě (`GcodeStats` to umí přečíst).
5. **Ruční průchod** – Roman v prohlížeči: `/tools/box` (víčko → Rozložit, barva víčka z okna, Stáhnout → ZIP),
   `/tools/logo` (Vybrat obrázek → Knihovna → srdce), `/tools/sign` tažení úchytu (u cedulky úchyt není, zkus
   krabičku), `/` s nahraným STL (kalkulačka sdílí viewer), objednávku na `/farm` až do výběru cívky. Ty mezitím
   totéž v headless Chrome proti produkci (jen GET a náhledy, žádné objednávky) a čti log. Každou chybu oprav na
   `main` (malé commity, test ke každé) a nasaď bodem 2.
6. **Úklid**: větve `feature/bust-fixes`, `feature/perf`, `feature/traffic` jsou sloučené – po souhlasu Romana
   `git branch -d` + `git push origin --delete`; worktrees `C:\matplace-bust-wt`, `-perf-wt`, `-traffic-wt`,
   `-tools-wt` odstranit `git worktree remove` (jen pokud `git status` v nich nic nehlásí).

## 4. Předání session 1

Až je `main` nasazený a bod 3.1–3.3 hotový: `git worktree add C:\matplace-shapes-wt -b feature/tools-shapes
origin/main`; session 1 dostane zadání `docs/prompts/nastroje.md` (sekce „Session 1“ začíná vánoční sadou s cílem
15. 11. 2026) a tenhle dokument jako kontext, co je nasazené. Do `docs/N.md` doplň sekci „8. Nasazeno“ s datem,
commitem na serveru, výsledkem `colors-fill` (čísla), co Roman ověřil ručně a co ne. Hotovo, když matplace.com
běží na `main` = `feature/tools-base` (+ opravy), log je 24 h bez nových chyb z nástrojů, `colors-fill` proběhl
na tom, co v katalogu je, a session 1 má worktree.
