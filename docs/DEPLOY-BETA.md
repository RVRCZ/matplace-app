# Nasazení kroků A0 až H na betu (jeden průchod)

Pro Romana. Všechno je na větvi `feature/h-tool-fixes` (`08449be`): kroky A0–F, bez osobního odběru, logo, opravy
nástrojů a sloučená farma s dvoubarevným QR kódem. Jednotlivé `docs/<krok>.md` zůstávají jako podrobnosti; tenhle
soubor je sled příkazů.

Pravidlo z dřívějška: **když sloučení na serveru nejde fast‑forwardem, zastavit a nic nepřepisovat.** Server už
jednou držel práci jiné session a skryté sloučení rozbilo API agentů.

## 1. Lokálně: `main` a push (5 minut)

```
cd C:\matplace-web-wt
git fetch origin
git log --oneline origin/main..feature/farm origin/main..feature/youtube | wc -l    # orientačně: co všechno jde do main
git merge-base --is-ancestor origin/main feature/h-tool-fixes && echo "main je predek, fast-forward OK"

git branch -f main feature/h-tool-fixes          # lokální main
git push origin feature/h-tool-fixes:main        # GitHub main (fast-forward)
git push origin feature/a0-locale feature/a-account feature/b-designers feature/c-catalog feature/d-shipping feature/e-seo feature/f-admin feature/g-no-pickup-logo feature/h-tool-fixes
git push server feature/h-tool-fixes:main        # holý repo na Hetzneru, pokud z něj server bere
```

Když `merge-base` neřekne OK, někdo mezitím posunul `main`; napiš mi, než cokoliv pushneš.

## 2. Server: kód (15 minut, krátká odstávka)

```
ssh -i ~/.ssh/hetzner_matplace root@178.104.162.164
cd /var/www/matplace-app

git status --short                      # musí být prázdné (snad až na engines/orca/profiles/result.json)
git fetch --all
git log --oneline origin/main..HEAD     # MUSÍ být prázdné: commity, které má server a main ne → STOP, napiš mi
git log --oneline HEAD..origin/main | wc -l   # kolik commitů přijde (desítky)

cp .env /root/matplace-app.env.bak-$(date +%Y%m%d-%H%M)
mysqldump matplace_app > /root/matplace_app-$(date +%Y%m%d-%H%M).sql   # název DB podle .env (DB_DATABASE)

php artisan down --retry=30
git merge --ff-only origin/main         # když odmítne → php artisan up a STOP
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force             # 7 migrací: 2026_10_02 … 2026_10_08
php artisan storage:link
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
systemctl restart php8.2-fpm matplace-worker
php artisan up
```

Migrace mění `users`, `model_files`, `catalog_models` (přejmenování sloupců, popisy do JSON), `farm_orders`,
`farm_settings` (doručení bez odběru, měna účtů) a zakládají tabulky designérů, událostí, blogu, kolekcí a adminu.
Na betě je v `catalog_models` jen zkušební import, proto je záloha DB nahoře jen pojistka.

## 3. Server: `.env` (doplnit, pak `php artisan optimize`)

Doplň do `/var/www/matplace-app/.env`:

```
# krok B – designéři, překlady
IMPORT_HTTP_PROXY=            # bez proxy vrací Printables ze serveru 403; formát http://user:pass@host:port
ENGINE_IMPORT=live
ENGINE_TRANSLATOR=claude      # ANTHROPIC_API_KEY už je

# krok C – starý katalog (jen čtení; uživatel jen se SELECT)
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=catalog3d
LEGACY_DB_USERNAME=
LEGACY_DB_PASSWORD=
LEGACY_THUMBS_DIR=/var/www/matplace/public/assets/thumbs
LEGACY_ASSETS_URL=https://matplace.com        # po přepnutí domén https://legacy.matplace.com
LEGACY_BLOG_IMAGES_DIR=/var/www/matplace/storage/blog-images
LEGACY_HOST=https://legacy.matplace.com
FARM_EUR_RATE=25

# krok D – Packeta (hodnoty z produkčního .env starého webu)
ENGINE_SHIPPING=packeta
PACKETA_API_KEY=
PACKETA_API_PASS=
PACKETA_ESHOP=

# krok E – měření (bez hodnot se nic nenačítá)
SEO_INDEXABLE=false           # beta nemá být ve vyhledávačích; na matplace.com true
GA_MEASUREMENT_ID=
META_PIXEL_ID=
GOOGLE_SITE_VERIFICATION=
SEO_FACEBOOK_URL=
SEO_INSTAGRAM_URL=

# krok F – admin
ENGINE_ASSISTANT=claude
ENGINE_SOCIAL=fake            # na meta přepnout až s tokenem
META_SYSTEM_TOKEN=
META_PAGE_ID=
META_IG_ID=
META_AD_ACCOUNT_ID=
META_CAPI_TOKEN=
AI_USD_CZK=23
```

Potom `php artisan optimize && systemctl restart php8.2-fpm matplace-worker`.

## 4. Server: nginx a limity (5 minut)

V `/etc/nginx/sites-available/matplace-app` (beta):

- v bloku statických souborů změnit `try_files $uri =404;` na `try_files $uri /index.php?$query_string;`
  (jinak `/og/….png` vrací 404 a obrázky pro sdílení nefungují);
- `client_max_body_size 520M;` (hromadný zip designéra má až 500 MB).

V php.ini pro fpm (`/etc/php/8.2/fpm/php.ini`): `upload_max_filesize = 520M`, `post_max_size = 520M`.

```
nginx -t && systemctl reload nginx && systemctl restart php8.2-fpm
```

## 5. Server: jednorázové příkazy (30 minut, podle dat)

```
cd /var/www/matplace-app
php artisan matplace:import-catalog --dry-run      # starý katalog: vypíše, co by udělal
php artisan matplace:import-catalog                # kategorie, 8 840 modelů, náhledy, kolekce
php artisan matplace:import-blog --dry-run
php artisan matplace:import-blog                   # 7 článků, obrázky do storage/app/public/blog
php artisan matplace:packeta-carriers --suggest    # dopravci Packety; ID z výpisu opsat do config/farm.php
php artisan matplace:sitemap                       # dál denně ze scheduleru
php artisan matplace:translate-catalog --dry-run   # kolik modelů by šlo k překladu (platí se za volání AI)
php artisan matplace:classify-catalog --dry-run    # kolik modelů je bez kategorie (totéž)
```

Překlady a klasifikaci pusť nejdřív v dávce (`--limit=50`), podívej se do `/admin/ai`, kolik to stálo, a teprve
pak zbytek. Ověř, že cron se `php artisan schedule:run` běží (`crontab -l`); plán přibral `matplace:sitemap`
denně, `matplace:packeta-carriers` týdně a `matplace:events-rollup` měsíčně.

## 5b. Worker: dvě fronty

Překlady a klasifikace katalogu jdou do fronty `ai`; worker ji bere, až když je běžná fronta prázdná (jinak
nahraný model zákazníka čekal za stovkami překladů). Po nasazení aktualizovat službu:

```
cp deploy/matplace-worker.service /etc/systemd/system/matplace-worker.service
systemctl daemon-reload && systemctl restart matplace-worker
```

## 5c. Printables a MakerWorld: proxy přes Romanův počítač

Cloudflare odpovídá adrese serveru (IPv4 i IPv6) výzvou, takže import z Printables ani vyhledávání v Printables
ze serveru nejdou. Řešení od 3. 10. 2026: na Romanově PC běží naplánovaná úloha „matplace import relay“
(`C:\matplace-relay
elay.cmd`): malá HTTP proxy na `127.0.0.1:3128` a SSH tunel `-R 127.0.0.1:3128` na server.
Server má `IMPORT_HTTP_PROXY=http://127.0.0.1:3128`; přes proxy jdou jen volání API Printables a MakerWorld
(import i hledání), obrázky se stahují přímo. Kontrola ze serveru:

```
curl -s -o /dev/null -w "%{http_code}" -x http://127.0.0.1:3128 -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36" https://api.printables.com/graphql/ -d '{"query":"{ __typename }"}'
```

Když je PC vypnuté, import i hledání v Printables potichu nejdou (hledání vrátí jen náš katalog). Trvalejší
náhrada je placená proxy s rezidenční IP a změna `IMPORT_HTTP_PROXY`. Vyhledávání v MakerWorldu nefunguje
ani přes proxy (jejich hledání vyžaduje prohlížeč), import modelu podle adresy ano.

## 6. Kontrola (10 minut)

```
curl -sI https://beta.matplace.com/en/tools/sign | head -1          # 200
curl -sI https://beta.matplace.com/tools/sign?lang=en | head -1     # 301
curl -sI https://beta.matplace.com/katalog | head -1                # 301 na /model
curl -sI https://beta.matplace.com/og/site/home.png | head -1       # 200 (po opravě nginx)
curl -s  https://beta.matplace.com/robots.txt                        # Disallow: / (SEO_INDEXABLE=false)
curl -sI https://beta.matplace.com/sitemap.xml | head -1            # 200
```

V prohlížeči: úvod s logem, `/model/<slug>` ze starého webu, `/blog`, registrace e‑mailem a ověření, `/account`,
zapnutí designérského profilu a import z Printables (ověří proxy), objednávka s doručením na výdejní místo
(ověří klíče Packety; první zásilku vytvořit na zkoušku), `/admin` (statistiky, katalog, e‑maily).

## Když se něco pokazí

- Kód: `git log -1` pro ověření, `php artisan optimize`, restart. Vrátit jde `git reset --hard <předchozí head>`
  (zapiš si ho před krokem 2: `git rev-parse HEAD`), `.env` ze zálohy, DB z dumpu; migrace zpět
  (`migrate:rollback --step=7`) jsou napsané, ale u přejmenovaných sloupců katalogu je dump jistější.
- Worker: `journalctl -u matplace-worker -n 50`, aplikace: `storage/logs/laravel.log`.

## 7. Přepnutí na matplace.com (provedeno 3. 10. 2026, 16:00)

1. DNS `legacy.matplace.com` → server (Roman, Wedos); certifikát `certbot certonly --nginx -d legacy.matplace.com`
   (Let's Encrypt si první neúspěšný pokus pamatuje hodinu – negativní cache zóny).
2. `sites-enabled`: `legacy.matplace.com.conf` (starý web, `/model`, `/blog`, `/katalog` → matplace.com),
   `matplace.com.conf` (nová aplikace; `beta.matplace.com` dál odpovídá stejnou aplikací pod vlastním certifikátem,
   `matplace.cz` má vlastní blok se svým certifikátem); vypnuté `matplace`, `matplace-app` a zapomenutá záloha
   `matplace.bak.20260523-170654`, která v `sites-enabled` ležela od května (přesunutá do `/root`).
3. `.env`: `APP_URL`, `GOOGLE_REDIRECT_URI`, `FACEBOOK_REDIRECT_URI` na matplace.com, `LEGACY_ASSETS_URL` na legacy,
   `SEO_INDEXABLE=true`, `MAIL_ALWAYS_TO` pryč (záloha `/root/matplace-app.env.bak-switch-*`). `optimize`, restart,
   `matplace:sitemap` (12 589 adres).
4. Starý web se nezměnil (`APP_URL` má dál matplace.com; odkazy z jeho e‑mailů na účty a zakázky nová aplikace
   přesměruje na legacy).

Po přepnutí v konzolích (Roman): Google a Facebook redirect URI `https://matplace.com/auth/{google,facebook}/callback`;
Stripe webhook pro `https://matplace.com/webhooks/payments/stripe` (ten na betě dál funguje, beta je alias);
Search Console: sitemapa `https://matplace.com/sitemap.xml`.
