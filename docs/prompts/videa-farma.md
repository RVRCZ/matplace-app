# Zadání pro session 1: videa z farmy rychlejší, s hudbou, klasická 16:9 a týdenní sestřih (TODO #11)

Od Romana (16. a 17. 10. 2026): „klasická videa z farmy vedle Shorts, z každého časosběru i verze 16:9 a týdenní
sestřih; zrychlit videa; ke všem videím přidat hudbu, ne jen pro YouTube.“

## 0. Kde pracuješ a pravidla

- Tvůj worktree (`C:\matplace-shapes-wt`), nová větev **`feature/farm-videos`** z aktuálního `main`
  (`git fetch origin && git checkout -b feature/farm-videos origin/main`), dokument **`docs/AD.md`**.
- Tvoje soubory: `app/Jobs/BuildFarmTimelapse.php`, `app/Jobs/UploadFarmVideo.php`, `app/Jobs/AddFinishPhotoToVideo.php`,
  `app/Domain/YouTube/FarmVideos.php`, `app/Domain/Social/VideoSharer.php`, `app/Console/Commands/FarmTimelapse.php`,
  `app/Console/Commands/SocialVideos.php`, nové příkazy, `app/Models/FarmVideo.php` (+ migrace), `config/youtube.php`,
  administrace videí (`routes/web.php` blok `admin/youtube`, její kontroler a pohledy), stránka zakázky jen tam, kde
  ukazuje video, `lang/*/youtube*.php` nebo přes `lang/src`. Nesahat: agent, slicer, `PlateLayout` (session 2),
  `OrderFlow`, `OrderService`, `param.ts`, enginy.
- Pravidla: texty jen v `lang/{cs,en,es}/*.php` nebo `lang/src/<soubor>.json` + `python scripts/lang_add.py`; `git add`
  jmenovitě; žádný stash; `PYTHON_BIN` v `.env`; před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`,
  `npm run build`; testy `php -d memory_limit=2G vendor/bin/phpunit --filter …`. Nemerguješ a nenasazuješ: push na
  `origin feature/farm-videos` a zpráva řídící session (conceprt19092026-8c), ta nasadí a na serveru spustí
  `farm:timelapse` na skutečné zakázce (snímky leží jen na serveru, 14 dní).
- Co dnes je: `BuildFarmTimelapse` (logo → 3, 2, 1 → růst → hotový kus 4 s → fotka 3 s → poděkování), snímek po vrstvě
  nebo po minutě, tempo `FRAME_SECONDS` 0,125 s na minutu tisku ÷ `GROWTH_SPEED` 1,25, u vrstev `min(0,125, 30/N)`,
  čtvercový Short (`SHORT_SECONDS` 28, max 1000 snímků, ořez podle tiskárny); hudba se přidává až při uploadu na YouTube
  (`FarmVideos::withMusic`, `config/youtube.php` `music_dir`, `music_volume` 0,8), stažení zákazníka je bez hudby;
  schvalování v `/admin/youtube`, po schválení FB/IG přes `social:videos`; `farm_videos` má jeden řádek na zakázku
  (`farm_order_id` unique), `score`, `music`, statistiky (`refreshStats`); UTM `utm_campaign=video`. Testy
  `FarmLayerTimelapseTest`, `YouTubeVideosTest`. Zveřejněná videa k porovnání: YouTube `1191KfZbCDU`, `Fd72RTnqYDE`.

## 1. Rychlejší videa

- Short: růst **nejvýš 12 až 15 s**, celý Short do 20 s (úvod a konec kratší než dnes, hotový kus 2 s, fotka 2 s).
- Klasické 16:9 (viz 3): růst 20 až 30 s, celkem do 45 s. Tempo stále rovnoměrné (žádné zrychlování uprostřed),
  první a poslední snímek vždy; u dlouhých tisků bere každý n-tý snímek (`pace`) tak, aby délka seděla.
- Napiš do AD.md, co dnešní video `1191KfZbCDU` měří a co bude měřit nové; řídící nechá Romana potvrdit na prvním
  novém videu. Konstanty do `config/youtube.php` (ne natvrdo v jobu), ať se dají ladit bez kódu.

## 2. Hudba ke všem videím

- Hudbu dostane **každé sestavené video** už při stavbě: zákazníkovo na stránce zakázky, Short, 16:9 i sestřih.
  Jedna skladba na zakázku (`pickMusic` v pořadí podle id, jako dnes) pro všechny její verze; hlasitost
  `music_volume`, na konci 1,5 s zeslabení (fade out), při kratším videu skladbu ustřihnout, ne zrychlit.
- `withMusic` při uploadu už nic nepřidává (video hudbu má); `music` v `farm_videos` zůstává jako záznam skladby.
  Když knihovna (`music_dir`) chybí nebo je prázdná, video vznikne bez hudby a do logu jde jedna věta.

## 3. Klasické 16:9 z každého časosběru

- Vedle Shortu vzniká **`timelapse_wide_path`** (nový sloupec zakázky): 1920 × 1080 (snímky kamery 1280 × 720 zvětšit
  s jemným doostřením, nebo nechat 1280 × 720, když zvětšení vypadá hůř; rozhodni a napiš proč), 30 fps.
- Skladba: úvodní titulek 1,5 s (název modelu a druh, jako `defaultTitle`), růst (bod 1), hotový kus 2 s, fotka
  z boxu 3 s, když je, závěrečná karta 3 s s adresou nástroje `matplace.com/tools/<nástroj>` (zakázka ví, z jakého
  nástroje je; cizí STL dostane `matplace.com`), hudba (bod 2).
- Na YouTube jde jako **samostatné video** po schválení: `farm_videos` dostane sloupec `kind` (`short` | `wide` |
  `weekly`), `farm_order_id` přestane být unique (unikátní dvojice zakázka + druh). Schvalovací stránka ukáže obě
  verze zakázky vedle sebe s jedním tlačítkem pro každou; popis se liší jen UTM: Shorts `utm_campaign=video_short`,
  16:9 `video_wide`, sestřih `video_weekly` (dnešní `video` ponech jako výchozí pro staré odkazy).
- FB/IG (`VideoSharer`) sdílí jen Short, jako dnes; 16:9 ne (rozhodnuto, ať se nehrnou dvě videa z jednoho tisku).

## 4. Týdenní sestřih „Tento týden na farmě“

- Příkaz **`farm:weekly-video {--week=2026-W42} {--dry-run}`**: všechny časosběry týdne (zakázky se souhlasem k videu,
  bez `PRIVATE_KINDS`, s `timelapse_path`), každý kus 6 až 10 s růstu + krátký titulek s názvem, úvod „Tento týden
  na farmě“ s datem, závěr s adresou, hudba; 2 až 4 minuty, 16:9. Výsledek jako `FarmVideo` druhu `weekly` ve stavu
  čekající na schválení (bez zakázky: `farm_order_id` nullable, cesta k souboru v novém sloupci `path`), popis
  s kapitolami (čas + název + odkaz na nástroj s UTM).
- Plánovač: každou neděli 18:00 sestavit minulý týden (jen připravit, nikdy nezveřejnit sám). Když týden nemá aspoň
  3 časosběry, nic nevzniká a do logu jde věta.

## 5. Měření

- V `/admin/youtube` souhrn podle druhu (Shorts, 16:9, sestřih): počet, zhlédnutí, lajky za 7 a 30 dní (ze stávajících
  `refreshStats`), ať Roman po měsíci vidí, co táhne. Žádné nové API.

## 6. Testy a ověření

- Testy bez ffmpeg: `BuildFarmTimelapse` vystaví seznamy snímků a filtry tak, aby test mohl ověřit délky (počet
  snímků × doba), přítomnost hudby ve filtru, tři druhy videí, kapitoly sestřihu; `YouTubeVideosTest` na `kind`, UTM,
  schválení obou verzí; migrace zpětně kompatibilní (staré řádky = `short`).
- Lokálně s ffmpeg (když je, `ffmpeg -version`): syntetické snímky (200 PNG s číslem) → Short, 16:9 a sestřih ze tří
  „zakázek“; zapiš délky a velikosti do AD.md a ulož po jednom snímku z každého do `docs/img/farm-videos/`.
- **Hotovo =** push `feature/farm-videos`, AD.md (rozhodnutí: rozlišení 16:9, tempa, co se měří), testy, `pint`, `tsc`,
  `build`, zpráva řídící session.

## 7. Co není v zadání

Videa k nástrojům z Romanova záznamu (TODO #12, jiná session), změna agenta a snímkování, Meshy, FB/IG pro 16:9.
