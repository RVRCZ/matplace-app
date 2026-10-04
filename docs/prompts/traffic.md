# Zadání: kontrola návštěvnosti a měření po přechodu na matplace.com

Datum: 4. 10. 2026. Pracuj v repozitáři `C:\matplace-app` (Laravel 12) ve **vlastním worktree**
(`git worktree add C:\matplace-traffic-wt feature/traffic`, větev z `main`; `main` je nasazený na matplace.com).
Nikdy `git add -A`, nikdy `git stash`. Server (`ssh -i ~/.ssh/hetzner_matplace root@178.104.162.164`,
`/var/www/matplace-app`, MySQL `matplace_app`) **jen ke čtení** – dotazy do DB, logy nginx (`/var/log/nginx/`),
`.env` nečíst nahlas; nasazuje Roman po schválení (`docs/DEPLOY-BETA.md`).

## Cíl

Roman chce vědět, **kolik skutečných lidí** na web chodí, odkud, co dělají a kde odpadají – a věřit těm číslům.
Po přepnutí domény (3. 10. 2026) a odeslání sitemap (12 589 adres) je potřeba (1) ověřit, že měření měří
správně (a neměří roboty), (2) dát Romanovi jeden srozumitelný přehled, (3) nastavit týdenní report.

## Co dnes existuje (přečti si kód; tohle je mapa)

- **Vlastní měření bez cookies**: `App\Support\Track` zapisuje do tabulky `events`
  (`type` visit/view/calculation/upload/order_created/order_paid/register/search/download…, `session_id`
  = anonymní session, `source` google/seznam/bing/facebook/instagram/designer/direct/other, `ref_slug`
  (designér, `RememberReferral`), `utm` JSON, `meta` JSON, `locale`). Měsíčně `matplace:events-rollup` skládá
  starší než 13 měsíců do denních souhrnů.
- **Admin**: `/admin/stats` (`StatsController`, `App\Domain\Stats\Funnel`: období 7/30/90, zdroje, cesty
  `PATHS`, výsledky customer/owner/designer, designéři), `/admin/stats/ai` (AI aktivita), vyhledávání.
- **Souhlas**: `App\Support\Consent` (cookie `consent` a1m0) – analytické/marketingové skripty se načítají jen po
  souhlasu; Meta pixel (`META_PIXEL_ID`) + Conversions API (`SocialPublisher::conversion`, jen se souhlasem).
  Google Analytics **není** zapojené (žádný GA/GTM klíč v `.env`) – rozhodni s Romanem, zda GA4 chce (jen po
  souhlasu), nebo zda vlastní měření stačí; Search Console má sitemap odeslanou 3. 10.
- **Sociální sítě**: videa na YouTube/Facebook/Instagram s UTM (`utm_source=facebook&utm_medium=social&utm_campaign=video`),
  příspěvky o modelech `/admin/content/meta` (UTM `post`), YouTube popisy s odkazem na web.
- **Staré adresy**: `LegacyRedirects` + `config/legacy.php` (301 ze starého webu), `legacy.matplace.com`
  = starý web, `matplace.cz` → 301 na .com.

## Co jsem našel (4. 10. 2026) – začni tím

- Za 7 dní **14 047 „visit“ událostí ve 14 036 sessions**, z toho **13 964 „direct“**, 3. 10. 10 467 návštěv za
  den. Skoro každá návštěva = nová session a bez dalšího kroku. To nejsou lidé: po odeslání sitemap prochází
  Googlebot/Bingbot/ostatní roboti 12 589 adres a každou započítáme. Reálná návštěvnost je o řád níž.
- Konverze za 7 dní: 45 kalkulací, 16 nahrání, 15 založených zakázek, 5 zaplacených, 4 registrace, 2 hledání.

## Co udělat

**1. Roboti pryč z čísel (první commit, nasadit co nejdřív).**
- V `Track` (nebo middleware, kde vzniká `visit`) **nepočítat známé roboty**: User‑Agent (Googlebot, bingbot,
  Seznambot, AhrefsBot, SemrushBot, PetalBot, GPTBot, ClaudeBot, facebookexternalhit, YandexBot…), HEAD
  požadavky, prázdný UA, požadavky bez `Accept: text/html`. Místo smazání je označ (`meta.bot = true` nebo
  vlastní `type` `crawl`), ať jde ukázat i „kolik toho roboti prošli“ (užitečné pro SEO: indexace běží).
- Lepší signál „člověk“: návštěva potvrzená z prohlížeče (malý beacon z JS, `sendBeacon`, bez cookies, bez
  souhlasu – nic osobního) → `visit` dostane `meta.js = true`; roboti bez JS se tím odliší i bez seznamu UA.
- Zpětně: označit robotí návštěvy v `events` za posledních 30 dní podle nginx logu (UA je jen tam) jedním
  příkazem `matplace:events-bots --since=2026-09-01` (idempotentní, nic nemaže).
- `/admin/stats` ukazuje jen lidi; roboti zvlášť v jednom řádku.

**2. Ověřit, že měření sedí.** Pro každý typ události projít reálnou cestu na matplace.com (anonymně i přihlášen,
cs/en/es): návštěva ze Seznamu/Googlu (referer), z Facebooku s UTM, odkaz designéra (`ref_slug`), kalkulace,
nahrání, zakázka, zaplacení, registrace, stažení, nástroje (`PATHS`). Zkontrolovat, že `source` a `utm` se
plní, že `session_id` drží v rámci návštěvy (dnes 14 036 sessions na 14 047 návštěv i u lidí?), že se
nepočítají vlastní návštěvy admina (přihlášený admin → vynechat) a stránky `/admin`. Co nesedí, opravit s testem.

**3. Jeden přehled pro Romana** (`/admin/stats`, nahoře): za 7 / 30 dní **lidé** (sessions), **zdroje** (Google,
Seznam, Facebook/Instagram/YouTube – podle UTM `video` zvlášť, designéři, přímé), **jazyky**, **nejnavštěvovanější
stránky** (nástroje, katalog, modely designérů, blog), **trychtýř** kalkulace → nahrání → zakázka → zaplaceno
s procenty, **hledání** (co lidé hledali a nenašli), **404 a staré adresy** (z nginx logu: co míří na
neexistující cesty = kandidát na redirect v `config/legacy.php`), **roboti** (kolik adres prošli, od koho).
Čísla před změnou (dnes) a po ní musí být porovnatelná – označ v grafu den nasazení filtru robotů.

**4. Týdenní report e‑mailem** (`matplace:stats-report`, pondělí 7:00, `FARM_ADMIN_EMAIL`): totéž v textu
s porovnáním k předchozímu týdnu, + co se zveřejnilo (videa, příspěvky) a kolik návštěv přivedlo (UTM).

**5. Externí zdroje (jen doporučit, zapojit po Romanově rozhodnutí):** Search Console (klíčová slova, pokrytí,
Core Web Vitals – API přes servisní účet projektu „My Project 99799“), GA4 jen po souhlasu, YouTube Studio
(odkud chodí diváci). Nic z toho neposílá data bez souhlasu návštěvníka.

## Pravidla

- Soukromí: žádné IP ani UA v `events` natrvalo (hash nebo jen „bot ano/ne“); nic osobního bez souhlasu; admin
  nepočítat.
- Výkon: `events` má statisíce řádků – indexy na (`type`, `created_at`), agregace v SQL, ne v PHP; rollup zachovat.
- Testy ke každé změně (`tests/Feature/StatsTest.php`, `TrackTest`…), Pint, dokumentace `docs/O.md` (co se
  počítá a co ne, definice „návštěvník“, jak se poznají roboti).

## Hotovo, když

`/admin/stats` ukazuje lidi bez robotů s věrohodnými zdroji a trychtýřem, roboti jsou vidět zvlášť, týdenní
report chodí, 404 ze starého webu mají seznam k rozhodnutí, `docs/O.md` vysvětluje čísla tak, že Roman ví, co
znamená „návštěvník“.
