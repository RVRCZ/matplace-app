# Úprava po kroku F: bez osobního odběru, logo ze starého webu

Větev `feature/g-no-pickup-logo` (z `feature/f-admin`). Dvě věci, které Roman zadal po dokončení kroků A0 až F.

## Osobní odběr se nenabízí

Farma zatím nemá kde výtisky vydávat. Odběr jsem **vypnul, ne smazal**: až místo bude, zapne se jedním
zaškrtnutím v `/admin/farm/settings` („Osobní odběr“) a nic dalšího není potřeba.

- `config/farm.php`: výchozí `delivery_modes` je `['packeta_point', 'packeta_home']`.
- Migrace `2026_10_08_100000_no_pickup_in_person` vyřadí `pickup` z nastavení, které je už uložené v databázi
  (kdyby zbyl prázdný seznam, nastaví obě varianty Packety). Zakázky, které odběr už mají zvolený, si ho nechají
  a v adminu se dokončí jako dřív.
- Objednávka nabízí jen „Výdejní místo“ a „Doručení domů“; předvolené je výdejní místo. Nadpis sekce je
  „Doručení“ (bylo „Převzetí“).
- Server odběr odmítne, i kdyby ho stránka poslala (`quote` i `pay`, důvod `delivery`).
- **Výtisk, který se nevejde do balíku** (nejdelší strana nad 70 cm nebo součet stran nad 120 cm), teď nejde
  objednat vůbec. Zákazník u doručení vidí proč a co s tím („zmenšete ho, nebo ho rozdělte na díly“), platba je
  zablokovaná a server vrací důvod `delivery_too_big`. Dřív stránka v téhle situaci nabízela odběr.
- Hotový tisk, který odchází balíkem, už není „Hotovo, čeká na převzetí“, ale „Hotovo, chystáme k odeslání“
  (stav na stránce zakázky a v účtu) a e‑mail o dokončení říká, že ho zabalíme a předáme dopravci.
- Texty ve třech jazycích: úvod, O nás, Kontakt (sekce „Osobní odběr“ je pryč), Časté otázky, Obchodní podmínky
  a 17 vět na stránkách nástrojů už odběr neslibují. Obchodní podmínky a FAQ výslovně říkají, že osobní odběr
  nenabízíme.

Rozhodnutí:
1. Testy běží se zapnutým odběrem (`tests/TestCase.php`), protože většina testů objednávek platí odběrem (nechce
   adresu) a kód odběru má zůstat funkční. Výchozí stav webu hlídá
   `ShippingCurrencyTest::test_pickup_in_person_is_not_offered_unless_the_admin_switches_it_on`.
2. Po zapnutí odběru v adminu je potřeba vrátit zmínku o něm do `lang/*/pages.php` (kontakt, FAQ, obchodní
   podmínky). Texty se podle nastavení samy nemění, jsou to právní stránky.

Cestou opravená chyba z kroku D: e‑mail „zakázka je na cestě“ měl správný předmět, ale v těle text „předali nebo
odeslali“ (veřejná vlastnost `status` v mailu přebila hodnotu předanou šabloně). Teď má v těle text o dopravci
a číslo zásilky.

## Logo

Logo ze starého webu (zelený nápis Matplace s tryskou) je teď všude místo textového „matplace.“:

| kde | soubor |
|---|---|
| hlavička webu | `public/img/logo-header.webp` (starý `matplace_logo_01.webp`, 299 × 180) |
| e‑maily | `public/img/logo-email.png` (starý `logo_email.png`), šablona `resources/views/vendor/mail/html/header.blade.php` |
| obrázky pro sdílení (`/og/…`) | `public/img/logo.png` (v repu už byl, používají ho videa) |
| ikona v prohlížeči | `public/favicon.svg` ze starého webu, `favicon.ico` a `apple-touch-icon.png` ve stejné podobě (dosavadní `favicon.ico` byl prázdný soubor) |

Barvy webu jsem neměnil (tlačítka zůstala oranžová); logo je zelené jako na starém webu.

## Nasazení (Roman)

```
php artisan migrate --force
npm ci && npm run build
php artisan optimize
systemctl restart php8.2-fpm matplace-worker
rm -f storage/app/og/*.png        # nepovinné: obrázky pro sdílení se s logem nakreslí znovu samy, tohle jen uklidí staré
```

Žádné nové `.env` klíče. V `docs/E.md` mezi chybějícími údaji odpadá „místo a čas osobního odběru“.
