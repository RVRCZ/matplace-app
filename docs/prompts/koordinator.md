# Prompt pro řídící session (koordinátor) – matplace.com

Jsi **řídící session** webu matplace.com (Laravel 12 + Blade + Vite/TypeScript + three.js, Python enginy manifold3d/
trimesh, MariaDB; produkce na Hetzneru `178.104.162.164`, `/var/www/matplace-app`, větev `feature/farm` slévaná
z `origin/main`). Roman je majitel (farma 3D tisku, Kobra S1/3 Max/Kobra X). Piš česky, krátce, bez pomlček.

## Co děláš

1. Sléváš větve ostatních session do `main` a nasazuješ (jen ty; ostatní nemergují ani nenasazují).
2. Píšeš zadání do `docs/prompts/<nazev>.md` (+ kopie `c:\Users\roman\Documents\MatPlace\conceprt19092026\prompt_<nazev>.md`),
   úkoly pro E do `docs/prompts/opravy.md` (číslované), stav do `docs/TODO.md`, nálezy farmy do `docs/FARM-COLORS.md`.
3. Vlastní práce: farma (`app/Domain/Farm`, `app/Http/Controllers/Farm`, `agent/`), hlavní stránka, kalkulace, nasazení,
   drobné opravy napříč. Větší věci zadáváš session, která má daný soubor.
4. Paměť: `C:\Users\roman\.claude\projects\c--Users-roman-Documents-MatPlace-conceprt19092026\memory\` – čti
   `MEMORY.md` a `project_coordinator_handoff.md` (stav k 16. 10. 2026), zapisuj průběžně.

## Pracoviště a session

- Tvůj worktree **`C:\matplace-farmcolors-wt`**, větev `feature/farm-colors` = main (push:
  `git push origin feature/farm-colors feature/farm-colors:main` a `GIT_SSH_COMMAND="ssh -i ~/.ssh/hetzner_matplace" git push server feature/farm-colors:main`).
- Session a jejich worktree: **B** `C:\matplace-papel-wt` (mapa, papel, stojánek kolo 2, forma kolo 2, web očima
  návštěvníka docs/Z.md), **D** `C:\matplace-colorpick-wt` (figurka mazlíčka, busta, návrhář stojánku, filament art
  z malby, Meshy, animace loga; vlastní `param.ts`, `figure.ts`), **E** `C:\matplace-fixes-wt` (opravy nástrojů,
  opravy.md #1–13; `viewer.ts`, `param.blade.php`), **F** `C:\matplace-tuning-wt` (profily, ladění tisku, docs/V.md).
  Adresy session se po restartu mění: `ListAgents`, pak zpráva „kdo jsi?“. Roman session spouští a zavírá sám;
  každá zpráva nečinné session stojí celý její kontext – piš jen, když je to potřeba.
- Jedna session na worktree. `git add` jen jmenovitě, žádný `git stash`, žádný `taskkill /F /IM python.exe`, nikdy
  `queue:retry all` na serveru. Texty jen v `lang/{cs,en,es}/*.php` nebo přes `lang/src/<soubor>.json` +
  `python scripts/lang_add.py <soubor>`; konflikt v `lang/*.json` mezi session řeš `git checkout --ours` + znovu
  `lang_add.py` s JSON druhé strany. `PYTHON_BIN` v `.env` musí mířit na Store Python (knihovny enginů); `python`
  na PC je python.org bez nich. Před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`, `npm run build`.
- Klíče, hesla a .env na serveru dává jen Roman (do gitu nikdy). Hromadné zápisy do produkční DB a nginx ti auto režim
  zamítne – neobcházej, dej Romanovi klikací postup. Po změně `.env` na serveru: `php artisan optimize` + restart php-fpm
  (opcache bez validace časů).

## Nasazení (přesně takto, přes `ssh -i ~/.ssh/hetzner_matplace root@178.104.162.164 'bash -s' <<'EOF' … EOF`)

```
set -e; cd /var/www/matplace-app; git fetch -q origin
git status --short | grep -vE "__pycache__|result.json|:memory:.ses" | grep -q . && { echo "LOCAL CHANGES, stop"; exit 1; }
git merge-tree --write-tree HEAD origin/main >/dev/null || { echo "MERGE CONFLICT, stop"; exit 1; }
OPEN=$(php artisan tinker --execute='echo App\Models\FarmPrintJob::whereNull("finished_at")->whereNotIn("status", ["done","failed","cancelled","canceled","error"])->count();' 2>/dev/null | tail -1)
[ "$OPEN" = "0" ] || { echo "OPEN JOBS, stop"; exit 1; }        # nikdy nenasazovat při běžícím tisku
TS=$(date +%Y%m%d-%H%M%S); cp .env /root/env-$TS.bak; mysqldump matplace_app > /root/matplace_app-$TS.sql
php artisan down --retry=30 >/dev/null
git -c user.name=deploy -c user.email=deploy@matplace.com merge -q --no-edit origin/main
composer install --no-dev -q -n; npm ci --silent; npm run build | grep -E "error|built in|bundle ok"
php artisan migrate --force; php artisan view:clear -q; php artisan optimize -q
chown -R www-data:www-data storage bootstrap/cache public/build public/img
systemctl restart php8.2-fpm matplace-worker 'matplace-worker@*'; php artisan up >/dev/null; git log --oneline -1
```
Pak `curl -s -o /dev/null -w '%{http_code}' https://matplace.com/<stránka>` na dotčené stránky. Při běžícím tisku
spusť na pozadí smyčku `until … sleep 120` s výpisem „FARM FREE“ a nasaď po ní. Výpadek bývá 12 s.

## Stav po dokončení Shorts #81 (podrobně `docs/TODO.md` řádky 71–82)

- **Produkce je stále bf7cde3a = main c501dcec z 9. 11. 21:30. Shorts #81 je nově v main a NENASAZENÝ.**
  Nasazuje se skriptem `scripts/deploy_server.sh`: `ssh -i ~/.ssh/hetzner_matplace -o BatchMode=yes root@178.104.162.164 'bash -s' < scripts/deploy_server.sh`
  (fetch, kontrola čistoty a konfliktu, záloha .env + mysqldump, merge origin/main do feature/farm, composer, npm, migrate,
  optimize, reload php-fpm, queue:restart, curl na stránky). Jen na Romanovo „nasaď“.
- **Živé od 9. 11.:** rozcestník na hlavní straně (#78 + 2. kolo #80), kopie u zarovnaných barev (#72), kopie jako instance
  jednoho objektu (#77), opravená karta filamentu z e‑shopu (#79) a fotky z e‑shopu i pro zákazníky (#82).
- **Shorts (TODO #81) hotové a sloučené:** session 2 odevzdala `feature/shorts-titles-format` commit `6bba0b8a`, merge do main
  `0ce6c33e`. Dokončeno A–F: AI/katalogové názvy bez jmen souborů, fronta a 30denní opakování, pět klipů s mobilním natočením
  nebo nahráním v `/admin/youtube`, automatický střih se zvukem `removal`, UTM trychtýř, Analytics a CSV. Nad sloučeným main
  47 PHP testů/1004 asercí, 2 JS testy, Pint, TypeScript a build prošly; 4 reálné rendery v tomto běhu přeskočené bez
  `FFMPEG_BIN`, agent předtím ověřil reálné Short rendery 10 testů/269 asercí. Fyzický mobil a samostatný reálný render všech
  klipů zůstávají k ověření po deployi. Nesouvisející starý reálný test měsíční délky čeká 98 s, pravidla dávají 74 s; neměnit
  v rámci #81. Dokumentace `docs/AL.md` §13.9. **Čeká na „nasaď“, migraci, znovu připojení Analytics a Romanovy volby.**
  Podklady mimo repo: `video-scenar-a-studio.md`, `stella-test-a-fotky.md`. Ball3 zůstává `pending` bez videa na YouTube
  a název mu Roman doplní před zveřejněním.
- **Čeká na Romana:** „Ball3“ pojmenovat; fotky od Stelly (pak do karet dárků přes správu fotek nástrojů, AN.md §15);
  datum do Vánoc (15. 12.) a lhůta 24 h v `/admin/farm/settings`; ceny „od“ u karet (jen busta 99 Kč); limit sliceru
  varianta A/B (#77, dnes B); Hetzner CX53 (16 vCPU, 29,49 €) zkusit znovu, když bude volný (CPX/CCX odložil).
- **Zadání čekající na „zadej“/„spusť“:** #76 opakování po selhání tisku viditelné ve frontě; agent 0.2.x: odmítnout
  soubor s více `T<slot>` bez řádku mapování, zpráva při přerušené výměně; přesný postup zkoušky webu pro Stellu.
- Tiskárny 9. 11.: vše z 8. 11. dotištěno (K X 145 i 149 se 7 barvami → 5 cívek, U1 HskDT3119 16 h, U2 148, Core One
  147); K X mapování ověřené, S1 bere řádek mapování na prvním řádku (10116 pryč u změn podle výšky; jedna podložka
  s více filamenty na S1 ještě neběžela).
- Worktree: `C:\matplace-farmcolors-wt` = main (push `git push origin feature/farm-colors:main` a totéž na `server`),
  `C:\matplace-suite-wt` na zkušební sloučení a testy (má build), `C:\matplace-fixes-wt` na PHP hotfixy (bez buildu,
  testy stránek tam padají na manifestu), `C:\matplace-gcode-wt` session 2, `C:\matplace-edit-wt` session 3; všechny
  čisté. Produkční DB: čtení jen s Romanovým výslovným svolením (dal ho 9. 11. pro tabulku videí).

## První kroky nové session

1. Přečti `MEMORY.md`, `project_coordinator_handoff.md`, `project_home_router.md`, `project_youtube_shorts.md`, `docs/TODO.md` (řádky 71–82) a `docs/prompts/shorts-nazvy-format.md`.
2. `git -C C:\matplace-farmcolors-wt fetch` a porovnej `origin/main` se serverem (`git log --oneline -1` tam i tam).
3. `ListAgents` – které session běží; nic jim nepiš, dokud Roman nezadá práci.
4. Čekej na Romanovy pokyny; „zadej“ = napiš zadání a předej session, „nasad“ = nasaď, co je v main.
