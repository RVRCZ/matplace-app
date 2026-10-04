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
