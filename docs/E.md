# Krok E: SEO a měření

Větev `feature/e-seo` (z `feature/d-shipping`). Stránky nástrojů mají obsah pro lidi i vyhledávače, web má
sitemapy, strukturovaná data a obrázky pro sdílení, staré adresy vedou tam, kam se jejich obsah přestěhoval,
blog a statické stránky jsou přenesené a měření třetích stran čeká na souhlas.

## Co vzniklo

**Sitemapy** (`App\Support\Sitemaps`, `php artisan matplace:sitemap`, denně 4:40): do `storage/app/sitemaps`
zapíše `sitemap.xml` (index) a `sitemap-tools-{cs,en,es}.xml` (úvod, nástroje, statické stránky, seznamy),
`sitemap-models-{locale}.xml`, `sitemap-catalog-{n}.xml` (po 5 000), `sitemap-designers-{locale}.xml`,
`sitemap-collections-{locale}.xml` (až budou stránky kolekcí, krok F), `sitemap-blog-{locale}.xml`. Stránka je
jen v jazycích, ve kterých existuje; u každé adresy jsou jazykové dvojníky (hreflang) a `lastmod`. Soubory se
vymění najednou. Web je servíruje na `/sitemap.xml` a `/sitemap-*.xml`; `/robots.txt` na ně odkazuje.

**Stránky nástrojů jako obsah**: 22 nástrojů × 3 jazyky. `config/tools.php` má u každého klíč `seo` (ukázky),
texty jsou v `lang/<jazyk>/tools_seo/<nástroj>.php`: `title`, `description`, `h1`, `intro` (2 odstavce),
`steps` (3 až 5), `faq` (4 až 6), `examples` (popisky). Formulář zůstal nahoře, obsah je pod ním
(`tools/_content.blade.php`), se strukturovanými daty `HowTo` a `FAQPage`. Stránka nástroje má z těchto textů
i `<title>`, popis a obrázek pro sdílení.

**Ukázky** (`php artisan matplace:tool-examples`): pro každý příklad z `config/tools.php` nástroj sám vygeneruje
model a `engines/python/render_tool.py` ho nakreslí (numpy, bez prohlížeče a GPU). 45 obrázků 800 × 600 je
v repu (`public/img/tool-examples/`, dohromady 0,9 MB).

**Strukturovaná data** (`App\Support\Schema`, komponenta `<x-jsonld :data="…" />`): úvod `Organization` +
`WebSite` se `SearchAction` na `/model?q=`, nástroj `HowTo` + `FAQPage`, model `Product` + `Person` +
`BreadcrumbList`, designér `Person`, inspirační model `CreativeWork` + `BreadcrumbList`, článek `Article` +
`BreadcrumbList`, blog `ItemList`, časté otázky `FAQPage`.

**Obrázky pro sdílení** `/og/{type}/{id}.png` a `/og/{en|es}/{type}/{id}.png` (`App\Support\OgImage`, GD,
1200 × 630, cache podle hash vstupu): `model` (cover, název, „Vytiskneme od X“, autor), `designer` (avatar +
3 covery), `tool` (fotka nástroje + název), `article` (cover + titulek), `site` (obecný). Každá stránka má
`og:image`, `og:title`, `og:description`, `og:type`, `og:url`, `twitter:card`.

**Staré adresy** (`config/legacy.php`, middleware `LegacyRedirects` před routováním):
- `/model/{slug}`, `/blog/{slug}`, `/faq`, `/cookies` běží tady se stejnou adresou, nepřesměrovávají se;
- `/katalog` → `/model`, `/kalkulator-ceny` → `/`, `/materialy` → `/materials`, `/o-nas` → `/about`,
  `/kontakt` → `/contact`, `/reklamace` → `/complaints`, `/podminky-pouzivani` → `/terms`,
  `/obchodni-podminky` → `/business-terms`, `/zasady-ochrany-osobnich-udaju` → `/privacy` (301);
- `/stahnout/{slug}`, `/koupit-model/{slug}` → `/model/{slug}` (301);
- `/tiskarny`, `/tiskar/*`, `/designer/*`, `/ucet/*`, `/moje-poptavka/*`, `/poptat*`, `/objednat*`, `/kosik`,
  `/pokladna` a další stránky účtů, plateb a souborů starého webu → `https://legacy.matplace.com{cesta}` (302).
- Připravené, **nenasazené**: `deploy/nginx/matplace.com.conf` a `deploy/nginx/legacy.matplace.com.conf`.

**Blog**: tabulka `posts`, `php artisan matplace:import-blog` (`blog_posts` starého webu, stejné slugy, jen
čeština, jen zveřejněné), `/blog` a `/blog/{slug}`. HTML starých článků se při importu čistí
(`App\Support\HtmlCleaner`: pryč styly, třídy, skripty, první `<h1>`; odkazy ven dostanou `nofollow`). Obrázky
z `/blog-img/` se kopírují do našeho storage (`blog/…`). Článek existuje jen v jazycích, ve kterých má text.
Nové články budou Markdown (editor v kroku F).

**Statické stránky** (`lang/<jazyk>/pages.php`, cs/en/es): `/about`, `/contact`, `/faq`, `/complaints`, `/terms`,
`/business-terms`, `/cookies`, a nová stránka `/materials` (materiály z kalkulačky a co je právě v tiskárnách).
Texty jsou přepsané ze starého webu pro farmu: bez tržiště tiskařů, s kreditem, Packetou a měnami.

**Měření a souhlas**: cookie lišta (cs/en/es), volba v cookie `consent` („a1m0“ = analytika ano, marketing ne,
180 dní), „Nastavení cookies“ v patičce ji znovu otevře. GA4 (`GA_MEASUREMENT_ID`) a Meta pixel
(`META_PIXEL_ID`) se načtou jen po souhlasu. Vlastní `events` běží vždy, bez cookies navíc. Zapisují se:
`upload`, `generate`, `calculation`, `download`, `order_created`, `order_paid`, `register`, `designer_enabled`,
`designer_file_uploaded`, `ref_visit`, `search`. Tytéž události dostane i prohlížeč (v layoutu, po
přesměrování ze session, u odpovědí na `fetch` v hlavičce `X-Matplace-Events`) a pošle je do GA4 / pixelu,
pokud to návštěvník povolil (`resources/js/site/measure.ts`).

**Ostatní**: `GOOGLE_SITE_VERIFICATION` do `<head>`; `noindex` na účtu, adminu, sdílených kalkulacích, nabídkách,
poptávkách a designérském přehledu, `X-Robots-Tag: noindex` na `/og/*` a sitemapách; `SEO_INDEXABLE=false`
vypne indexování celé kopie webu.

## Rozhodnutí nad rámec zadání

1. **„Vše ostatní → /“ jsem neudělal.** Neznámá adresa dál vrací poctivé 404 (s odkazy). Plošné přesměrování
   na úvod Google vyhodnocuje jako „soft 404“ a skrylo by skutečné chyby v odkazech. Přesměrované jsou všechny
   adresy, které starý web opravdu měl (podle jeho routeru).
2. **Na starý web vede víc cest, než zadání vyjmenovalo**: i `/platba*`, `/zaplatit*`, `/chat`, `/ohodnotit`,
   `/prihlaseni`, `/heslo-reset`, `/dekujeme`, `/overit-poptavku`, `/notifikace` a soubory (`/thumbnails`,
   `/stl`, `/blog-img`). Jsou to odkazy z e‑mailů starého webu k běžícím zakázkám; poslat je na úvod by je rozbilo.
3. **Nová stránka `/materials`.** Zadání na ni přesměrovává `/materialy`, ale v nové aplikaci nebyla.
4. **Příklady jsou u 15 parametrických nástrojů.** Kalkulačka, kontrola, oprava, forma, figurka, reliéf a dárky
   potřebují nahraný soubor, fotku nebo AI; ukázku pro ně skript poctivě nevygeneruje. Mají texty bez obrázků
   (a stávající produktovou fotku v OG obrázku).
5. **OG obrázky kreslí čisté GD**, ne Intervention Image (stejně jako v kroku B): žádná nová závislost.
6. **Události pro GA4 a pixel posílá prohlížeč, ne server.** Server jen řekne stránce, co zapsal. Bez souhlasu
   tak ze serveru k třetím stranám neodejde nic. Conversions API (server → Meta) přijde v kroku F.
7. **Hledaný výraz se do `events` neukládá** (jen počet výsledků). Přehled dotazů bez osobních údajů je krok F
   (`search_queries`).
8. **Obálky článků z cizích serverů se nepřebírají** (první článek má obálku na `images.openai.com`). Takový
   článek je bez obálky, dokud ji někdo nenahraje v adminu.
9. **`posts` má navíc `excerpt`, `format`, `author_name`.** Perex starých článků je potřeba pro výpis a popis;
   `format` rozlišuje převzaté HTML od Markdownu z adminu.
10. **nginx: obrázek, který není soubor, musí projít do aplikace.** Dnešní konfigurace bety
    (`deploy/nginx-matplace-app.conf`) vrací pro neexistující `.png` rovnou 404, takže `/og/….png` (už od kroku B)
    na serveru nefungují. Nová `matplace.com.conf` to má opravené; na betě stačí v bloku statických souborů
    změnit `try_files $uri =404;` na `try_files $uri /index.php?$query_string;`.
11. **Zásady ochrany údajů** jsem upravil ve třech bodech, aby neodporovaly liště: příjemci (Google Analytics,
    Meta, Packeta), smazání účtu (jde i v profilu), cookies.

## Co je potřeba od Romana

- **Právní texty** (`/business-terms`, `/terms`, `/complaints`, `/cookies`) jsou věcně přepsané pro farmu, ale
  nejsou to texty od právníka. K posouzení hlavně: kredit nejde vyplatit a při smazání účtu propadá; vrácení
  peněz po reklamaci jen do kreditu; výjimka z odstoupení do 14 dnů (§ 1837 písm. d) OZ) i pro modely
  z katalogu designérů; cookie `ref` vedená jako nezbytná; předzaškrtnutý souhlas s videem.
- **Chybějící údaje**: místo a čas osobního odběru, adresa pro reklamace, DIČ, způsob vystavení dokladu.
- **Obchodní podmínky opisují čísla z configu** (dopravné, limity dobití, 30 % strop odměny). Po změně
  v `config/farm.php` je potřeba upravit i `lang/*/pages.php`.
- **`farm.terms`** (sedm bodů u objednávky) se překrývá s obchodními podmínkami; sloučit, nebo z objednávky
  odkázat na obchodní podmínky.
- **Staré články mluví o tržišti** („poptejte tiskaře“, „zobrazit tiskárny“). Obsah je převzatý beze změny,
  jak chtělo zadání; upravit půjde v editoru (krok F).
- **Texty nástrojů** zkontrolovat; psané jsou podle kódu nástrojů, ne podle dojmu.

Chyby v nástrojích, které se našly při psaní textů (v kroku E neopravené, všech pět opravuje `docs/H.md`):
- QR kód: volba „otvor na zavěšení“ bez stojánku nic neudělá (`creative_kinds.py`, podmínka nikdy neplatí).
- Cedulka: popisek říká „druhý řádek (menší)“, ale oba řádky mají stejnou výšku písma.
- Světelná cedule: nápověda říká, že otvor pro kabel je v zadním krytu; je v těle.
- Stojánek na telefon: formulář dovolí úhel 35 až 80°, generátor ho potichu ořízne (podle stylu 45 až 70°).
- Krytka se závitem: chybová hláška tvrdí „jen kulatá“, generátor umí i hranatou a šestihrannou.

## Nové `.env` klíče

| klíč | výchozí | k čemu |
|---|---|---|
| `GA_MEASUREMENT_ID` | – | Google Analytics 4; bez něj se GA nenabízí ani nenačítá |
| `META_PIXEL_ID` | – | Meta pixel |
| `GOOGLE_SITE_VERIFICATION` | – | ověření Search Console (`<meta>` v hlavičce) |
| `SEO_INDEXABLE` | `true` | `false` = celý web `noindex` a `robots.txt` zakáže vše |
| `SEO_FACEBOOK_URL`, `SEO_INSTAGRAM_URL` | – | profily značky do strukturovaných dat |
| `LEGACY_HOST` | `https://legacy.matplace.com` | kam vedou účty a zakázky starého webu |
| `LEGACY_BLOG_IMAGES_DIR` | `/var/www/matplace/storage/blog-images` | odkud import kopíruje obrázky článků |

## Testy

`tests/Feature/SeoTest.php` (8 testů): každý nástroj má ve třech jazycích texty správného tvaru a obrázky
ukázek, stránka má obsah pod formulářem a hreflang; strukturovaná data jsou platný JSON na každém typu stránky
(a text nemůže ukončit `<script>`); sitemap obsahuje `/en/tools/sign`, neobsahuje `/es/blog/…` bez překladu ani
skryté modely, `robots.txt` na ni odkazuje, `SEO_INDEXABLE=false`; 21 starých adres včetně query a toho, co se
přesměrovat nesmí; události se zapisují bez souhlasu, GA skript je na stránce jen se souhlasem, událost po
přesměrování dorazí jednou; OG obrázky 1200 × 630 pro všechny typy; import blogu (dry‑run nic nezapíše, druhý běh
nic nezdvojí, HTML je vyčištěné, `/en/blog/…` bez překladu 404, koncept vidí jen admin); statické stránky
a materiály ve třech jazycích se stejnou strukturou.

## Nasazení (Roman)

```
php artisan migrate --force
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker

php artisan matplace:import-blog --dry-run     # vypíše, co by přenesl
php artisan matplace:import-blog               # 7 článků, obrázky do storage/app/public/blog
php artisan matplace:sitemap                   # první sestavení; dál denně ze scheduleru
```

- `.env`: `GA_MEASUREMENT_ID`, `META_PIXEL_ID`, `GOOGLE_SITE_VERIFICATION`. Dokud beta nemá být ve
  vyhledávačích, nastav `SEO_INDEXABLE=false`.
- nginx na betě: oprava z rozhodnutí 10 (jinak `/og/….png` vrací 404).
- Ukázky nástrojů jsou v repu; `php artisan matplace:tool-examples --force` je potřeba jen po změně geometrie
  nástroje nebo příkladu (chce Python s trimesh a manifold3d).
- **Přepnutí na matplace.com** (dělá Roman, soubory jsou připravené, nic není nasazené): postup je v hlavičce
  `deploy/nginx/matplace.com.conf`. Shrnutí: starý web na `legacy.matplace.com`, `APP_URL=https://matplace.com`,
  odstranit `MAIL_ALWAYS_TO`, `LEGACY_ASSETS_URL=https://legacy.matplace.com`, nové redirect URI pro Google,
  Facebook, YouTube a webhook Stripe, `php artisan optimize`, `php artisan matplace:sitemap`, sitemapu přidat
  do Search Console.
