# AK – Instruktážní video „Model z Printables → matplace → výtisk“ (sestřih z dat)

Session 1, větev `feature/howto-video` (z `origin/main` 00f87e8), 18. 10. 2026. Zadání:
`docs/prompts/video-sestrih-printables.md` (TODO #12), natočeno podle `docs/prompts/video-printables.md`.

## 1. Výstupy (`C:\Users\roman\Desktop\video\out\`)

| soubor | co | délka | velikost obrazu |
|---|---|---|---|
| `printables-howto.mp4` | hlavní video 16:9, 30 fps, AAC | 1:48 (s časosběrem, bez telefonu) | 1920 × 1080 |
| `printables-howto-short.mp4` | Short 9:16, 30 fps, AAC | 0:35 (s časosběrem, bez telefonu) | 1080 × 1920 |
| `popis.txt` | název a popis pro YouTube (cs): text, model a autor (CC0) s odkazem, `https://matplace.com/?…utm_campaign=video_howto`, kredit hudby | | |
| `titulky.cs.srt`, `titulky.en.srt` | titulky 16:9 (stejné jako vypálené v obraze) | | |
| `titulky-short.cs.srt`, `titulky-short.en.srt` | titulky Shortu | | |

Snímky z videa jsou v `docs/img/howto/`: úvod, Printables, cena, barvy, platba s rozmazaným telefonem, Short
a časosběr (`casosber.jpg`, `short-casosber.jpg`).

Verze 18. 10. 13:39 (s časosběrem F26-000085) nahradila ranní; ranní výstupy jsou v `out\old-1059\`.

**Pořadí 16:9**:
1. úvod 2,5 s: název cs/en a adresa,
2. Printables, výběr modelu, 2,5×,
3. stránka modelu „Cell Phone Stand od Pit Demon“,
4. soubory a licence CC0, klik na stažení,
5. nahrání na matplace,
6. kalkulace: cena a doba „2 h 1 min“, otočení modelu,
7. „Vytisknout u nás“,
8. zakázka s cenou 319 Kč,
9. barvy z cívek s kódy, zvolená měděná PLA Silk,
10. doprava Zásilkovna (3,5 s načítání okna je vystřižených),
11. podmínky a zaplacení,
12. „ve frontě“,
13. „tiskne se“ s prvním snímkem z kamery,
14. časosběr F26-000085 (U #1, PLA Silk měděná, dotištěno 11:32 UTC), 30 s stlačeno na 20 s, titulek „Časosběr tisku“,
15. záběr telefonem (až bude; teď vynechaný bez prázdného místa),
16. závěr s logem a matplace.com 3 s.

Průchod je zrychlený 1,5 až 3×; delší statické části (fronta 2:40–3:36, konec záznamu) jsou vystřižené. Titulky
cs/en jsou u každého kroku 2,8 až 4 s, na tmavém pruhu dole. Nahoře jsou tam, kde jsou dole tlačítka: Vytisknout,
doprava, platba.

**Short**: úvod 1,5 s → nahrání → cena → barvy → časosběr (čtvercový `short.mp4` 15 s, titulek „Tiskneme na farmě“) → telefon (až bude)
→ závěr 2 s.
- Výřez stránky 16:9 běží přes střed a za ním je tentýž obraz rozmazaný.
- Titulky jsou větší (62/46 px) a zalamují se.

**Obraz**: záznam neměl F11, proto se ořezává na obsah okna (x 42, y 108, 1878 × 947 px). Zmizely tím záložky,
adresní řádek s odkazem na zakázku a boční lišta Firefoxu. Obsah je vložený do 1080p s tmavými pruhy nahoře a dole
(zvětšení 1,02×).

**Osobní údaje**:
- Telefonní číslo u doručení je rozmazané ve všech 6 úsecích (2:15,5–2:21,3 a 2:30–2:36,6 záznamu), včetně
  rolování. Ověřeno na snímcích po 0,1 až 0,17 s.
- Jméno (přejmenované), výdejní místo Zásilkovny a kredit zůstaly; Roman 18. 10. řekl, že můžou být vidět.
- E-mail ani administrace v záznamu nejsou.

**Hudba**:
- Skladba je `donnysombrero-up-in-the-air-499093.mp3` (Pixabay), napevno ve specifikaci, aby se kredit v popisu
  shodoval se zvukem, ať je v knihovně skladeb kolik chce.
- Hlasitost 0,6, náběh 0,5 s, zeslabení 1,5 s, kratší skladba se opakuje.
- Lokálně skladba pochází z Romanových Stažených, na serveru z `YOUTUBE_MUSIC_DIR`.

## 2. Jak to funguje (další videa = nový JSON)

- **`scripts/video/howto.json`** popisuje celé video:
  - zdroje (cesta a ořez),
  - rozmazané obdélníky (zdroj, od–do v sekundách záznamu, `box` v pixelech záznamu),
  - úseky pro `wide` a `short`: zdroj, `from`/`to` (záporné `to` = od konce), `speed`, nebo `fit` (cílová délka),
    titulek cs/en, `caption_at`, `caption_seconds`, `short_crop` [střed x, střed y, šířka], `missing` (karta
    místo chybějícího zdroje),
  - karty `intro` a `end`, název a popis pro YouTube, model, hudbu, `site`.
- **`php artisan farm:howto-video scripts/video/howto.json`**:
  - postaví 16:9, Short, `popis.txt` a SRT,
  - `--dry-run` vypíše délky a co chybí,
  - `--out=` určí jinou složku,
  - `--queue` dá obě videa do `/admin/youtube` (druh **`howto`**, `meta` = název + varianta `wide`/`short`,
    soubor zkopírovaný na farm disk do `howto/`),
  - `--skip-build` zařadí už postavené soubory ze složky (na serveru, kde záznam není).
- **Kód**:
  - `App\Domain\YouTube\HowtoCutter` plánuje `VideoCut`. Kreslí ho stejné ffmpeg filtry jako `TimelapseCutter`,
    z něj se berou `render`, `withMusic`, písmo a cesty.
  - Dlouhý filtr (víc než 6000 znaků) jde přes soubor `-/filter_complex`, protože Windows `cmd.exe` uřízne příkaz
    nad 8191 znaků; potřebuje ffmpeg 7+. Krátké filtry časosběrů na serveru jdou dál přímo.
  - Rozmazání má poloměr podle velikosti obdélníku (ffmpeg jinak odmítne malé boxy).
  - **Týká se i časosběrů farmy**: každé video dostane plánovanou délku jako `-t`. ffmpeg 9 drží poslední snímek
    seznamu o jeden záznam déle než 7.1, takže Short bez fotky vycházel o 2 s delší. Testy prošly s ffmpeg 7.1 i 9.0.2.
- **Schvalování**:
  - `FarmVideos::queueHowto`. `consented` je u howto vždy ano (naše vlastní video), `allowed` hlídá soubor.
  - V `/admin/youtube` je dvojice vedle sebe („Instruktážní video · 16:9“ a „· Short 9:16“), každé s vlastním
    tlačítkem. Facebook a Instagram se nabízejí jen u Shortu.
  - `VideoSharer` sdílí Shorty zakázek i Short instruktážního videa, `SocialVideoController` už nevyžaduje zakázku.
- **Testy**: `HowtoVideoTest`:
  - plán: ořez, rychlost, rozmazání v čase úseku, titulky a SRT, hudba, Short, zalamování, popis,
  - příkaz s `--queue`: soubory, dva řádky `howto`, administrace,
  - na FB/IG jde jen Short,
  - skutečný render ze dvou syntetických klipů: 16:9 i 9:16, délka, AAC.

## 3. Co chybí a jak to doplnit

1. **Časosběr F26-000085**: hotovo 18. 10. Ze `F26-000085\` se bere `wide.mp4` (1920 × 1080, 34,5 s) od 1,5 s
   do konce −3,1 s: střih na kartu s logem je v 31,533 s, −3 by trefilo přesně tu hranici, proto o desetinu dřív.
   `short.mp4` (720 × 720) od 0,5 s (první snímek je hotový výrobek) do konce −0,5 s. `timelapse.mp4`
   (1280 × 720) se nepoužívá, `wide.mp4` má plné rozlišení.
2. **Záběr telefonem** `C:\Users\roman\Desktop\video\vec-1.mp4`: přidá se sám (16:9 na 12 s, Short 6 s), titulek
   „Hotovo“.
3. **Stránka zakázky „hotovo“** s přehraným časosběrem (krok 5 natáčecího návodu) v záznamu není; video končí
   časosběrem a záběrem telefonu.
4. Po doplnění telefonu: `php artisan farm:howto-video scripts/video/howto.json`. Lokálně jsou potřeba `FFMPEG_BIN`
   (winget ffmpeg 9.0.2) a `YOUTUBE_MUSIC_DIR` se zvolenou skladbou. Pak:
   - zkopírovat `out\*.mp4` na server,
   - spustit `php artisan farm:howto-video scripts/video/howto.json --out=<složka> --skip-build --queue`,
   - Roman schválí v `/admin/youtube`.
5. Celá sada 689 testů OK (s ffmpeg 9.0.2), `pint`, `tsc`, `npm run build` čisté.
6. Titulky SRT se na YouTube nahrávají ručně ve Studiu (API je neposílá); vypálené v obraze jsou tak jako tak.
