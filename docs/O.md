# Návštěvnost: lidé, ne roboti

Větev `feature/traffic` (4. 10. 2026), zadání `docs/prompts/traffic.md`. Po přepnutí domény a odeslání sitemap
ukazovaly statistiky 14 047 „návštěv“ za týden, skoro všechny „přímé“, každá v nové session a bez dalšího kroku.
To nebyli lidé. Tenhle krok dělá z `/admin/stats` čísla, kterým se dá věřit, a posílá je každé pondělí e‑mailem.

## Co znamená „návštěvník“

**Návštěvník = jeden prohlížeč, který web opravdu zobrazil.** Přesně:

1. Prohlížeč otevře první stránku → zapíše se `visit` (jedna na session prohlížeče; anonymní cookie `mp_sid`,
   kterou web stejně potřebuje pro nahrané modely – žádná další cookie kvůli měření nevzniká).
2. Skript stránky zavolá domů (`POST /api/seen`, jen adresa stránky) → návštěva dostane `meta.js = true`.
   Robot stránku stáhne, ale skript nespustí, takže potvrzení nepřijde.
3. Kdo skript blokuje, ale něco udělá (nahraje model, spočítá cenu, hledá, objedná, registruje se), dostane
   `meta.act = true` – to robot nedělá.

Mezi lidi se počítá návštěva, která má `js` nebo `act` a nemá značku `bot` ani `staff`. V kódu je to jedno místo:
`Event::people()`. Stejné pravidlo používá přehled, podrobné cesty (`Funnel`) i týdenní e‑mail.

**Co se nepočítá**

| kdo | jak se pozná | co se zapíše |
|---|---|---|
| známý robot (Google, Bing, Seznam, Ahrefs, GPTBot, ClaudeBot, náhledy odkazů…) | jméno v User‑Agent (`App\Support\Bots::FAMILIES`) | `crawl` se jménem robota a adresou |
| neznámý robot, skript | slovo bot/crawl/spider/curl/python…, chybějící „Mozilla/“, prázdný User‑Agent, požadavek `HEAD`, nebo stránka vyžádaná bez `Accept: text/html` | `crawl`, robot `other` |
| robot převlečený za prohlížeč | návštěvu nikdy nepotvrdí skript ani akce | `visit` bez `js` – v přehledu „roboti v převleku“ |
| my (admin) | prohlížeč, ze kterého se někdy přihlásil admin (`anonymous_sessions.staff`) | nic; co ten prohlížeč udělal za posledních 30 dní, dostane `meta.staff` a z čísel zmizí |
| naše zkušební průchody (skripty, testovací prohlížeč; TODO #10, `docs/AJ.md`) | cookie `mp_test=1` nebo hlavička `X-Matplace-Test` | jako admin: prohlížeč se zapamatuje (`anonymous_sessions.staff`), nic se nezapíše a co udělal dřív, dostane `meta.staff` |
| stránky `/admin` | adresa | nic |

Robot, který umí spustit JavaScript a tváří se jako běžný Chrome, projde jako člověk. Takových je málo a nejdou
poznat bez sledování lidí; s tím se počítá.

**Dny jsou v UTC** (jako všechno v databázi), tedy o 2 hodiny posunuté proti našemu času.

## Porovnatelnost před a po

Potvrzování začíná prvním potvrzeným `visit` (`App\Domain\Stats\Humans::since()` – najde si ten okamžik samo,
nic se nenastavuje). Starší návštěvy potvrdit nešlo, proto se počítají všechny, které nejsou označené jako robot.
Označí je jednorázově příkaz níže. V grafu „Lidé po dnech“ je ten den vyznačený svislou čárou.

```
php artisan matplace:events-bots --since=2026-09-01 --logs="/var/log/nginx/access.log*" --missing --dry-run
php artisan matplace:events-bots --since=2026-09-01 --logs="/var/log/nginx/access.log*" --missing
```

Příkaz nic nemaže, jen doplní `meta.bot` (důvod) u návštěv, které nikdo nepotvrdil:

- `agent` – User‑Agent session (nebo řádku v logu) patří robotovi; seznam je dnes širší než v době návštěvy,
- `burst` – z jedné IP adresy přišlo za den 6 a více „nových návštěvníků“ a žádný neudělal nic dalšího
  (prohlížeč si cookie pamatuje, robot ne; `--burst=N` práh mění),
- `no-assets` – podle logu nginx ten, kdo stránku stáhl, nikdy nestáhl styly ani skripty webu.

Návštěva, po které někdo něco udělal, zůstane vždy. `--dry-run` vypíše tabulku po dnech (kolik robotů podle čeho,
kolik zůstane) a nic nezapíše – **nejdřív si ji přečti**. Druhé spuštění nic nezmění. IP adresy ani User‑Agenty se
nikam nezapisují; z logu se čtou jen do paměti. Log musí být ve formátu nginx „combined“ (příkaz ohlásí, kolik
řádků nepřečetl); `.gz` umí. Na serveru ho spusť jako root (log nginx `www-data` nepřečte) a pak
`chown -R www-data:www-data storage`.

## Přehled v `/admin/stats`

Nahoře, za 7 / 30 / 90 dní, vždy vedle stejně dlouhého období předtím (`App\Domain\Stats\Overview`):

- **Lidé na webu → spočítali cenu → nahráli model → založili zakázku → zaplatili**, u každého podíl z lidí.
  Kroky se počítají samostatně (cenu jde spočítat i u modelu z nástroje, bez nahrání).
- **Lidé po dnech** s čárou „od tohoto dne jen potvrzené návštěvy“.
- **Odkud přišli**: Google, Seznam, Bing, Facebook, Instagram, YouTube (nově), odkazy designérů, přímo, ostatní.
  Zdroj určuje první stránka návštěvy: `utm_source`, jinak odkazující web.
- **Odkazy z videí a příspěvků (UTM)**: `utm_source / utm_medium / utm_campaign`, kolik lidí a kolik z nich
  zaplatilo. Videa mají `campaign=video`, příspěvky o modelech `post`. Popis videa na YouTube má odkaz nově také
  s UTM (`utm_source=youtube`), protože YouTube odkazující adresu často neposílá – platí pro nově nahraná videa.
- **Jazyky**.
- **Nejnavštěvovanější stránky**: zobrazení hlášená prohlížečem (`page`). Jedna stránka = jeden řádek pro
  všechny jazyky. Adresy se soukromým odkazem se ukládají jako vzor (`/c/{calculation}`, `/farm/orders/{order}`),
  token se do statistik nedostane. Pro dobu před potvrzováním se ukazují stránky, na které lidé přišli.
- **Hledali a u nás nenašli** (odkaz na celé vyhledávání).
- **Adresy, které neexistují** (`missing_pages`): co skončilo 404, kolikrát lidé a kolikrát roboti, a odkud
  tam vedl odkaz. To je seznam k rozhodnutí o přesměrování v `config/legacy.php`. Pokusy o cizí systémy
  (`wp-login.php`, `.env`, `/cgi-bin/`…) a chybějící soubory se nesbírají. Aplikace počítá sama od nasazení
  (`App\Http\Middleware\RecordMissing`); dobu před ním doplní `matplace:events-bots --missing` z logu.
- **Roboti**: jeden řádek – kolik stránek a kolik různých adres který robot prošel, plus počet „robotů
  v převleku“. Po odeslání sitemap je vysoké číslo u Googlu dobrá zpráva: indexace běží.

Pod přehledem zůstávají podrobné cesty (zákazník, majitel tiskárny, designér) s filtry; i ty teď počítají jen lidi.

## Týdenní e‑mail

`matplace:stats-report` – každé pondělí v 7:00 našeho času na adresu správce farmy (`FARM_ADMIN_EMAIL`, nastavení
farmy `admin_email`). Týden pondělí–neděle proti týdnu před ním: lidé a trychtýř, zdroje, jazyky, co se zveřejnilo
(videa se zhlédnutími na YouTube, příspěvky) a kolik lidí přivedly označené odkazy, stránky, marná hledání,
neexistující adresy, roboti.

```
php artisan matplace:stats-report --print                 # jen vypíše
php artisan matplace:stats-report --to=nekdo@example.com  # pošle jinam
```

## Co se ukládá a jak dlouho

- `events`: typ, anonymní session, jazyk, zdroj, UTM, pár kódů v `meta`. **Žádná IP adresa, žádný User‑Agent**,
  žádný text, který někdo napsal. Nové typy: `page` (zobrazení stránky), `crawl` (stránka stažená robotem;
  `source` = robot, bez session).
- `crawl` se po 30 dnech skládá do denních součtů (`events_daily`), ostatní po 13 měsících
  (`matplace:events-rollup`, nově denně ve 3:50).
- `missing_pages`: adresa, počty, poslední odkazující web. Nic o návštěvníkovi.
- Veřejné texty (stránka Cookies, Zásady) platí beze změny: vlastní statistika na našem serveru, bez dalších
  cookies. Volání `/api/seen` posílá jen adresu stránky.

## Ověření měření (bod 2 zadání)

Lokálně proti běžícímu serveru, skriptem a skutečným Chrome (headless, který opravdu spustil skript stránky):

| cesta | výsledek |
|---|---|
| příchod z Googlu, Seznamu, Bingu, YouTube (odkazující web) | `source` správně |
| Facebook / YouTube / Instagram s UTM | `source` i `utm` správně, vidět v tabulce UTM |
| `/en/…`, `/es/…` | `locale` správně; stránka v přehledu bez jazykové předpony |
| druhá a další stránka téhož prohlížeče, hledání | stejná session, jedna návštěva, další `page`, `act` |
| Googlebot, SeznamBot, `Accept: */*`, `HEAD` | `crawl`, žádná návštěva |
| „prohlížeč“ bez skriptu, pokaždé bez cookie | `visit` bez `js` → roboti v převleku, ne lidé |
| neexistující adresa, `wp-login.php`, stará adresa z `config/legacy.php` | zapsána / nezapsána / přesměrována 301 |
| admin před přihlášením, po něm i po odhlášení | nepočítá se (test `StatsTest`) |

Odkaz designéra (`ref_slug`), kalkulace, nahrání, zakázka, zaplacení, registrace a stažení mají vlastní testy
z dřívějška (`DesignerTest`, `AdminContentTest`, `FarmOrderFlowTest`) a prošly beze změny.

**Neověřeno na ostrém webu.** Čtení z produkční databáze a logů nginx tahle session neměla povolené, takže:
nevím, kdo přesně těch 14 000 návštěv dělal (roboti se jménem už se nepočítali dřív – nejspíš roboti s běžným
User‑Agentem, co nedrží cookies; `--dry-run` to ukáže), a formát logu nginx na serveru jsem neviděl.

## Nasazení

Jedna migrace (`2026_10_12_100000_traffic_people_and_robots`: sloupec `anonymous_sessions.staff`, index
`events.created_at`, tabulka `missing_pages`), žádné nové klíče v `.env`. Postup jako vždy (`docs/DEPLOY-BETA.md`):
merge jen fast‑forward, `composer install`, `npm ci && npm run build`, `migrate --force`, `optimize`, restart
`php8.2-fpm` a `matplace-worker`. Potom:

1. Přihlas se jako admin v každém prohlížeči, který používáš (počítač, mobil) – tím se z čísel vyřadí.
2. `matplace:events-bots … --dry-run`, přečíst, pak naostro.
3. `matplace:stats-report --print` a jednou `--to=` sobě na zkoušku.

## Externí zdroje (jen doporučení, nic z toho není zapojené)

- **Search Console** – jediné místo, kde jsou vidět hledané výrazy, pokrytí indexu a Core Web Vitals. Doporučuji
  zapojit přes API servisním účtem projektu „My Project 99799“ a přidat do týdenního e‑mailu (kliknutí,
  zobrazení, top dotazy, kolik z 12 589 adres je v indexu). Neposílá žádná data návštěvníků.
- **GA4** – nedoporučuji zapínat teď. Měřilo by jen lidi, kteří dají souhlas (menšina), takže čísla budou nižší
  než naše a nepůjdou srovnat. Smysl dostane, až poběží reklama v Google Ads. Kód je připravený
  (`services.ga4.id`, načte se jen po souhlasu).
- **YouTube Studio** – odkud chodí diváci a kolik jich kliklo na odkaz v popisu; s UTM v popisu teď uvidíme
  druhou půlku u nás.
- **Meta pixel / Conversions API** – beze změny, jen se souhlasem.

## Skenery (TODO #36, 19. 10. 2026)

Skenery zranitelností se v přehledu za 7 dní počítaly jako lidé: /wp 276 „lidí“, /phpinfo 77, /_profiler/phpinfo 89,
/credentials 33 s refererem `178.104.162.164//.aws/credentials` a spamové slugy `/119211/is-binary-options…`.
`App\Support\Bots::scanner(cesta, referer)` je teď pozná, a to všude: návštěva skeneru je `crawl` se zdrojem
`scanner` (nikdy osoba), jeho 404 je robotí zásah (živě i z logu) a import logu označí starou návštěvu `bot =
scanner`. Pozná je podle cesty (`/wp*`, `*.php`, `/phpinfo`, `/_profiler`, tečkové soubory jako `.aws` a `.env`,
`/credentials`, `/vendor/`, `/cgi-bin`, `/phpmyadmin`, číselný spamový slug) a podle refereru (holá IP adresa
místo domény, nebo referer, který je sám cestou skeneru). Seznam „Adresy, které neexistují“ třídí řádky až při
čtení, takže platí i pro stará data. Řádky skenerů v něm nejsou a místo nich je jedno číslo „skenery: n“ (v adminu
i v týdenním e‑mailu).

**„/blog“ s refererem wp-login.php:** to není sražení cesty. `/blog//wp-login.php` zůstává v aplikaci i ve čtení
logu celé a seznam ho jako `.php` nesbírá. Na `/blog` se ale v logu ořízne `/blog/?author=1` (WordPress sken
uživatelů). Referer s IP serveru ukazuje, že skenery míří na `http://178.104.162.164/…`. Nginx jim tam (default
server) vrací 404 a do společného access logu je zapíše bez hostitele, takže vypadají jako 404 na matplace.com.
Doporučení pro server: default server s vlastním `access_log` (nebo `return 444`), pak se do logu webu nedostanou
vůbec. Mimochodem opraveno čtení logu: cíl začínající `//` vzal `parse_url` za hostitele (`//blog/x` → `/x`,
`//wp-login.php` → `/`). Teď se čte jako cesta, stejně jako v aplikaci.

## Vlny (TODO #40, 24. 10. 2026)

23. a 24. 10. hlásil přehled 323 a 177 „lidí“ za den (17.–22. 10. bylo 16–69). Posledních 24 h 495 „lidí“, všichni
`direct`, 492 `en`, každý jediná stránka `/model/<slug>` (katalog, zhruba podle abecedy) nebo `/login` (29×),
1 kalkulace a 0 zakázek (čísla řídící session z produkce). Je to jeden stroj: headless prohlížeč spustí skript
stránky, a projde tak potvrzením „prohlížeč“, které roboty jinak drží venku.

**Pravidlo** (`App\Domain\Stats\Waves`, příkaz `matplace:stats-wave`):
- **Kandidát** je jen návštěva, která se dnes počítá mezi lidi (podmínka `Event::people()`: potvrzená skriptem
  prohlížeče). Musí přijít bez refereru a kampaně (`direct`), její sezení nesmí nic udělat (žádná akce z
  `Track::ACTIONS`, žádné `act`) a smí otevřít nejvýš jednu stránku. Roboty, které nikdo nepotvrdil, pravidlo
  nechává být, protože mezi lidi se nepočítají už teď. **Oprava 24. 10.:** první verze je brala taky (60 000 za
  týden). Dry-run na produkci tak „našel“ 2 545 návštěvníků vln v den s 19 lidmi a člověk se stejným běžným
  prohlížečem by v takové hodině spadl do jejich skupiny. Řídící session hodinové spouštění na den vypnula, naostro
  se nic neoznačilo. Test `test_robots_nobody_confirmed_make_no_wave_and_the_people_sharing_their_browser_stay`:
  3 000 nepotvrzených robotů a 30 lidí se stejným prohlížečem dají 0 vln a 30 lidí. Skutečná potvrzená vlna (12 za
  hodinu) se najde dál.
- Tabulka dry-runu „lidé potom“ **lidi přepočítá** (stejná podmínka, navržené vlny vyjmuté), neodečítá.
- **Vlna** je aspoň **8 kandidátů za jednu hodinu** se stejným otiskem:
  - stejný prohlížeč (User-Agent a jazyk) **přes mnoho různých stránek** (aspoň polovina různých): procházení
    katalogu, ne sdílený odkaz;
  - nebo stejná síť (IPv4 /24, IPv6 /48) přes mnoho různých stránek;
  - nebo stejná síť na přihlašovacích stránkách (`/login`, `/register`, `/forgot-password`), které nikdo nesdílí.
- Návštěvy vlny dostanou `meta.bot = wave` (a `meta.wave` = prohlížeč / síť). Z lidí zmizí a v přehledu, v adminu
  („vlny: n“ u robotů) i v týdenním e‑mailu jsou jedním číslem. Příkaz běží každou hodinu (`hourlyAt(5)`) za
  posledních 48 h a umí i vrátit: kdo mezitím něco udělal, značku ztratí. Nic se nemaže.

**Proč to nesebere lidi:** u nás chodí 16–69 lidí za den, tedy pár za hodinu. Sdílený odkaz (třeba 10 lidí ve stejném
iPhonu během hodiny) míří na jednu stránku, často z jedné mobilní sítě, protože operátoři sdílejí adresy (CGNAT), a
pravidlo „mnoho různých stránek“ ho nechá být. Kdo otevře druhou stránku, spočítá cenu nebo se přihlásí, kandidátem
není. Testy (`StatsTest::test_a_wave_is_one_machine_and_the_people_of_the_days_before_stay`) mají šest dní lidí
s těmito „podobami“, 7 podobných pod prahem, lidi s druhou stránkou, s kalkulací a z Googlu. Všech 20 lidí denně
zůstane, vlna 40 návštěv za hodinu a 12× `/login` z jedné sítě odejdou.

**Co ověřit na produkci (z této session nešlo):** automatický režim mi nedovolil číst produkční databázi, takže
otisk vlny (IP, user agent, kadence) a dopad na 17.–22. 10. jsem z dat neviděl. Pravidlo je postavené na číslech
zadání. Ověření je jeden příkaz, který nic nemění:

    php artisan matplace:stats-wave --since=2026-10-17 --dry-run

Tabulka po dnech „lidé teď / návštěvy vln / lidé potom“. Pro 17.–22. 10. má být rozdíl nejvýš ~1 člověk, pro 23. a
24. 10. mají zbýt jednotky. Když vlna projde (jiný prohlížeč na každou návštěvu a jiná síť), ukáže to tabulka a práh
`--min` se dá snížit. Pak totéž bez `--dry-run` a týdenní tabulka je přepočítaná.

**Výsledek na produkci (řídící session, 24. 10. 2026 10:40 UTC):** první verze (bez podmínky potvrzení) označila
nasucho 14 297 „návštěvníků vln“ (17. 10. 2 545 při 19 lidech), proto byla hodinu vypnutá; opravená verze dala
17.–22. 10. beze změny (19 / 69 / 19 / 38 / 16 / 33 lidí, 0 vln), 23. 10. 323 → 10 lidí (313 vln), 24. 10. 188 → 21
(167 vln); celkem 480 návštěvníků vln, všech 480 „jeden prohlížeč přes mnoho stránek“, 0 podle sítě. Pak naostro:
označeno 480, 0 odznačeno. Přehled za 7 dní potom: 215 lidí (týden předtím 212), vlny 480, nepotvrzených 60 917,
skenerů 1 969; zdroje direct 161, facebook 19, youtube 18; trychtýř 215 → 72 kalkulací → 16 nahrání → 1 zakázka.

**/login:** zobrazení stránky `/login` (GET) není pokus o přihlášení. Neúspěšná přihlášení se dosud nikam
nezapisovala, takže hádání hesel nešlo ověřit ani zpětně. Teď se každé zapíše do logu aplikace: `login failed` s IP
a 12 znaky otisku e‑mailu (ne e‑mail). Hádání ukáže
`grep "login failed" storage/logs/laravel.log | grep -o '"ip":"[^"]*"' | sort | uniq -c | sort -rn | head`.
`POST /login` už má limit 6 za minutu z jedné adresy. Kdyby log ukázal hádání, další krok je denní limit podle IP,
jako u registrace (`RegisterGuard`).
