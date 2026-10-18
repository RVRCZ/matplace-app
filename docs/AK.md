# AK – Instruktážní video „Model z Printables → matplace → výtisk“ (sestřih z dat)

Session 1, větev `feature/howto-video` (z `origin/main` 00f87e8), 18. 10. 2026. Zadání:
`docs/prompts/video-sestrih-printables.md` (TODO #12), natočeno podle `docs/prompts/video-printables.md`.

## 1. Výstupy (`C:\Users\roman\Desktop\video\out\`)

| soubor | co | délka | velikost obrazu |
|---|---|---|---|
| `printables-howto.mp4` | hlavní video 16:9, 30 fps, AAC | 1:53 | 1920 × 1080 |
| `printables-howto-short.mp4` | Short 9:16, 30 fps, AAC | 0:40 | 1080 × 1920 |
| `popis.txt` | název a popis pro YouTube (cs): text, model a autor (CC0) s odkazem, `https://matplace.com/?…utm_campaign=video_howto`, kredit hudby | | |
| `titulky.cs.srt`, `titulky.en.srt` | titulky 16:9 (stejné jako vypálené v obraze) | | |
| `titulky-short.cs.srt`, `titulky-short.en.srt` | titulky Shortu | | |

Snímky z videa jsou v `docs/img/howto/`: úvod, Printables, cena, barvy, platba s rozmazaným telefonem, Short
a časosběr (`casosber.jpg`, `short-casosber.jpg`), hotový výrobek (`hotovo.jpg`).

Verze 18. 10. 17:05 má časosběr F26-000085 i záběr hotového výrobku (`vec-1.mp4`). Předchozí verze jsou v `out\old-1059\`
(ranní, s kartou místo časosběru) a `out\old-1339\` (s časosběrem, bez záběru výrobku).

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
15. záběr hotového stojánku s telefonem (`vec-1.mp4`, 3,6 s při 60 fps zpomalené na 0,75×, tedy 4,8 s), titulek „Hotovo“,
16. závěr s logem a matplace.com 3 s.

Průchod je zrychlený 1,5 až 3×; delší statické části (fronta 2:40–3:36, konec záznamu) jsou vystřižené. Titulky
cs/en jsou u každého kroku 2,8 až 4 s, na tmavém pruhu dole. Nahoře jsou tam, kde jsou dole tlačítka: Vytisknout,
doprava, platba.

**Short**: úvod 1,5 s → nahrání → cena → barvy → časosběr (čtvercový `short.mp4` 15 s, titulek „Tiskneme na farmě“) → hotový výrobek (0,75×, okno na stojánek `short_crop` [1050, 540, 1200])
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
2. **Záběr výrobku** `vec-1.mp4`: hotovo 18. 10. Má jen 3,6 s, `fit` umí jen zrychlit, proto `speed: 0.75`
   (zdroj má 60 fps, zpomalení je plynulé).
3. **Stránka zakázky „hotovo“** s přehraným časosběrem (krok 5 natáčecího návodu) v záznamu není; video končí
   časosběrem a záběrem telefonu.
4. Postavení znovu: `php artisan farm:howto-video scripts/video/howto.json`. Lokálně jsou potřeba `FFMPEG_BIN`
   (winget ffmpeg 9.0.2) a `YOUTUBE_MUSIC_DIR` se zvolenou skladbou. Pak:
   - zkopírovat `out\*.mp4` na server,
   - spustit `php artisan farm:howto-video scripts/video/howto.json --out=<složka> --skip-build --queue`,
   - Roman schválí v `/admin/youtube`.
5. Celá sada 689 testů OK (s ffmpeg 9.0.2), `pint`, `tsc`, `npm run build` čisté.
6. Titulky SRT se na YouTube nahrávají ručně ve Studiu (API je neposílá); vypálené v obraze jsou tak jako tak.

## 4. Kam vedou odkazy z videí (TODO #21, větev `feature/video-links`)

Zadání: `docs/prompts/video-odkazy.md`. Roman se 18. 10. při schvalování videí F26‑000085 ptal, kam vede odkaz.

**Dřív**:
- Popis na YouTube vedl vždy na hlavní stránku (`link(null, …)`), i když koncová karta ukazovala adresu nástroje.
- Facebook a Instagram posílaly na hlavní stránku s `utm_campaign=video`, bez rozlišení druhu.
- Odkaz byl až v pátém odstavci popisu, takže ho bez rozkliknutí „více“ nikdo neviděl.

### 4.1 Jedno místo pravdy

`FarmVideos::link($order, $kind, $source = 'youtube', $target = null)` určuje odkaz pro všechny kanály:
- **cíl**: stránka nástroje, ve kterém model vznikl (`origin tool`, `lithophane` i `relief` → `/tools/relief`,
  `generated` → `/tools/figure`). Jinak hlavní stránka, která je zároveň kalkulací s polem pro soubor.
  `$target` cíl přepíše (instruktážní video).
- **utm**: `utm_source` = kanál (`youtube`, `facebook`, `instagram`), `utm_medium=social`,
  `utm_campaign=video_<druh>`.

Pomocné metody:
- `FarmVideos::linkOf($video, $source)` vybere odkaz pro řádek videa. Týdenní sestřih → hlavní stránka,
  instruktážní video → `meta.link`.
- `FarmVideos::forChannel($text, $source)` přepíše v textu z YouTube `utm_source=youtube` na kanál. Stránka zůstane
  stejná, změní se jen značka.
- Koncová karta (`address()`) bere stejný `toolUrl()`, takže adresa na kartě sedí s odkazem v popisu (ověřeno testem).

| druh | kanál | cíl | utm_source | utm_campaign |
|---|---|---|---|---|
| Short tisku | YouTube (popis, komentář) | nástroj, jinak hlavní stránka | youtube | video_short |
| Short tisku | Facebook video, Facebook příspěvek s odkazem | totéž | facebook | video_short |
| Short tisku | Instagram Reel (text, odkaz v něm není klikací) | totéž | instagram | video_short |
| 16:9 tisku | YouTube | nástroj, jinak hlavní stránka | youtube | video_wide |
| týdenní sestřih | YouTube, první řádek popisu | hlavní stránka | youtube | video_weekly |
| týdenní sestřih | YouTube, kapitoly | nástroj každého kusu, jinak hlavní stránka | youtube | video_weekly |
| instruktážní 16:9 | YouTube | `site` + `link` ze specifikace (dnes `https://matplace.com/`) | youtube | video_howto |
| instruktážní Short | YouTube, Facebook, Instagram | totéž | youtube / facebook / instagram | video_howto |

Facebook a Instagram dostávají jen Shorty: 16:9 a týdenní sestřih zůstávají na YouTube, jako dosud.

V `/admin/stats` (zdroj × kampaň) se tak návštěvy dají rozdělit podle kanálu i druhu videa. Starší facebookové
příspěvky mají `utm_campaign=video`.

### 4.2 Odkaz v prvních dvou řádcích popisu

YouTube bez rozkliknutí ukáže první dva řádky popisu, Shorts ještě méně. Popis všech druhů proto začíná výzvou
s odkazem (`FarmVideos::callLine`, klíče `youtube.video.cta` a `cta_tool`):
- hlavní stránka: „Nahrajte model a hned uvidíte cenu: https://matplace.com/?…“,
- nástroj: „Udělejte si vlastní: https://matplace.com/tools/…?…“.

Pořadí popisu tisku: výzva s odkazem → věta o videu → materiál, tiskárna, doba tisku → „Video zveřejňujeme se
souhlasem zákazníka.“ → kredit hudby → hashtagy.

Ostatní druhy:
- týdenní sestřih: výzva → věta o týdnu → souhlas → kapitoly → hudba → hashtagy,
- instruktážní video: výzva → text ze specifikace → kredit modelu → hudba → hashtagy (`popis.txt`).

Texty jsou ve třech jazycích (cs, en, es v `lang/*/youtube.php`).

Instruktážní video:
- Cíl je ve specifikaci: nový klíč `"link": "/"` v `scripts/video/howto.json` (cesta na webu `site`).
- `HowtoCutter::target()` ho skládá.
- `farm:howto-video --queue` ho uloží do `meta.link` řádku, podle něj pak vede i odkaz na Facebooku.

**Rozpracované řádky**:
- Videa už čekající ve frontě (třeba F26‑000085) mají popis z doby zařazení, starý.
- Nový popis dostane jen nově zařazené video. U čekajícího stačí v `/admin/youtube` přepsat první řádek, nebo řádek
  zamítnout a nechat zařadit znovu.
- Facebook a Instagram berou odkaz z nového místa i u starých řádků (přepíše se jen `utm_source`).

### 4.3 Připnutý komentář s odkazem (připravený, vypnutý)

Co jde přes API (YouTube Data API v3, ověřeno v dokumentaci 18. 10. 2026):
- **Vložit komentář** jde: `commentThreads.insert` (`part=snippet`, povinné `snippet.channelId`, `snippet.videoId`,
  `snippet.topLevelComment.snippet.textOriginal`). Stojí 50 jednotek kvóty.
- **Připnout komentář přes API nejde.** `comments.setModerationStatus` zná jen `heldForReview`, `published`
  a `rejected`, a zdroj `commentThread` nemá žádné pole pro připnutí. Připnout se dá jen ručně ve Studiu (u komentáře
  ⋮ → Připnout), jedno kliknutí na video.
- **Rozsah**: `commentThreads.insert` přijímá jen `https://www.googleapis.com/auth/youtube.force-ssl`. Dnešní token
  má `https://www.googleapis.com/auth/youtube` (`YouTubeClient::SCOPE`), a ten podle dokumentace nestačí.

Co je v kódu, všechno za přepínačem `YOUTUBE_PIN_COMMENT=false` (výchozí; `config/youtube.php` `pin_comment`,
`.env.example`):
- **Rozsah při připojení**: `YouTubeClient::scopes()` při zapnutém přepínači žádá při připojení kanálu i
  `youtube.force-ssl`. Vypnutý přepínač nechává připojení beze změny.
- **Vložení komentáře**: `YouTubeClient::comment($videoId, $text)` volá `commentThreads.insert` a vrací id vlákna.
- **Kdy se komentář píše**: `FarmVideos::commentLink($video)` napíše výzvu s odkazem (tentýž první řádek jako
  v popisu) jednou, když je video veřejné:
  - po `publish()` s výsledkem „public“,
  - nebo když ho `refreshStats()` najde veřejné (zveřejněné ve Studiu nebo plánovačem YouTube).
- **Proč až u veřejného**: soukromé a naplánované video komentáře nemá.
- **Opakování**: id se zapíše do `meta.comment_id`, druhý komentář se nepíše.
- **Selhání**: token bez rozsahu (`insufficientPermissions`) nebo kvóta se zapíše do logu, video zůstane, jak je.

Skutečné volání neproběhlo, testy mají Google podvržený. Kroky k zapnutí, až to Roman schválí:
1. Na serveru `YOUTUBE_PIN_COMMENT=true` a `php artisan config:cache`.
2. Ověřit, že projekt `matplace-youtube` v Google Cloud má rozsah `youtube.force-ssl` na obrazovce souhlasu
   (OAuth consent screen → Data access). Pokud ne, přidat ho. Citlivý rozsah u neověřené aplikace znamená varování
   při souhlasu, stejně jako dnes.
3. Roman v `/admin/youtube` kanál odpojí a znovu připojí, Google se zeptá na nový souhlas
   s rozšířeným rozsahem.
4. Od té chvíle dostane každé nově zveřejněné video komentář. Připnout ho Roman musí ve Studiu.
5. Pro už zveřejněná videa se dá komentář dopsat ručně, `commentLink()` je veřejná metoda (tinker).

## 5. Blog podle dnešního webu (TODO #24, větev `feature/blog-refresh`)

Zadání: `docs/prompts/blog-sjednoceni.md`. Šest článků z WordPressu popisovalo původní tržiště a odkazovalo na
`/tiskarny`, `/tiskar/poptat` a `/katalog`. Psaly o nabídkách do několika hodin, tiskařích z okolí a poptávce. Nově
popisují dnešní web: kalkulaci z modelu, tisk na naší farmě, nástroje a naše materiály. Podklad byla těla
z produkce (`resources/blog/cs/_import/`, export fa423a8) a veřejné stránky článků.

### 5.1 Soubory a příkaz

**Články**:
- Každý článek je v souboru `resources/blog/cs/<slug>.md`: hlavička mezi řádky `---` (`title`, `excerpt`,
  `cover`, `published_at`, `updated_at`, časy v UTC jako v databázi), pak tělo v Markdownu.
- Slugy jsou zachované. Složka `_import/` je jen export a příkaz ji nečte.

**`php artisan blog:sync [--dry-run]`** (`app/Console/Commands/BlogSync.php`):
- **Párování**: podle slugu, chybějící článek založí.
- **Co zapíše**:
  - český `title`, `excerpt` a `body`, `format` = markdown,
  - `cover_path`, jen když ho hlavička uvádí (prázdný `cover` nechá obálku z databáze),
  - `updated_at` z hlavičky. Používá se jako `dateModified` v JSON-LD a jako `lastmod` v sitemap.
- **Datum zveřejnění**: `published_at` z hlavičky dostane jen nově založený článek. U existujícího zůstane to
  z databáze, sync tedy nikdy nezveřejní ani neschová článek, který už je v databázi.
- **Co nikdy nedělá**:
  - nic nemaže,
  - nesahá na ostatní jazyky,
  - přeskočí s varováním článek, který má jiný jazyk v HTML: `format` je jeden na článek a Markdown by takový
    překlad rozbil.
- **Výpis**: u každého článku napíše, co by změnil (`--dry-run`), nebo co změnil. Druhé spuštění vypíše
  „Changed 0“.
- **Po nasazení**: `blog:sync --dry-run`, pak `blog:sync`. Na produkci by měl ohlásit 5× update (title,
  excerpt, body, format, u tří i cover) a printpal jako update bez data zveřejnění.

**Testy** (`tests/Feature/BlogSyncTest.php`, 7 testů):
- `--dry-run` nic nezapíše,
- založení a aktualizace podle slugu, druhé spuštění beze změny,
- článek s HTML překladem přeskočí,
- ostatní jazyky Markdown článku zůstanou,
- v žádném článku nejsou staré adresy (`/tiskarny`, `/tiskar/`, `/katalog`, `legacy.`) ani slova starého
  tržiště (poptávka, tiskař, do 24 hodin),
- každý článek končí oddílem „Co dál“ s 1–3 odkazy,
- každý interní odkaz vede na existující route nebo článek,
- stránka článku blok „Co dál“ ukáže a koncept (printpal) je 404.

### 5.2 Co se v článcích změnilo

Všechny články mají:
- blok **„Co dál“** na konci, Markdown nadpis `## Co dál` s jedním až třemi odkazy na nástroj nebo kalkulaci
  a větou, co tam člověk udělá. Šablona blogu se neměnila, pod článkem dál zůstává její obecná výzva,
- odkazy mezi sebou: cena ↔ materiály ↔ příprava STL ↔ busta ↔ AI články,
- `updated_at` 18. 10. 2026 a oslovení „vy“.

| článek | dřív | teď |
|---|---|---|
| Kolik stojí 3D tisk na zakázku | orientační tabulka 50–5000 Kč, „cenu do pár hodin“, odkazy na katalog a tiskárny | postup v kalkulaci; skutečné ceny 5 modelů a bust; z čeho se cena skládá (gramy, hodiny, příprava, minimum 99 Kč, zaokrouhlení); příplatky materiálů; doprava Zásilkovnou; jak ušetřit (měřítko, vydutí, víc kusů); FAQ |
| PLA vs. PETG vs. ASA | tři materiály, „naši tiskaři“, asistentka Matty, platba při převzetí | PLA+, PETG, ASA, TPU s údaji ze stránky `/materials` (teplota, venku, potraviny, cena proti PLA+); provedení PLA z farmy (matné, Silk, metalické, třpytivé, svítící); doba tisku jednoho modelu v různých materiálech; nylon a pryskyřice jen odhad |
| Jak připravit STL | export z CAD (zachován), opravy v cizích programech, „poptejte přípravu u tiskárny“ | export z CAD zůstal; kontrola a oprava v `/tools/check` a `/tools/repair`; oprava v kalkulaci; rozdělení, životní velikost, vydutí; slicer řeší kalkulace (0,2 mm, 15 %); 3MF na barvy, model z G‑kódu |
| Vytvoření STL z fotek | fotogrammetrie a cizí aplikace s cenami | dvě cesty: AI z jedné fotky (busta, mazlíček, litofanie, hrubý model na hlavní stránce) a fotogrammetrie (cizí aplikace bez cen); jak fotit pro obě; z modelu výtisk |
| Generování 3D modelů pomocí AI | propagace STLBuddy (čísla, tarify), „marketplace tiskařů, nabídky do pár hodin“ | jak generování funguje a co od něj čekat; AI na matplace (hlavní stránka, busta, mazlíček); kdy AI nepotřebujete (parametrické nástroje); postup a ceny; co AI neumí (náhradní díl) |
| PrintPal.io a 4 další AI nástroje (koncept) | „síť 100+ tiskáren, 5–15 nabídek do 24 h“, odkaz `/poptat` | pět cizích nástrojů bez čísel, která nejdou ověřit; tabulka „stejná úloha na matplace.com“; příprava; tisk u nás s cenou |

**Rozhodnutí pro Romana**:
- **printpal-io-…** byl na produkci nezveřejněný (`published_at` prázdné). Přepsaný je, ale zveřejněný zůstane jen
  koncept. Zveřejnit se dá nastavením `published_at` v databázi.
- **Generování 3D modelů pomocí AI** dřív propagovalo STLBuddy, tedy nástroje, které dnes máme sami. Propagace
  je pryč a článek vede na naše nástroje. Odkaz na stlbuddy.com v článku není.
- U cizích nástrojů (Meshy, Polycam, PrintPal…) jsem vynechal ceny, počty kreditů a statistiky. Nejdou ověřit
  a rychle stárnou.
- „Jak vzniká busta z fotky“ se nepřepisovala. Je v databázi ve třech jazycích (zdroj `docs/blog/`) a sync na ni
  nesahá. Odkazy na ni z ostatních článků test hlídá jako na známý článek.

### 5.3 Čísla a odkud jsou

**Ceny tisku** jsou z produkční kalkulace, 18. 10. 2026 kolem 17:20 UTC, materiál PLA+, standard (vrstva 0,2 mm,
výplň 15 %). Testovací soubory šly do kalkulace na matplace.com bez objednávky: 6 nahrání (4 různé soubory) a 15 výpočtů,
User-Agent obsahuje `matplace-session1-blog`.

| model | rozměry | g | doba | cena | rozpad (materiál + čas za kus, příprava) |
|---|---|---|---|---|---|
| kostka (vygenerovaná) | 20 mm | 3,6 | 15 min | 99 Kč | 4,67 + 6,67, příprava 30 → 41,33 → minimum 99 |
| kostka | 40 mm | 19,9 | 33 min | 99 Kč | 70,44 → 99 |
| stojánek na telefon (3MF z Romanových Stažených) | 87 × 91 × 75 mm | 32,9 | 1 h 38 min | 120 Kč | 116,47 |
| plný váleček | ⌀ 60 × 100 mm | 74,4 | 1 h 27 min | 170 Kč | 165,47 |
| tentýž, měřítko 2 | ⌀ 120 × 200 mm | 503,4 | 8 h 14 min | 910 Kč | 654,36 + 220,42 + 30 = 904,78 |
| 10 × kostka 20 mm | | | | 150 Kč | 10 × 11,34 + 30 = 143,4 |

**Z rozpadu plyne**:
- PLA+ ≈ 1,30 Kč/g a ≈ 27 Kč za hodinu tisku,
- příprava 30 Kč za zakázku,
- nejnižší cena 99 Kč,
- cena se zaokrouhluje nahoru na desetikoruny.

**Další zdroje**:
- **Busty** (99/130/200 Kč) jsou z článku o bustě.
- **Doprava**: `config/farm.php` `shipping` (ceník Zásilkovny od 30. 9. 2026). CZ výdejní místo 99 Kč, domů 149 Kč;
  SK 149/175 Kč; EU od 199 Kč; do 2 kg bez příplatku, 2–5 kg +50 Kč.
- **Výroba 2 dny**: `lead_time_days` farmy v kalkulaci.
- **Materiály**: veřejná stránka `/materials`. Odtud jsou teploty, venku, potraviny, poměr cen (PETG +5 %,
  ASA +30 %, TPU +50 %) a seznam „Co je právě v tiskárnách“.

**Pozor, nesoulad v kalkulaci**:
- U PETG, ASA i TPU má cena farmy v kalkulaci popisek „Tisk u nás (PLA+)“.
- ASA vychází levnější než PLA+: stojánek 110 Kč proti 120 Kč, váleček 150 Kč proti 170 Kč. Stránka `/materials`
  přitom uvádí ASA o 30 % dražší.
- Proto články uvádějí absolutní ceny jen pro PLA+ a u ostatních materiálů poměr ze stránky `/materials`.
- Ověřit by se to mělo v ceníku farmy (`price_per_gram` materiálů farmy a jejich párování s materiálem kalkulace).

### 5.4 Angličtina a španělština

**Jak blog jazyky řeší**:
- Jazyky jsou v jednom řádku `posts`: `title`, `excerpt` a `body` jsou JSON pole podle jazyka a slug je společný.
- Článek existuje v jazyce, ve kterém má název i tělo (`Post::locales()`).
- `sitemap-blog-{cs,en,es}.xml` (`App\Support\Sitemaps::blog`) bere zveřejněné články, které daný jazyk mají,
  s `lastmod` = `updated_at`.
- Výpis blogu v jazyce ukazuje jen články v tom jazyce.

**Co z toho plyne**:
- Překlady blog umí. Dnes je má jen článek o bustě (cs, en, es v Markdownu).
- Šest článků z WordPressu je jen česky, proto jsem je jen česky přepsal. Sync na ostatní jazyky nesahá.
- Překlad by znamenal doplnit `en`/`es` do téhož řádku. Příkaz se dá rozšířit o `resources/blog/en|es/`, až to
  bude potřeba.

### 5.5 Články k nástrojům (§1a zadání), první pětice

Roman doplnil (přes řídící session 18. 10.): po starých článcích napsat články k nástrojům a článek s nástrojem
propojit oběma směry. Hotová je první pětice. Pořadí dál: další nástroje po dávkách, po každé dávce push.

| článek (slug) | nástroj, `article` v `config/tools.php` | čísla (kalkulace na produkci 18. 10., PLA+) |
|---|---|---|
| `cena-3d-tisku-z-modelu` | `calc` (hlavní stránka) | kostka 20 mm 99 Kč, stojánek 120 Kč, organizér 200 × 120 × 40 250 Kč, váleček ⌀ 120 × 200 910 Kč |
| `figurka-z-fotky-busta-a-mazlicek` | `pet`; `figure` → `jak-vznika-busta-z-fotky` | busty 99/130/200 Kč (článek o bustě); mazlíček bez ceny, nová figurka by stála kredity Tripo |
| `qr-cedulka-a-wifi-stojanek` | `qr` | web 70 mm 14 g 25 min 99 Kč; Wi‑Fi 90 mm se stojánkem 35 g 1 h 1 min 110 Kč (jedna barva, cena dvou barev je až u objednávky) |
| `organizer-do-supliku-na-miru` | `organizer`, `modular` | 100 × 80 × 30 2 × 2 41 g 57 min 110 Kč; 200 × 120 × 40 2 × 3 115 g 2 h 27 min 250 Kč; 300 × 400 × 50 hlásí `exceeds_typical_bed` |
| `krabicka-na-miru` | `box` | 80 × 50 × 30 bez víčka 28 g 34 min 99 Kč; s víčkem 41 g 51 min 110 Kč |

Dále: `check` a `repair` → `jak-pripravit-stl-soubor-pro-3d-tisk`.

**Jak čísla vznikla**: modely nástrojů vznikly na produkci jako u návštěvníka (`POST /api/tools/param`), pak se
spočítaly v kalkulaci. Bylo to 7 modelů a 7 výpočtů, User-Agent `matplace-session1-blog`. Wi‑Fi cedulka nesla
ukázková data z příkladu nástroje (síť „Kavarna“).

**Odkazy oběma směry**:
- Každý článek má v „Co dál“ odkaz na svůj nástroj.
- Klíč `article` u nástroje říká, na který článek má stránka nástroje odkázat. Zobrazí ho session 3 ve vstupních
  stránkách.
- Test `test_a_tool_and_its_article_link_each_other` hlídá, že článek existuje a odkazuje zpět na route nástroje.
- Staré články odkazují na nové: cena → kalkulace, fotky → figurka, AI → organizér a krabička, printpal → QR.

**Nové články mají `published_at` 18. 10. 2026 20:00 UTC**, `blog:sync` je tedy po nasazení rovnou zveřejní.
Kdyby měly počkat na fotky, stačí jim v hlavičce dát `published_at` prázdné.

**Fotky výtisků dodá Roman.** V textu je zástupné místo `<!-- foto: … -->`. Při vykreslení zmizí (Markdown se
renderuje bez HTML, test to hlídá). Fotku vložíte do `public/img/blog/<slug>/<název>.webp` a zástupné místo
nahradíte řádkem `![popis](/img/blog/<slug>/<název>.webp)`, stejně jako v článku o bustě.

| článek | fotka |
|---|---|
| cena-3d-tisku-z-modelu | kalkulace na telefonu s nahraným modelem, cenou a dobou tisku (stačí snímek obrazovky) |
| figurka-z-fotky-busta-a-mazlicek | vytištěná busta a figurka psa vedle sebe na poličce |
| figurka-z-fotky-busta-a-mazlicek | moderní a antická busta, vytištěné |
| figurka-z-fotky-busta-a-mazlicek | figurka psa ve stylu miniatura a kreslená |
| qr-cedulka-a-wifi-stojanek | QR cedulka s Wi‑Fi ve stojánku na stole, telefon nabízí připojení |
| qr-cedulka-a-wifi-stojanek | detail dvoubarevné cedulky, světlá destička a tmavý kód |
| organizer-do-supliku-na-miru | vytištěný organizér v kuchyňské zásuvce s příbory |
| organizer-do-supliku-na-miru | organizér na stole s kancelářskými potřebami |
| krabicka-na-miru | vytištěná krabička s víčkem a elektronikou uvnitř |
| krabicka-na-miru | krabička s nasazeným a sundaným víčkem |

Obálky (`cover`) nových článků jsou prázdné. Náhled pro sdílení kreslí `route('og')`, jako u článků bez obálky.

### 5.6 Druhá dávka článků k nástrojům

Druhou dávku vybrala řídící session 18. 10. Pravidla jsou stejná jako u první (§5.5). Nové články mají
`published_at` 18. 10. 2026 21:00 UTC.

| článek (slug) | `article` u nástroje | čísla (kalkulace na produkci 18. 10., PLA+) |
|---|---|---|
| `stojanek-na-telefon-na-miru` | `phone_stand` | klín 80 mm 60° 39 g 1 h 8 min 120 Kč; A‑rám 75 mm 65° 65 g 1 h 45 min 170 Kč |
| `vaza-a-obal-na-kvetinac-na-miru` | `vase` | hladká 160 mm 76 g 1 h 57 min 190 Kč; spirálová 180 mm 80 g 5 h 7 min 280 Kč; obal 120 mm s drenáží 99 g 3 h 45 min 260 Kč |
| `cedulka-jmenovka-a-klicenka-se-jmenem` | `sign`, `text` | klíčenka „Jana“ 2 g 5 min 99 Kč; 10 klíčenek 99 Kč celkem; „Ela“ 99 Kč; cedulka „Novákovi 12“ 173 × 69 mm 30 g 46 min 99 Kč |
| `3d-mapa-mesta-z-openstreetmap` | `map` | Staré Město 500 m, deska 150 mm, Hladká: 85 g 3 h 42 min 240 Kč (jedna barva) |
| `litofanie-z-fotky` | `relief` | panel 100 × 75 mm se stojánkem 27 g 1 h 9 min 100 Kč; lampa E27 ⌀ 100 × 150 mm 111 g 2 h 29 min 250 Kč |

**Jak čísla vznikla**:
- Nástroje se volaly na produkci jako u návštěvníka: `/api/tools/param`, `/api/tools/map` a `/api/tools/relief`.
- Litofanie vznikla z vygenerovaného obrázku s přechodem, ne z fotky člověka.
- Celkem to bylo 13 modelů a 13 výpočtů, User-Agent `matplace-session1-blog`.
- Obal na květináč s podmiskou hlásí `exceeds_typical_bed`: oba díly vedle sebe měří 287 mm. V článku je proto
  cena bez podmisky a věta o upozornění kalkulace.

**Odkazy ze starších článků na nové**:
- fotky → litofanie,
- AI → stojánek, váza, cedulka a mapa,
- printpal → mapa.

**Fotky výtisků** (zástupná místa `<!-- foto: … -->`, postup jako v §5.5):

| článek | fotka |
|---|---|
| stojanek-na-telefon-na-miru | vytištěný stojánek A‑rám s telefonem na pracovním stole |
| stojanek-na-telefon-na-miru | nízký klín s telefonem a vlnka vedle sebe |
| vaza-a-obal-na-kvetinac-na-miru | spirálová váza z PLA Silk se sušenými květinami |
| vaza-a-obal-na-kvetinac-na-miru | hladká a žebrovaná váza vedle sebe |
| cedulka-jmenovka-a-klicenka-se-jmenem | klíčenky se jmény ve dvou barvách a cedulka na dveře |
| cedulka-jmenovka-a-klicenka-se-jmenem | detail dvoubarevné cedulky, písmo jinou barvou než deska |
| 3d-mapa-mesta-z-openstreetmap | vytištěná 3D mapa městského centra ve třech barvách |
| 3d-mapa-mesta-z-openstreetmap | detail mapy, budovy, silnice a řeka v různých barvách |
| litofanie-z-fotky | litofanie s portrétem proti oknu, vedle stejná destička bez světla |
| litofanie-z-fotky | lampa s fotkou kolem dokola, rozsvícená |
