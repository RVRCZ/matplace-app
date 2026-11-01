# Ceny ve videích volitelně: přepínač v adminu (session 2)

Zadal Roman 1. 11. 2026 odpoledne: „nelíbí se mi ceny ve videích, co se ukazují na konci, můžeme je mít volitelné v adminu?“ Řídící doporučila jeden přepínač farmy a Short „Kolik stojí 3D tisk?“ při vypnutí nestavět; Roman: „zadej podle doporučení“. Navazuje na docs/AL.md §13 (videa farmy a YouTube, session 2).

## 1. Co je dnes

- **Short ke každému výtisku** (`app/Domain/YouTube/TimelapseCutter.php` řádky 185 a 218): závěrečná karta s vyfoceným kusem má řádek `FarmVideos::priceLine()` „Cena: 99 Kč“ (`youtube.video.price`); prázdný řádek se nekreslí (ukázkové kusy bez ceny). Komentář kanálu `FarmVideos::commentText()` (řádek 679) říká „:cta Cena tohoto výtisku: :price.“ (`youtube.video.comment`). Popis videa `youtube.video.description` (`lang/cs/youtube.php` řádek 38) říká „hotový kus a cenu vidíte na konci“ (`FarmVideos` řádek 860).
- **Short „Kolik stojí 3D tisk?“** (`SeriesCutter::priceShort`, příkaz `youtube:price-short`, spouští se ručně, v plánovači není): cena u každého kusu (řádek 69), v popisu řádky „:name – :price – :url“ (`priceTexts`, řádky 153 až 166), nápověda příkazu v /admin/youtube (`FarmVideos` řádek 391).
- **Týdenní sestřih a měsíční video** (`farm:monthly-video`, plánovač 1. den v měsíci 19:00): částky podle všeho neukazují (měsíční má krok „cena“ v postupu a čísla farmy). Ověř v kódu obou.
- **Přepínače farmy**: `App\Domain\Farm\FarmSettings` (`config/farm.php` klíč `settings`, výchozí hodnoty řádky 75 až 86, tabulka `farm_settings` přepisuje), stránka `/admin/farm/settings` (`resources/views/admin/farm/settings.blade.php`, zaškrtávátka řádky 45 až 69), uložení `FarmCatalogController::saveSettings` (řádky 466 až 505, boolean přes `$request->boolean(...)`).
- Video ve frontě má v /admin/youtube tlačítko **Nahradit** (`YouTubeController::replace` řádek 182, `FarmVideos::replace`): smaže starou verzi z YouTube a nahraje novou. Ověř, zda video staví znovu z časosběru, nebo jen nahrává hotový soubor.

## 2. Cíl

1. **Přepínač `video_prices`** (výchozí `true`) v `config/farm.php` `settings`, na stránce /admin/farm/settings jako „Ceny ve videích“ s krátkou nápovědou, co vypnutí znamená; uložení jako ostatní boolean.
2. **Vypnuto**:
   - `priceLine()` vrací prázdný řádek: karta Shortu bez ceny, nic jiného se na kartě nehýbe (ověř rozložení karty bez řádku, s řádkem je to dnešní stav);
   - `commentText()` bez ceny, jen výzva s odkazem;
   - popis Shortu bez zmínky o ceně (druhá varianta textu nebo doplňovaný kus věty; cs/en/es přes `lang/src/*.json` + `python scripts/lang_add.py …`);
   - Short „Kolik stojí 3D tisk?“ se nestaví: `youtube:price-short` skončí větou „Ceny ve videích jsou vypnuté (/admin/farm/settings)“, nic nefrontuje a v /admin/youtube to nápověda u seriálu řekne také;
   - týdenní a měsíční video beze změny, pokud částky neukazují; pokud ano, platí přepínač i tam.
3. **Platí pro videa postavená po přepnutí.** Videa už postavená ve frontě (čekají na schválení) ať jdou přestavět bez ceny: buď Nahradit video staví znovu z časosběru s aktuálním nastavením, nebo přidej u čekajícího videa tlačítko „Postavit znovu“. Napiš do AL.md, co Roman klikne, aby čekající videa přišla o ceny. Zveřejněná videa se nemění (komentář jde upravit ručně na YouTube).
4. **Zapnuto zpět**: vše jako dnes, bez přestavby.

## 3. Testy

- `YouTubeVideosTest` / `FarmVideosCutTest`: s vypnutým přepínačem je text karty prázdný, komentář bez ceny, popis bez „cenu vidíte na konci“; zapnuto jako dnes.
- `YouTubeSeriesTest`: `youtube:price-short` s vypnutým přepínačem nic nefrontuje a vypíše důvod.
- Stránka nastavení (vedle `FarmAdminNotifyTest`): přepínač se uloží a načte, výchozí zapnuto.
- `vendor/bin/pint --test`; tsc a build jen při změně TS.
- Ručně: jeden Short postavený lokálně s vypnutým přepínačem (ffmpeg je na PC, `winget Gyan.FFmpeg`, celá cesta v Bash) a snímek karty do `docs/img/short-no-price.png`.

## 4. Kde a jak

- Session 2, worktree `C:\matplace-gcode-wt`, větev `feature/video-prices-switch` z main (6a63c3f1 nebo novější). Soubory: `config/farm.php`, `resources/views/admin/farm/settings.blade.php`, `app/Http/Controllers/Admin/FarmCatalogController.php`, `app/Domain/YouTube/FarmVideos.php`, `SeriesCutter.php`, `TimelapseCutter.php` jen když karta potřebuje, `app/Console/Commands/YouTubePriceShort.php`, `lang/src/*.json`, `resources/views/admin/youtube/*.blade.php` kvůli nápovědě a tlačítku.
- Zápis: `docs/AL.md` §13, nový pododdíl „Ceny ve videích volitelně“. Texty jen přes lang soubory, `git add` jmenovitě, žádný stash. Nic nenasazuj. Po pushi zpráva řídící session: větev, commit, co je ověřené, snímek karty, co Roman klikne u čekajících videí.
