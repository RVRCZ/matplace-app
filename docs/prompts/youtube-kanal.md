# YouTube kanál a fotky z fotoboxu – session 2

Roman 27. 10. 2026: audit kanálu `docs/prompts/audit-youtube-2026-10-27.md` a priority návštěvnosti
`docs/prompts/priority-navstevnost-2026-10-27.md` bod 6. K tomu Roman: „fotky z fotoboxu doplnit vhodným pozadím automaticky
pomocí nástroje, který už máme v portfoliu“ = odstranění pozadí rembg ze studia session 4 (větev `feature/tools-sell`,
nikdy nesloučená: `app/Engines/Photo/RembgBackgroundRemover.php`, `FakeBackgroundRemover`, `engines/python/photo_cut.py`,
`engines/python/photo_backgrounds.py`, `app/Domain/Tools/PhotoCut.php`). Piš česky, krátce, bez pomlček.

## Pracoviště

- Worktree `C:\matplace-gcode-wt`, nová větev `feature/youtube-shorts` z `origin/main` (po `git fetch origin`). Nemerguješ
  ani nenasazuješ. Deník `docs/AL.md` §13. Žádné volání YouTube API naostro bez Romanova slova (titulky 33 videí přejmenuje
  až po jeho schválení); pro vývoj `FakeYouTube`/testy jako dosud. Push po dávkách + zpráva řídící (`conceprt19092026-91`).
- Co už je: `App\Domain\YouTube\FarmVideos` (fronta videí ze zakázek, týdenní sestřih, návod, `link()` s utm_source/medium/campaign),
  `YouTubeClient` (upload, publish(id, title, description), statistics, comment, delete; playlisty ani úprava titulku zvlášť
  nejsou), ffmpeg skládání (docs/AD.md, AK.md), odkazy na nástroj první řádek popisu (AK.md §4), připnutý komentář (force-ssl).
  31 zveřejněných videí, 12 čeká, 26 odmítnutých. Souhlas zákazníka `video_consent` u zakázky.
- Fotobox: fotky z kamery Trust Teza v `C:\Users\roman\Pictures\Camera Roll` (126 souborů `WIN_<datum>_Pro.jpg`), exponování
  `kamera_expozice.ps1` (paměť `reference_photo_camera`). Server nemá rembg; model U2NET a `U2NET_HOME` byly u session 4 lokálně.

## Úkoly (v tomhle pořadí)

1. **Fotky výtisků bez pozadí a na pozadí** (Romanův dodatek, podmínka pro konec Shorts i pro web). Přenes z
   `feature/tools-sell` jen fotkovou část (BackgroundRemover + rembg + fake, `photo_cut.py`, `photo_backgrounds.py`), ne studio
   s Gemini a Claude. Příkaz `php artisan farm:photo <soubor|složka> [--order=F26-000123] [--bg=studio|wood|white|gradient]`:
   ořízne pozadí (rembg), položí na zvolené pozadí (z `photo_backgrounds.py`, jedno výchozí vybrané pro Shorts 9:16 a jedno
   pro web 3:2), uloží `storage/app/public/prints/<zakázka>/` (cutout PNG + složené JPG + WebP), u zakázky `photo_path`
   (migrace, šířky sloupců proti MariaDB). Admin: na stránce zakázky tlačítko „Nahrát fotku výtisku“ (upload z PC, zpracuje
   se ve workeru). Dávka: `farm:photo "C:\...\Camera Roll" --match=<datum>` pro Romana lokálně, výstup do složky, kterou
   pak nahraje. Fotku ukázat na stránce zakázky zákazníkovi, v bloku důvěry (session 3 ji přečte z `PublicStats` nebo
   z nové metody `FarmOrder::photoUrl()`, domluv rozhraní zprávou), v `config/site.php` `example_items.photo` (Roman vybere).
   Na serveru je třeba `pip install rembg onnxruntime` do Python prostředí enginů + model: napiš přesný postup pro řídící.
2. **Nová šablona Shorts** (od příštího videa): první sekunda textová karta s háčkem („Z téhle fotky → busta“, „Zákazník poslal
   rozměr → organizér“; u modelů z fotky ukázat fotku, pokud je souhlas), tělo časosběr 15 až 25 s, poslední 3 s hotový
   výtisk (fotka z bodu 1, bez ní nekončit prázdnou podložkou) s textem „Cena: 99 Kč“ (cena zakázky, zaokrouhlená) a
   adresou nástroje v obraze (`matplace.com/tools/figure`), na konci otázka („Co vytisknout příště?“). Hudba zůstává z
   Audio Library (trending zvuky jen ručně v Studiu, nejde přes API). Horizontální duplikáty přestat nahrávat (jen Shorts;
   16:9 jen pro delší formát).
3. **Tituly a popisy**: šablona „[co vzniklo] + háček + doba tisku“ (bez „za 29 s“), unikátní podle toho, co se tiskne
   (název modelu/nástroje, ne název souboru z Printables); popis: první řádek odkaz na konkrétní nástroj s
   `utm_content=<videoId>` (doplň do `FarmVideos::link()`), materiál, tiskárna, doba, 3 až 5 hashtagů česky (#3dtisk
   #3dtiskarna #dárek + 1 k tématu); připnutý komentář s odkazem na nástroj a cenou.
4. **Stávajících 33 videí**: vygeneruj tabulku `docs/data/youtube-titles-2026-10.csv` (id, dnešní titulek, zhlédnutí, nový
   titulek, nový popis, playlist, duplikát ano/ne) a pošli řídící; Roman schválí; teprve pak příkaz `youtube:retitle --apply`
   (doplň do `YouTubeClient` úpravu `snippet` přes `videos.update`, dry-run výchozí). U 7 horizontálních duplikátů navrhni
   „unlisted“, rozhodne Roman.
5. **Playlisty přes API** („Figurky z fotky“, „Dárky se jménem“, „Pro domácnost“, „Jak to funguje“): `playlists.insert`,
   `playlistItems.insert`, přiřazení podle nástroje zakázky; dry-run, apply po schválení.
6. **Texty pro Studio** (Roman klikne sám): popis kanálu česky s klíčovými slovy, odkazy (web, Instagram, Facebook, e-mail
   pro firmy), banner s CTA, trailer = zkrácené video „fotka → model → výtisk“. Do `docs/data/youtube-kanal-texty.md`.
7. **Odkaz na video zákazníkovi**: po dotisknutí a zveřejnění e-mail „vaše video“ (jen se souhlasem `video_consent`), s
   prosbou o sdílení; měřit kliknutí (`utm_content`).

## Ověření a výstup

Testy s falešným YouTube a falešným odstraňovačem pozadí, ffmpeg lokálně (`imageio-ffmpeg`, paměť `reference_ffmpeg_local`),
jeden zkušební Short složený lokálně z existující zakázky s fotkou (cesta k souboru ve zprávě), migrace proti MariaDB.
Zpráva řídící po každé dávce: změněné soubory, co se změnilo, jak ověřeno, co čeká na Romana (schválení titulků,
playlistů, instalace rembg na serveru).
