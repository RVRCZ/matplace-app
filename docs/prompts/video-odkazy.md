# Zadání pro session 1: odkazy z videí (YouTube, Shorts, Facebook, Instagram) tam, kam má divák jít

Od Romana (18. 10. 2026, nad schvalováním videí F26‑000085 v `/admin/youtube`): „Kam je ten odkaz ve videích?“
Dnes (`app/Domain/YouTube/FarmVideos.php` `toolUrl()`/`link()`): model z nástroje → stránka nástroje, jinak hlavní
stránka (ta je zároveň kalkulace s polem pro soubor, `resources/views/calculator/index.blade.php`, `id="calculator"`),
vždy s `utm_source=youtube&utm_medium=social&utm_campaign=video_<druh>`, aby se návštěvy z videí daly spočítat
v `/admin/stats`. Facebook a Instagram (`app/Domain/Social/VideoSharer.php` `siteLink()`) posílají vždy na hlavní
stránku s `utm_source=facebook`, i když video vzniklo v nástroji.

## 0. Kde pracuješ a pravidla

- Větev **`feature/video-links`** z aktuálního `main`, dokument **`docs/AK.md` §4** (pokračuj v AK.md).
- Soubory: `FarmVideos.php` (link, address, popisy), `VideoSharer.php`/`SocialPublisher.php` (odkaz na FB/IG),
  `lang/cs|en|es/youtube.php` a `lang/src/*.json` přes `python scripts/lang_add.py`, `YouTubeClient.php` jen pro
  bod 3, testy `tests/Feature/YouTubeVideosTest.php`, `FarmVideosCutTest`, `SocialVideo*`. Nesahat: střih videí,
  `TimelapseCutter`, `HowtoCutter`, farma, kalkulace.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, testy; nemergovat, nenasazovat; hotovo = push + zpráva
  řídící session (conceprt19092026-8c). Nic nenahrávat na YouTube ani FB; skutečné volání API jen čtením.

## 1. Kam odkaz vede (jedno místo pravdy)

- Jedna metoda pro všechny kanály: cíl = stránka nástroje, když model vznikl v nástroji (`origin tool/generated`,
  i `relief`/`lithophane` → relief), jinak hlavní stránka; instruktážní video (`howto`) → cíl ze specifikace
  (`scripts/video/howto.json`, dnes hlavní stránka), týdenní sestřih → hlavní stránka. `utm_source` podle kanálu
  (youtube, facebook, instagram), `utm_campaign` podle druhu (video_short, video_wide, video_weekly, video_howto),
  FB/IG z téhož místa (`VideoSharer::siteLink` → `FarmVideos::link($order, $kind, $source)`).
- Koncová karta (`address()`): u videa z nástroje „matplace.com/tools/…“, jinak „matplace.com“ (beze změny, jen
  ověřit, že sedí s cílem odkazu).

## 2. Popis: odkaz v prvních dvou řádcích

YouTube ukáže bez rozkliknutí jen první dva řádky popisu (Shorts ještě méně), odkaz je dnes až v pátém odstavci.
Přeskládat popis (youtube.php `description`, `short.description`, `weekly.description`, howto v `popis.txt` ze
specifikace): 1. řádek = výzva s odkazem („Nahrajte model a hned vidíte cenu: https://matplace.com/?…“ /
u nástroje „Udělejte si vlastní: https://matplace.com/tools/…“), pak materiál, tiskárna, doba tisku, pak „Video
zveřejňujeme se souhlasem zákazníka.“ a kredit hudby, hashtagy na konci. Tři jazyky; test, že odkaz je v prvních
dvou řádcích každého druhu.

## 3. Připnutý komentář s odkazem (zjistit, nenasazovat bez Romana)

Připnutý komentář pod videem je nejviditelnější místo odkazu. API: `commentThreads.insert` + `comments.setModerationStatus`/
„pin“ (pin v API není; ověř, co jde). Potřebný rozsah je `youtube.force-ssl`; dnešní token má jen
`https://www.googleapis.com/auth/youtube` (`YouTubeClient::SCOPE`), takže by Roman musel znovu udělit souhlas.
Napiš do AK.md, co by to obnášelo (změna SCOPE, nový souhlas v `/admin/youtube`, kde se komentář vkládá po nahrání),
a připrav kód za přepínačem (`YOUTUBE_PIN_COMMENT=false` výchozí), bez skutečného volání.

## 4. Hotovo

Push `feature/video-links`, AK.md §4 (kam co vede, tabulka druh × kanál × cíl × utm), testy, zpráva řídící session.
