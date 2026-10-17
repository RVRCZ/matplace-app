# AD – Videa z farmy: rychlejší, s hudbou, klasická 16:9 a týdenní sestřih

Session 1, větev `feature/farm-videos` (z `origin/main` 4138678), 17. 10. 2026. Zadání: `docs/prompts/videa-farma.md` (TODO #11).

## 1. Co se změnilo

**Z každého časosběru vznikají tři videa, všechna s hudbou** (`TimelapseCutter`, nastavení `config/youtube.php` → `cut`)

| video | soubor | velikost | skladba | délka |
|---|---|---|---|---|
| zákaznické (stránka zakázky) | `timelapse.mp4` | jako kamera (max. 1280 š.), 24 fps | logo, 3 2 1, růst, hotový kus 3 s, fotka 3 s, poděkování | růst 12–28 s, celkem do 46 s |
| Short | `short.mp4` | čtverec (max. 1080), 24 fps | hotový kus 0,5 s, růst, hotový kus 2 s, fotka 2 s (prolnutí 0,5 s), logo v rohu | růst 8–14 s, **celkem do 18 s** |
| klasické 16:9 | `wide.mp4` (nový sloupec `farm_orders.timelapse_wide_path`) | 1920 × 1080, 30 fps | titulek 1,5 s, růst, hotový kus 2 s, fotka 3 s, karta s adresou nástroje 3 s, logo v rohu | růst 20–28 s, **celkem do 37,5 s** |
| týdenní sestřih | `weekly/2026-W42.mp4` | 1920 × 1080, 30 fps | úvod „Tento týden na farmě“ s daty 3 s, každý tisk 8,5–10 s růstu + aspoň 1 s hotového kusu (kapitola ≥ 10 s) s názvem vlevo dole, karta „matplace.com“ 4 s | **do 4 min** |

- **Tempo** je rovnoměrné: jeden snímek (vrstva, nebo minuta tisku) = 0,1 s, natažené nebo stlačené do rozsahu
  druhu. Nikdy pomaleji než 0,2 s na snímek, takže krátký tisk zůstane krátký. Když by snímek trval méně než jeden
  snímek videa, bere se každý n-tý snímek rovnoměrně (`paced`). První a poslední snímek zůstávají vždy. Zrychlování
  uprostřed není. Všechny konstanty jsou v `config/youtube.php` (`cut.*`), ladí se bez kódu.
- **Hudba** je ve videu už při stavbě, i v tom zákaznickém (na stránce zakázky už video není `muted`). Na zakázku
  připadá jedna skladba pro všechny verze. Je to ta, kterou už nese některé její video v `farm_videos.music`, jinak
  `pickMusic` podle id jako dřív. Hlasitost `music_volume` 0,8, náběh 0,5 s, zeslabení na konci 1,5 s
  (`music_fade_out`). Delší skladba se ustřihne, kratší se opakuje (`-stream_loop`), nikdy se nezrychluje.
  Prázdná nebo chybějící knihovna = videa bez hudby a v logu jedna věta „Farm videos are built without music…“.
- `FarmVideos::withMusic` je pryč. Upload posílá soubor, jak je.

**YouTube: Short a 16:9 jsou dvě samostatná videa**
- `farm_videos` má nový sloupec `kind` (`short` | `wide` | `weekly`, výchozí `short`, takže staré řádky jsou
  Shorts). Dál `path` (soubor sestřihu) a `meta` (týden a id zakázek v sestřihu). `farm_order_id` je nullable
  a unikátní je dvojice zakázka + druh (migrace `2026_10_20_100000_farm_videos_kinds`).
- `queueFor` založí řádek pro každou postavenou verzi a vrací Short (testy a volající beze změny). Adminovi jde
  jeden e-mail na tisk.
- Popis se liší jen UTM: `utm_campaign=video_short`, `video_wide`, `video_weekly`. Odkaz v popisu dál vede na
  úvodní stránku; dřívější `video` zůstává ve statistikách pro staré odkazy. `#Shorts` má jen Short.
- `/admin/youtube`:
  - verze jedné zakázky jsou vedle sebe, každá má svůj přehrávač, název, popis a tlačítko,
  - fotka z foto-boxu a údaje zakázky jsou nad nimi jednou,
  - Facebook/Instagram se nabízí jen u Shortu (`VideoSharer::due` bere jen `short`),
  - sestřih je samostatný blok „Týden 2026-W42 · N tisků“,
  - soubor ke schválení: `GET /admin/youtube/videos/{video}/video.mp4` (dřív podle zakázky).
- `/admin/youtube` → Statistiky → **„Podle druhu videa“**: pro Shorts, 16:9 a sestřih počet videí, zhlédnutí
  a lajky. Sloupce „za 7 dní“ a „za 30 dní“ = videa zveřejněná v tom období a jejich dosavadní čísla. Bere se ze
  stávajícího `refreshStats`, které drží jen součty, ne historii po dnech. Nové API není.
- Stránka zakázky: u zveřejněného videa i odkaz „Klasické video (16:9)“.
- Odvolaný souhlas smaže z YouTube obě verze. Sestřih s tím tiskem dostane chybu pro admina („… odvolal souhlas:
  sestavte týden znovu … nebo video stáhněte“). Neschválený sestřih se pak nenahraje, protože `consented` hlídá
  všechny zakázky v `meta.orders`.

**Týdenní sestřih: `php artisan farm:weekly-video {--week=2026-W42} {--dry-run}`**
- Bez `--week` bere v neděli týden, který končí, jindy minulý týden.
- Do sestřihu jdou tisky:
  - druhu `print` nebo `showcase`,
  - se souhlasem,
  - dokončené v tom týdnu (`finished_at`, pražský čas),
  - ne z fotek (`PRIVATE_KINDS`),
  - bez zamítnutého videa,
  - se snímky na disku (14 dní).
- Při méně než 3 tiscích (`cut.weekly.min_prints`) nic nevzniká a do logu jde věta.
- Víc tisků, než se vejde do 4 minut (nejvýš 23), řeší výběr podle `score` v pořadí tisku.
- `--dry-run` vypíše tisky a kapitoly a nic nestaví.
- Výsledek je `FarmVideo` druhu `weekly` ve stavu `pending`. Nová stavba téhož týdne přepíše soubor i popis,
  dokud video není na YouTube.
- Plánovač: neděle 18:00 Europe/Prague, `runInBackground`, `withoutOverlapping`. Sestřih nikdy nezveřejní sám.
- Popis tvoří úvodní věta, kapitoly a hudba, například:

  ```
  0:00 Stojánek na telefon – https://matplace.com/tools/phone-stand?utm_source=youtube&utm_medium=social&utm_campaign=video_weekly
  0:14 PLA+ bílá – https://matplace.com/?utm_…
  ```

  YouTube kapitoly ukáže jen tehdy, když první začíná v 0:00, jsou aspoň tři a každá má ≥ 10 s. Proto první tisk
  začíná v 0:00 včetně úvodu a každý kus má aspoň 10 s (`piece_min`).

**Adresa nástroje** (`FarmVideos::toolUrl/address/link`): model z nástroje → `/tools/<nástroj>` (litofanie
a reliéf → `/tools/relief`), postava z fotky → `/tools/figure`, cizí STL → `matplace.com`. Na kartě 16:9 je adresa
bez `https://`, v kapitolách s UTM.

## 2. Rozhodnutí

- **16:9 v 1920 × 1080, zvětšené z 1280 × 720** (lanczos + jemné `unsharp=5:5:0.5`). Proč:
  - YouTube dává videím nahraným v 1080p vyšší datový tok a lepší kodek než 720p,
  - stejný snímek kamery proto vypadá na YouTube ostřeji jako 1080p upload než jako 720p,
  - titulky a karty se kreslí přímo v 1080p, takže písmo je ostré vždy.
  
  Snímek jiného poměru (4:3) se nedeformuje, dostane černé pruhy po stranách. Na skutečném snímku z kamery jsem
  zvětšení **neviděl**: snímky leží jen na serveru. Kdyby po prvním videu vypadalo hůř, stačí
  v `config/youtube.php` dát `cut.wide.width/height` 1280/720.
- **Tempa** viz tabulka v §1. Zákaznické video se zkrátilo jen v růstu (max. 28 s místo až 60 s u tisků se
  snímkem po minutě) a hotový kus drží 3 s místo 4 s. Úvod s 3 2 1 a poděkování zůstaly.
- Názvy na kartách jsou v jazyce kanálu (`youtube.language`), zákaznické video v jazyce zákazníka (jako dřív).
  Bez čitelného názvu modelu je na titulku materiál a barva a pod ním jen doba tisku.
- Ve sestřihu je název tisku po celou dobu jeho kusu (dole vlevo v poloprůhledném rámečku).
- FB/IG dostanou jen Short (rozhodnuto v zadání).

**Co měří dnešní videa a co budou měřit nová** (délky ze stávajícího vzorce a z názvů videí. Stránky YouTube se
přes cookie zeď přečíst nedaly; čísla „za 25 s“ / „za 8 s“ v názvu počítá `defaultTitle` z délky souboru):

| | dnes (Short) | nový Short | nové 16:9 |
|---|---|---|---|
| `1191KfZbCDU` Stojánek na telefon, 2 h 54 min | **25 s** = 1 s + růst 22,4 s (strop 28 s ÷ 1,25) + 2 s, bez fotky | 16,5 s (s fotkou 18 s) = 0,5 + 14 + 2 | 26,5–34,5 s podle počtu snímků (růst 20–28 s), s fotkou +3 s |
| `Fd72RTnqYDE` Medaile s řetězem, 1 h 18 min | **8 s** = 1 + růst 5 s (~31 snímků po 0,16 s) + 2 | ~8,7 s = 0,5 + 6,2 (31 × 0,2 s) + 2 | ~12,7 s = 1,5 + 6,2 + 2 + 3 |

Dlouhé tisky jsou tedy v Shortu o ~9 s kratší. Krátké tisky zůstávají krátké a jdou o chlup pomaleji. Roman
potvrdí na prvním novém videu; ladí se `cut.short.growth` a `cut.wide.growth`.

## 3. Ověření

- Testy:
  - nový `FarmVideosCutTest`: tempo, tři střihy s hudbou, ticho bez knihovny, adresa nástroje, týdenní sestřih
    s kapitolami a souhlasy, dva testy se skutečným ffmpeg,
  - `YouTubeVideosTest`: obě verze vedle sebe, UTM, upload bez míchání, staré řádky = `short`, souhrn podle druhu,
    souhlasy u sestřihu,
  - `FarmLayerTimelapseTest`: Short s fotkou +1,5 s.
- Lokálně s ffmpeg 7.1 (imageio-ffmpeg, `FFMPEG_BIN`): 200 číslovaných snímků 1280 × 720, skladba 20 s sinus.
  Délky sedí na plán přesně.

  | video | délka | velikost obrazu | soubor | zvuk |
  |---|---|---|---|---|
  | zákaznické | 35,0 s | 1280 × 720 | 713 kB | AAC |
  | Short | 16,5 s | 720 × 720 | 279 kB | AAC |
  | 16:9 | 26,5 s | 1920 × 1080 | 727 kB | AAC |
  | sestřih ze 3 zakázek | 40,0 s | 1920 × 1080 | 1 296 kB | AAC |

  S fotkou z foto-boxu: Short +1,5 s, 16:9 a zákaznické +3 s (ověřeno testem). Snímky z videí:
  `docs/img/farm-videos/` (`short`, `wide-title`, `wide`, `wide-end`, `customer`, `weekly-intro`, `weekly`).
  Spuštění: `FFMPEG_BIN=… FARM_VIDEO_SAMPLES=docs/img/farm-videos phpunit --filter real`.
- `pint`, `tsc`, `npm run build` čisté; celá sada 648 testů OK (s `FFMPEG_BIN`, tedy i testy se skutečným ffmpeg).

## 4. Pro řídící session (nasazení a první ostré video)

1. Migrace `2026_10_20_100000_farm_videos_kinds`:
   - na MySQL nejdřív přidá unikát (zakázka, druh), pak zruší starý unikát (cizí klíč se opře o nový) a udělá
     `farm_order_id` nullable,
   - lokálně ověřeno jen na SQLite.
2. Knihovna hudby na serveru (`YOUTUBE_MUSIC_DIR`, výchozí `storage/app/music`): bez MP3 vznikají videa tiše a log
   to řekne.
3. Ostrá zkouška: `php artisan farm:timelapse F26-…` na zakázce se snímky (do 14 dní). Pak:
   - v `/admin/youtube` musí být Short a 16:9 vedle sebe, obě s hudbou,
   - délky porovnat s §2,
   - na 16:9 posoudit ostrost zvětšení.
4. `php artisan farm:weekly-video --dry-run` vypíše tisky tohoto/minulého týdne bez stavby. První ostrý sestřih
   vznikne sám v neděli v 18:00.
5. Videa, která už čekají na schválení, jsou postavená postaru, bez hudby (upload hudbu nově nepřidává). Kdo je chce
   s hudbou: `farm:timelapse <číslo>`. Vznikne tím i 16:9.
6. Job `BuildFarmTimelapse` staví tři videa, timeout 900 s (16:9 v 1080p je nejdražší; lokálně jednotky sekund
   na 200 snímků).

## 5. Otevřené

- Ostrost 1080p zvětšení na skutečné kameře (§2) a tempo nových videí potvrdí Roman na prvním videu.
- Licence skladeb z YouTube Audio Library mimo YouTube (zákaznické video, FB/IG Short): Roman chtěl hudbu všude.
  Některé skladby knihovny vyžadují uvedení autora. Popis to dělá; v zákaznickém videu ani v souboru ke stažení
  uvedení není.
