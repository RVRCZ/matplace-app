# AF · Web očima návštěvníka – kolo 2: stránka nástroje, katalog a fotkové nástroje na telefonu (session 3, 17. 10. 2026)

Zadání: `docs/prompts/web-kolo2.md` (body 1, 6, 8 z `docs/Z.md` §4 a nálezy N9, N10, N14 – část nástroje, N15;
doplněk §7 od Romana „jde o uživatelský komfort“). Větev `feature/web-round2`, worktree `C:\matplace-edit-wt`.
Kalkulaci, cookie lištu, menu, blog a přesměrování dělala řídící session (commit d587877, v této větvi sloučeno).

## 1. Stránka nástroje na telefonu (N1, N14) – jedna šablona pro všechny nástroje

`resources/views/tools/page.blade.php` má nově tři části: **hlavičku** (`#tool-head`: zpět na Nástroje, `h1`,
věta `#tool-lead`, štítek „ověřeno tiskem“), **panel** s kroky (`#tool-panel`) a **jeviště** (`#tool-stage`:
prohlížeč, stavový řádek, karta akce).

- **Pod `lg` (telefon a tablet na výšku):** pořadí hlavička → prohlížeč → karta akce → kroky (CSS `order-1/2/3`).
  Prohlížeč má výšku 46 vh (388 px na iPhonu 390 × 844, dřív 55 vh), takže na první obrazovce je název, věta, model,
  rozměry i tlačítko Pokračovat (tabulka v §5).
- **Na `lg` a výš beze změny:** mřížka 340 px + jeviště, hlavička a panel vlevo, jeviště přes oba řádky vpravo
  (`lg:grid-rows-[auto_1fr]`, `lg:row-span-2`, sticky jako dřív). Snímky `*-1366-*` jsou totožné s produkcí.
  V DOM zůstává panel před jevištěm, takže klávesnice prochází formulář před tlačítky jako dřív.
- **Tlačítko „Pohled“** (rozhodnutí): pod `lg` je celá lišta prohlížeče (3D, Shora, Zepředu, Ze strany, Zespodu,
  pohledy nástroje jako Celek/Díl, Rozložit, Přizpůsobit, Rentgen, Podložka) schovaná pod jedním tlačítkem
  `#tool-view-toggle` vlevo nahoře. Klepnutí ji rozbalí pod sebou (`#tool-viewbar[data-open]`), druhé klepnutí ji
  zase sbalí; po výběru pohledu zůstává otevřená, aby šlo pohledy přepínat za sebou. Rozbalená lišta na telefonu
  ukazuje u Přizpůsobit/Rentgen/Podložka i slova (dřív jen ikony, které nikdo nečetl). Proč ne vodorovně rolovací
  lišta: osm tlačítek přes model by zůstalo, a to byl ten dojem „nástroj pro odborníky“ (N1).
- **Odstavec pod stavovým řádkem** (např. QR: „Kód má 33 × 33 polí…“, lampa: co potřebuje) je na telefonu skrytý;
  řádky s čísly (Vnitřní rozměry, buňka) zůstávají. Řeší to CSS nad značkami, které píše `param.ts`
  (`#param-dims > div.text-muted`), `param.ts` se kvůli tomu neměnil.
- **Karta akce na telefonu:** Stáhnout a Pokračovat se dělí o šířku karty, hlavní akce vpravo pod palcem. Jedna
  oranžová akce na obrazovce platí dál (test počítá `btn-primary` na každé stránce).
- **Mezery:** mřížka stránky má mezi hlavičkou, jevištěm a panelem 12 px (`gap-3`), stejně jako karty jeviště a
  kroky panelu (`mt-3`); dřív 16 px mezi jevištěm a panelem.
- **Fotkové nástroje** (reliéf a litofánie, busta, mazlíček) ukazují místo prázdného prohlížeče **ukázkový výtisk**
  (fotka karty z `public/img/tools/<nástroj>-800.*`, `tools.picture`) a větu „Nahrajte fotku, model uvidíte asi za
  minutu“ (reliéf) nebo „…za 1 až 3 minuty“ (busta, mazlíček). Nástroj to zapne parametrem `sample` šablony; první
  model ukázku skryje (`Stage.show`), tečkové logo při generování také. Papel portrét ukazuje od začátku ukázkový
  model (param), tam ukázka není potřeba.

## 2. Katalog `/tools` na telefonu (N6)

**Rozhodnutí: kompaktní řádky, ne dva sloupce.** Na 390 px by dva sloupce daly kartu 171 px širokou: fotka
114 px vysoká, název na 2–3 řádky a popis by se nevešel, takže by člověk vybíral jen podle fotky a krátkého názvu
(„SVG do STL“, „Díly podle barev z 3MF“ nic neřeknou bez věty). Řádek (fotka 96 px vlevo, vpravo název a nejvýš dva
řádky popisu, šipka) nese slova a na první obrazovku se vejde nadpis, hledání, chipy, první sekce a 4–6 nástrojů.

- **Sekce podle kategorie, sbalené s počtem** („Obrázky a loga · 28“), první otevřená. Nástroj je v každé
  kategorii, kterou má (stejně jako ho filtrují chipy), takže počet u sekce = počet, který ukáže chip. Hledání
  ukáže nástroj jen jednou (v první sekci, kde se najde), otevře sekce s výsledky a počty přepíše na nalezené; po
  smazání hledání se sekce vrátí, jak je měl návštěvník otevřené.
- **Chipy v jednom vodorovně rolovacím řádku** (dřív 3 řádky), hledání zůstává nahoře.
- **Popis na dva řádky**, ne jeden: na jeden řádek se při 390 px vešla tři slova („Z SVG, jednoduchého…“), což
  říká méně než nic. Dva řádky už nesou smysl věty a řádek je pořád nižší než fotka + název velké karty.
- **Na `lg` beze změny:** dvě vstupní karty (Mám soubor / Chci něco vytvořit) a sekce podle záměru s velkými
  kartami ve třech sloupcích. Telefonní seznam je samostatný blok `lg:hidden`, velké karty `hidden lg:block`
  (obrázky ve skrytém bloku se líně nenačítají). Hlavní stránka používá velkou kartu dál (`tools/card.blade.php`
  se neměnil); řádek je `tools/card_row.blade.php`.
- Výška `/tools` na telefonu: **23 656 px → 3 738 px** (z 27 obrazovek na 4,5, se zavřenými sekcemi).

## 3. Fotkové nástroje (N8, N9, N10)

- **Po dokončení modelu** na `< lg` stránka odskroluje na jeviště (model + rozměry + Pokračovat;
  `Stage.revealResult()`), na `lg` jako dřív na poslední krok panelu. Ověřeno lokálně na reliéfu
  (`relief-390-cs-done.webp`: po 40 s je nahoře model, řádek rozměrů a Pokračovat).
- **Po klepnutí na Vytvořit** (busta, mazlíček, reliéf) na `< lg` hned odskroluje na prohlížeč s tečkovým logem
  (`Stage.revealStage()`, `block: 'start'`), aby bylo vidět, že se něco děje (§7.3).
- **Busta neslibuje kalkulaci**: „Hotovo. Model je v náhledu, cenu ukáže tlačítko Pokračovat.“ (dřív „Hotovo,
  otevírám kalkulaci…“ a nic se neotevřelo).
- **Časy pravdivě:** reliéf „trvá to asi minutu“ (dřív „pár vteřin“, i v SEO popisu a návodu
  `tools_seo/relief.php`, a věta stránky „a hned uvidíte model“ → „asi za minutu“), busta „obvykle 1 až 3 minuty“
  (dřív „asi minutu“), čekání busty „1 až 3 minuty, můžete odejít a vrátit se odkazem“, karta busty v katalogu
  „za 1 až 3 minuty“.
- **Před nahráním nad tlačítkem:** busta a mazlíček mají nad tlačítkem souhlas (zaškrtávátko s červeným rámečkem
  zůstává), pod ním „Zdarma 1 model denně bez účtu, 3 s účtem. Model vzniká 1 až 3 minuty.“ (dřív malým písmem pod
  tlačítkem); reliéf „Zdarma a bez registrace. Model vzniká asi minutu.“
- **Po vyčerpání limitu** má hláška odkaz „Založit účet zdarma“ (jen nepřihlášenému; registrace se vrátí na stránku
  nástroje přes `url.intended`). Denní maximum serveru (`global_limit`) odkaz nemá, účet by nepomohl.
- **Velikost fotky v prohlížeči** (`resources/js/calc/photo.ts`): fotka nad 2 000 px na delší straně se před
  odesláním zmenší na canvasu (otočená podle EXIF, bílé pod průhlednou PNG, JPEG 92 %); malá a lehká fotka (do
  3 MB) jde beze změny. Teprve potom kontrola limitu a vlastní hláška „Fotka je větší než 15 MB, zmenšete ji
  prosím.“ (reliéf 15 MB, busta a mazlíček 12 MB – limit serveru `GenerationController`, neměnil jsem ho kvůli
  dodavateli generování). Limit je napsaný u pole („JPG, PNG nebo WebP do 15 MB. Velkou fotku z telefonu před
  odesláním zmenšíme.“). Co prohlížeč neumí nakreslit (HEIC ve Windows), jde, jak je, a rozhodne server.
- **Server mluví jazykem stránky:** `ReliefGenerator::messages()` a `GenerationController::photoMessages()` dávají
  validaci vlastní texty (`max`, `uploaded`, `mimes`/`image`), takže `422` nikdy nenese „The photo field must not be
  greater than 15360 kilobytes.“ `figure.ts` teď hlášku `422` ukáže (dřív ji schoval za „nepovedlo se“).

## 4. Prázdný povinný text (N15)

Když je povinný text (cedulka, jmenovka, QR…) prázdný, `param.ts` zavolá `stage.stale(true)` (jediný řádek v
`param.ts`): prohlížeč, stavový řádek a řádky rozměrů zešednou (`#tool-stage[data-stale]`, 35 % a odbarvení),
Stáhnout i Pokračovat jsou vypnuté a zůstává červená hláška „Napište text.“ Po napsání textu se vše vrátí.
Snímek `sign-390-cs-empty.webp` (zároveň ukazuje rozbalený „Pohled“).

## 5. Co je na první obrazovce – před (produkce 17. 10.) a po (větev, lokálně)

Měřeno skriptem `scripts/review_round2.mjs` (§8), cookie volba udělaná (cookie lištu řešila řídící session),
„vidět“ = celý prvek v okně 390 × 844 / 1366 × 768. Cena na stránce nástroje už od 16. 10. není (ukáže se až v
kalkulaci), proto sloupec „tlačítko“ = Pokračovat.

| stránka, 390 px | název před → po | věta před → po | Pokračovat před → po | prohlížeč před → po | snímky |
|---|---|---|---|---|---|
| `/tools/qr` cs, en | ne → **ano** | ne → **ano** | ano → ano | 464 → 388 px, 5 tlačítek + 3 ikony → 1 „Pohled“ | `before/qr-390-cs-1`, `qr-390-cs-1` |
| `/tools/sign` cs, en | ne → **ano** | ne → **ano** | ano → ano | 464 → 388 px | `sign-390-*` |
| `/tools/box` cs, en | ne → **ano** | ne → **ano** | ano → ano | 464 → 388 px | `box-390-*` |
| `/tools/figure` cs, en | ano (pod prázdným prohlížečem) → **ano, nahoře** | ne → **ano** | ano → ano | prázdný „Model a cena“ → **ukázka busty + „Přidat fotky“** | `figure-390-*` |
| `/tools/pet-figurine` cs | (nefoceno) → ano | → ano | → ano | → ukázka + „Přidat fotky“ | `pet-figurine-390-*` |
| `/tools/relief` cs, en | ano (pod prázdným prohlížečem) → **ano, nahoře** | ne → **ano** | ano → ano | prázdný → **ukázka litofánie** | `relief-390-*` |
| `/tools` cs, en | ano → ano | – | – | první nástroj: 0 → **6 řádků**; výška 23 656 → **3 738 px** | `tools-390-*` |

Na 1366 px: všechny stránky beze změny (název, věta, Pokračovat, 5 pohledů + 3 ikony vidět před i po; `/tools`
3 karty v řadě). U busty, mazlíčka a reliéfu je navíc ukázka místo prázdné plochy.

## 6. Doplněk §7 (Roman, 17. 10.): busta a mazlíček

1. **Fotky rovnou v prázdném prohlížeči** (`tools/_figure_drop.blade.php`, sekce `viewer-drop` šablony): přes ukázku
   výtisku velká plocha „Přidat fotky“ (klepnutí, přetažení). Klepnutí otevře **tentýž `input`** jako panel: nejdřív
   fotku zepředu, potom volné strany (zleva, zezadu, zprava); přetažení více fotek je rozdělí stejně. Vybrané fotky
   se v ploše ukážou jako náhledy s popiskem strany a nadpis se změní na „Přidat další stranu“; náhledy a tlačítka
   odebrání v panelu zůstávají a vše je propojené. Po vytvoření modelu plochu nahradí prohlížeč. Na telefonu
   `accept="image/*"` nabídne systém fotoaparát i galerii; `capture` jsem nepřidal, protože by na Androidu galerii
   zavřel (lidé mají fotku psa většinou už vyfocenou). Snímky `figure-390-cs-photo.webp`, `pet-figurine-390-cs-1.webp`.
2. **Mazlíček bez podstavce** (`none`): `PedestalChanger::PET_TYPES` (validace generování i výměny podstavce ho
   berou odtud), `pet_kind.stand` (spodek seříznutý rovinou o `min(1, max(0,3; 1,2 % velikosti))` mm, tedy nejvýš
   1 mm, jedno uzavřené těleso, `base_mm` = otisk tlapek), volba na stránce (podstavce teď 2 × 2), u ní varování
   „Tiskne se s podpěrami a snáz se převrhne.“, jméno a věnování se bez podstavce schovají; nápověda podstavce
   přepsaná (už neříká „stojí vždy na podstavci“). Miniatura zůstává jen na kulatém. Test
   `PetFigurineTest::test_without_a_base_the_figure_stands_on_its_own_paws_cut_flat` (vodotěsné, 1 těleso, 80 mm,
   řez ≤ 1 mm, všechny 4 chodidla v jedné rovině).
3. **Po „Vytvořit“ odskrolovat** na prohlížeč s tečkovým logem, po dokončení na prohlížeč a kartu akce (§3).

## 7. Testy

- `ToolPageTest` (nové): `test_on_a_phone_the_name_and_the_sentence_come_before_the_model_and_the_bar_folds`
  (h1 → věta → prohlížeč → karta akce v HTML, `order-1/2/3`, tlačítko Pohled), `test_a_photo_tool_shows_a_printed_
  piece_and_says_time_and_size_before_the_upload` (ukázka, věta o čase, limit u pole, „Přidat fotky“ a limit před
  tlačítkem u busty a mazlíčka, žádné „pár vteřin“ ani „otevírám kalkulaci“), `test_the_catalogue_on_a_phone_folds_
  the_categories_with_their_counts` (počet = řádky = nástroje s kategorií, první otevřená, chipy v řádku, lg 3
  sloupce), `test_a_photo_over_the_limit_is_refused_in_the_visitors_language` (422 nese „Fotka je větší než 15 MB…“,
  ne „must not be greater“/„kilobytes“; soubor, který není fotka).
- `PetFigurineTest`: nový test bez podstavce; `test_the_page_shows_…` teď čeká volbu `none` právě jednou.
- Výsledky běhu a `pint`, `tsc`, `build`: §9.

## 8. Skript na snímky

`scripts/review_round2.mjs` (v repu, bez balíčků: Chrome přes DevTools protokol a vestavěný WebSocket Node 22).
Na každou stránku čerstvý headless Chrome s prázdným profilem, iPhone 390 × 844 (2×, dotyk) nebo 1366 × 768,
`Accept-Language` podle jazyka, cookie `consent=a0m0` (`--bar` ji vynechá), po načtení čeká `--wait` ms, pak volitelný
skript `--then` (např. vložení fotky a klepnutí na Vytvořit) a znovu čeká. Zapíše webp první obrazovky
(`<stránka>-<šířka>-<jazyk>-<suffix>.webp`, `--full` i celou stránku) a do `facts.json` co je vidět. **User agent
obsahuje „HeadlessChrome“, takže naše statistiky běh počítají jako robota** (`App\Support\Bots`), ne jako lidi –
kolo 1 nafouklo návštěvy 13. 10.

    node scripts/review_round2.mjs --base=https://matplace.com --out=docs/img/web-round2/before
    node scripts/review_round2.mjs --base=http://127.0.0.1:8013 --pages=relief --langs=cs --widths=390 --suffix=done --wait=45000 --then="…"

Snímky: `docs/img/web-round2/before/` (produkce před změnou), `docs/img/web-round2/` (po), každý ≤ 90 kB.

## 9. Stav, co je neověřené, co zbývá

- Stav a čísla testů: viz zpráva řídící session a poslední commit větve.
- **Neověřeno:** skutečné generování busty a mazlíčka (lokálně bez klíče generátoru; plocha „Přidat fotky“,
  náhledy a zmenšení fotky ověřeny jen do odeslání), zmenšení skutečné 12 MB fotky z telefonu (HEIC/EXIF z iPhonu),
  tisk mazlíčka bez podstavce (řez je ověřený na tvaru stolu ze 4 nohou, ne na zvířeti), rychlost na mobilních
  datech. Na produkci po nasazení: `/tools/pet-figurine` s volbou Bez podstavce na jedné fotce a busta z telefonu.
- Papel a filament art nahrávají fotku přes `artwork.ts`/`param.ts` (cizí soubory): tam zmenšení před odesláním
  není, jejich limity a hlášky jsem neměnil.
- `param.blade.php`: tip pod kartou akce („Kratší odkaz znamená větší pole…“) zůstává i na telefonu, je až pod
  tlačítky, první obrazovku nezabírá.
