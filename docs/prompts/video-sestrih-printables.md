# Zadání pro session 1: sestřih instruktážního videa „model z Printables → matplace → výtisk“ (TODO #12)

Roman 18. 10. 2026 natočil průchod podle `docs/prompts/video-printables.md` (zčásti podle `video-navod.md`) k zakázce
**F26-000085** (vlastní STL z Printables, tiskne se na U #1). Ty video sestavíš a připravíš ke schválení.

## 0. Vstupy a kde pracuješ

- Surový záznam OBS: `C:\Users\roman\Desktop\video\2026-10-18 10-20-58.mp4` (27 MB, 1920 × 1080 podle návodu; délku
  změř ffprobe). Nehýbej s ním, pracuj s kopií.
- Časosběr zakázky dodá řídící session po dotisku do `C:\Users\roman\Desktop\video\F26-000085\` (`timelapse.mp4`
  zákaznické, `short.mp4`, `wide.mp4` 16:9 z pipeline farmy). Dokud tam nejsou, stříhej průchod a nech na časosběr místo.
- Záběr telefonem na hotový kus (`C:\Users\roman\Desktop\video\vec-1.mp4`) a odkaz na model, autora a licenci na
  Printables dodá Roman; dokud nejsou, konec videa drž na posledním snímku časosběru a do popisu dej zástupný řádek
  `MODEL: …` (řídící doplní).
- Tvůj worktree, větev **`feature/howto-video`** z aktuálního `main`, dokument **`docs/AK.md`**. Výstupy mimo repo do
  `C:\Users\roman\Desktop\video\out\`: `printables-howto.mp4` (1920 × 1080, 30 fps, AAC, do 3 minut),
  `printables-howto-short.mp4` (1080 × 1920, do 60 s), `popis.txt` (název a popis pro YouTube cs, odkazy, UTM
  `utm_campaign=video_howto`, kredit hudby jako `FarmVideos::musicCredit`), `titulky.srt` cs a en (titulky jsou i vypálené).
- Pravidla jako jinde: `git add` jmenovitě, žádný stash, `pint --dirty`, `tsc`, `build`, testy; nemergovat, nenasazovat;
  hotovo = push + zpráva řídící session (conceprt19092026-8c).

## 1. Střih (podle `video-printables.md` §2)

- Pořadí: úvod 2 s (název „Model z Printables vytištěný na matplace“ + adresa) → Printables (výběr, stažení) →
  nahrání na matplace → kalkulace (cena a doba nech číst 3 s) → zakázka (barva s kódem, doprava, podmínky, platba)
  → časosběr 16:9 (zrychlený, 15 až 25 s) → stránka zakázky „hotovo“ → záběr telefonem (až bude) → konec
  „matplace.com“ 3 s.
- Průchod zrychlit 1,5 až 2×, statické pauzy a chyby ven, posuny kolečkem nechat, klik na zaplacení nenatáčel, nebo
  ven. Titulky cs a en v obraze u každého kroku (bílý text na tmavém pruhu dole, 2 řádky nejvýš, 2 až 4 s), nic
  nepřekrývá tlačítka. Hudba z knihovny farmy (`YOUTUBE_MUSIC_DIR`, 16 skladeb) tišeji než u časosběrů (0,6), fade out.
- Nic osobního: zkontroluj záznam na e‑mail, kredit, adresu; kdyby tam byly, rozmaž nebo vystřihni.
- Short 9:16: nejlepších 20 až 45 s (nahrání → cena → časosběr → kus v ruce), titulky větší.

## 2. Aby to šlo příště znovu

- Střih popsaný daty, ne ručně: `scripts/video/howto.json` (segmenty: zdroj, od, do, rychlost, titulek cs/en) a
  skript `scripts/video/howto.mjs` nebo PHP příkaz `php artisan farm:howto-video <json>`, který z toho postaví 1080p
  i Short přes ffmpeg (filtry jako `TimelapseCutter`). Další instruktážní videa (QR cedulka, busta, stojánek) pak
  budou jen nový JSON.
- Ke schválení jako u časosběrů: `FarmVideo` druhu **`howto`** bez zakázky (`path`, `meta` s názvem videa a odkazy;
  druhy už umí `weekly`), zařazené příkazem; `/admin/youtube` ho ukáže s náhledem a tlačítkem; po schválení upload
  stávajícím tokem, FB/IG přes `social:videos` jen Short.
- Test: příkaz z JSONu se dvěma syntetickými segmenty vyrobí seznam filtrů a (s ffmpeg) soubor správné délky; druh
  `howto` v administraci.

## 3. Hotovo

Soubory v `out\`, `docs/AK.md` (co je kde, délky, co chybí: telefon, model), větev s JSON, skriptem, druhem `howto`
a testy, `pint`, `tsc`, `build`, zpráva řídící session. Roman video posoudí a schválí v administraci.

## 4. Doplněno 18. 10. (řídící)

- Záznam: 1920 × 1080, 60 fps, 4 min 44 s, zvuk AAC (nepoužít). Prohlížeč není v F11 (záložky a lišta jsou vidět);
  v sestřihu ořízni obraz na obsah okna, nebo nech, ať je vidět adresa.
- Model: **Cell Phone Stand**, autor **Pit Demon** (@Demon), https://www.printables.com/model/767024-cell-phone-stand,
  licence **CC0 Public Domain** (komerční využití i remix dovolený, uvedení původu nepovinné). Do popisu videa:
  „Model: Cell Phone Stand od Pit Demon (Printables, CC0)“ s odkazem.
- ffmpeg na PC: `C:\Users\roman\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-9.0.2-full_build\bin\ffmpeg.exe`
  (v nových shellech i jako `ffmpeg`).
