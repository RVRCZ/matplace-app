# Zadání pro session 1: blog sjednotit se současným webem (šest článků, odkazy, pokračování)

Z auditu SEO (ChatGPT pro Romana, 18. 10. 2026, ověřeno na produkci): starší články popisují původní tržiště
(nabídka do několika hodin, odkazy na `/tiskarny`, `/tiskar/poptat`, `/katalog`). Článek o ceně odkazuje na
`/katalog` a `/tiskarny`, příprava STL na `/katalog`, `/tiskar/poptat` a `/tiskarny` (`/tiskarny` dnes jde 302 na
legacy s noindex). Vzorem je nový článek „Jak vzniká busta z fotky“ (markdown, konkrétní postup, rozměry, čas, cena).

Články na produkci (`posts`, `App\Models\Post`, `body` + `format` html|markdown, bez editoru v adminu; import
z WordPressu dělal `app/Console/Commands/ImportBlog.php`):
`kolik-stoji-3d-tisk-na-zakazku-v-roce-2026`, `pla-vs-petg-vs-asa-jaky-material-vybrat`,
`jak-pripravit-stl-soubor-pro-3d-tisk`, `vytvoreni-stl-z-fotek-pomoci-ai-kompletni-navod-pro-3d-tisk-2026`,
`generovani-3d-modelu-pomoci-ai`, `printpal-io-a-ai-nastroje-pro-3d-tisk-navod` (vše html) a `jak-vznika-busta-z-fotky` (markdown).

## 0. Kde pracuješ a pravidla

- Větev **`feature/blog-refresh`** z aktuálního `main`, dokument **`docs/AK.md` §5**.
- Soubory: nová složka **`resources/blog/cs/<slug>.md`** (jeden článek = jeden soubor s hlavičkou: title, excerpt,
  cover, published_at, updated_at), nový příkaz **`php artisan blog:sync [--dry-run]`** (upsert podle slugu: body +
  format markdown + excerpt + title; nikdy nemaže; vypíše, co změnil), `app/Http/Controllers/BlogController.php`
  a `resources/views/blog/*` jen pro blok „Co dál“ (viz 2), `tests/Feature/Blog*Test.php`. Nesahat: nástroje,
  kalkulace, farma, videa.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, testy; nemergovat, nenasazovat; hotovo = push +
  zpráva řídící session (conceprt19092026-8c). Na produkci příkaz spustí řídící po nasazení.

## 1. Co s články

1. Stáhni si současná těla z produkce? Ne: řídící ti je vyexportuje do `resources/blog/cs/_import/<slug>.html`
   (řekni, až začínáš; zatím vezmi texty z `https://matplace.com/blog/<slug>` jako podklad).
2. Každý článek přepiš do markdownu tak, aby popisoval **dnešní web**: cena = kalkulace z modelu hned a objednávka
   u nás (odkaz na hlavní stránku, kde je pole pro soubor), příprava STL = kontrola a oprava modelu v kalkulaci
   (`/tools` nástroje „soubor dovnitř“: opravit, vydutit, rozdělit), fotka = busta `/tools/figure`, mazlíček
   `/tools/pet-figurine`, popis slovy = návrh (`/tools` nástroje z textu), materiály = naše druhy a barvy z farmy
   (PLA+, PETG, PLA Silk, matné, metalické, TPU, ASA, ABS, PC; svítící jen ručně). Vše, co už neplatí (nabídka do
   hodin, tiskaři, poptávka), pryč. Zachovej slug, název může zůstat; `published_at` nech, dej `updated_at`.
3. **Čísla**: ceny z produkce (busta 80 mm 99 Kč, 120 mm 130 Kč, 150 mm 200 Kč; další si spočítej na
   matplace.com s testovacím souborem, bez objednávky), doby tisku z kalkulace, doprava Zásilkovna (cena z webu).
4. **Blok „Co dál“** na konci každého článku: 1–3 odkazy na konkrétní nástroj nebo kalkulaci s větou, co tam
   člověk udělá (markdown stačí; kdyby bylo potřeba jednotné tlačítko, šablona blogu dostane komponentu).
5. Interní odkazy mezi články (cena ↔ materiály ↔ příprava STL ↔ busta), žádný odkaz na `/tiskarny`, `/tiskar/*`,
   `/katalog`, `legacy.` (test, že se v žádném článku nevyskytují).
6. Angličtina a španělština: zjisti, odkud bere sitemap-blog-en/es články (překlady v tabulce? stejné slugy?) a napiš
   do AK.md; překládej jen, když to blog dnes umí a dělá; jinak jen cs.

## 1a. Doplněk od Romana (18. 10. večer): články k našim nástrojům, prolinkované

Po přepsání šesti článků napiš **nové články k hlavním nástrojům**, každý jako návod s výsledkem a cenou, a všechny
články i stránky nástrojů **vzájemně prolinkuj** (článek → nástroj → článek, blok „Co dál“ na obou koncích; stránky
nástrojů dostanou odkaz na článek přes `config/tools.php` klíč `article` a session 3 ho ukáže v bloku „odkazy dál“,
domluv se s ní přes řídící). První pětice podle vstupních stránek: **cena 3D tisku z modelu** (kalkulace), **figurka
z fotky** (busta + mazlíček, s ukázkami rozměrů a cen), **QR cedulka a Wi‑Fi stojánek** (kavárna, ubytování, obchod),
**organizér na míru** (jak změřit prostor), **krabička na míru**. Pak další nástroje podle `config/tools.php`
(model z G‑kódu, mapa, reliéf z fotky, litofán, papel picado, forma na odlévání…), vždy jeden článek = jeden nástroj
nebo skupina. Fotky výtisků dodá Roman (zástupné místo v markdownu `![fotka výtisku](TODO)` a seznam potřebných
fotek do AK.md). Pořadí: nejdřív šest starých článků, pak pětice, pak zbytek po dávkách s push po každé dávce.

## 2. Testy a hotovo

`BlogSyncTest`: `--dry-run` nic nezapíše; sync založí/aktualizuje podle slugu; druhé spuštění beze změny; stará
adresa v markdownu = test neprojde (`/tiskarny`, `/tiskar/`, `/katalog`, `legacy.`); stránka článku má blok
„Co dál“ s odkazem na existující route. **Hotovo =** push `feature/blog-refresh`, AK.md §5 (co se v každém
článku změnilo, čísla a odkud jsou), zpráva řídící session.
