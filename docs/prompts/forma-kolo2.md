# Zadání: Forma na odlévání, kolo 2 – nástroj podle moldboxer.com, stránka k prezentaci na Googlu a sítích, video

Od Romana (13. 10. 2026): „můžeme naši formu zlepšit a udělat ten nástroj a stránku nástroje podobnou
https://moldboxer.com/? Zadej ho tak, aby se nástroj i se stránkou dal prezentovat na sockách a Googlu, zadej i video
do stránky.“

Komu: té session, která bude dřív volná – **D po filament artu**, nebo **B po stojánku** (řídící session přidělí; do té
doby nikdo nezačíná). Jedna session, jedna větev **`feature/mold-round2`** z main, dokument `docs/AB.md`.

## 0. Pravidla

Pravidla pro všechny session platí (texty jen v lang / `lang/src` + `scripts/lang_add.py`, `git add` jmenovitě, žádný
stash, jedna session na worktree, nikdy `taskkill /F /IM python.exe`, `PYTHON_BIN` v .env, `pint`, `tsc`, `build`).
Soubory: `engines/python/mold_tool.py`, `app/Domain/Tools/MoldGenerator.php`, `resources/views/tools/mold.blade.php`,
`resources/js/calc/mold.ts` (session 0/1 – neběží, smíš), `lang/*/tools_seo/mold.php`, `config/tools.php` (jen
`mold`), `app/Support/ToolSeo.php` a `tools/page.blade.php` jen pro **obecnou** sekci videa (platí pak pro všechny
nástroje). Řídící session slévá a nasazuje. Nic z farmy, kalkulace a YouTube pipeline neměnit – jen použít.

## 1. Co máme (navaž, nepiš znovu)

- **Nástroj** (`mold_tool.py`, docstring): tištěná dělená forma 2–4 díly (dělicí roviny, klíče, nálevka, měření podřezů,
  vyplnění podřezů s náhledem odlitku), forma na silikon (podstavec s modelem + objímka). Stránka: nahrání, analýza
  („forma nepustí :pct %“ s červeným značením), volby typ/stěna/díly/osa/dělení/vyplnit, výsledek, cena a tisk u nás.
- **SEO stránka nástroje**: `lang/*/tools_seo/mold.php` (h1, intro, kroky, FAQ, ukázky kreslené
  `php artisan matplace:tool-examples mold`), strukturovaná data HowTo + FAQPage (`ToolSeo`), sdílecí obrázek
  (`OgController`, z fotky karty). Karta: nová studiová fotka (13. 10.).
- **Video**: časosběry hotových tisků z kamer farmy (`matplace:farm-timelapse`, `TimelapseFrames`, ffmpeg, hudba),
  nahrání na YouTube po schválení (`App\Domain\YouTube\FarmVideos`, docs/L.md), sdílení videí na Facebook/Instagram
  (`matplace:social-videos`, `VideoSharer`), statistiky (`matplace:youtube-stats`), UTM odkazy v popisech. Stránky
  nástrojů video **neumí** (žádná sekce).
- **Čísla**: týdně ~180 lidí, Google roste; nástroj z vyhledávače je nejčastější vstup (docs/Z.md).

## 2. Nástroj – co převzít z Moldboxeru (pořadí = priorita)

1. **Odvzdušnění**: kanálky z nejvyšších míst dutiny (lokální maxima povrchu modelu ve směru lití) k vnějšku formy,
   průměr 2–3 mm, volba `vents` (auto/off); u dělené formy vedou dělicí rovinou, u silikonové nahoru. Bez toho
   odlitky mají vzduchové kapsy – nejčastější důvod zkaženého odlitku.
2. **Typy silikonových forem** (`type = silicone`, nová volba `box`):
   - `shell` – přizpůsobená skořepina: stěna formy kopíruje model v odstupu `gap` (8–20 mm) místo kvádru → úspora
     silikonu (uvést v % proti kvádru); stavba: offset modelu (Minkowski s koulí přes manifold, nebo voxel offset
     z `mesh_tool`) + podstavec s drážkou jako dnes;
   - `tray` – otevřená vanička pro díly s plochou zadní stranou (reliéf, cedulka, podtácek): model leží plochou dolů,
     silikon se lije shora, forma je jednodílná;
   - `two_part` – dvoudílná silikonová forma s dělicí rovinou (volba osy a výšky jako u tištěné), s klíči v silikonu
     (kuželové výstupky na dělicí desce) a tištěným boxem na obě půlky.
3. **Vnitřní jádro** pro duté odlitky (`core` on/off, `core_wall` 3–8 mm): jádro = model zmenšený o stěnu (offset
   dovnitř), uchycené v podstavci; odlitek je skořepina (svíčky, mýdla, figurky z pryskyřice – šetří materiál).
4. **Kalkulačka**: objem silikonu (dutina boxu − model) a objem odlitku (model, u jádra model − jádro) v ml, u nás navíc
   cena tisku formy; ukázat v analýze i ve výsledku; texty `mold.page.volumes`.
5. **Volby pro pokročilé** (sbalené): vůle `clearance` 0,1–0,3, typ klíčů (kulové/kuželové), poloha nálevky
   (dole/nahoře/z boku), tloušťka stěny – stávající meze.
6. Co **nedělat**: sádrové formy (slip casting), přípravky, ochranné krabičky, logo na formě – jiný trh.

## 3. Stránka nástroje – k prezentaci

- **Nahoře** (před formulářem, jen první obrazovka): titulek a jedna věta („Z vašeho modelu forma k tisku: dělená,
  silikonová, nebo vanička. Zdarma, bez přihlášení, cena tisku hned.“), **galerie typů** – 5 dlaždic (dělená 2 díly,
  dělená 4 díly, silikonová skořepina, vanička, dvoudílná silikonová) s obrázky z našeho generátoru
  (`seo.examples` → `tool-examples`, jeden příklad na typ; stejný model – soška/busta – ve všech), kliknutí nastaví typ.
- **Tři kroky** s ikonami: nahrát model → zvolit typ (nástroj poradí podle podřezů) → vytisknout u nás nebo stáhnout.
- **„Který typ zvolit“**: krátká tabulka (tvar bez podřezů / s podřezy / plochá zadní strana / dutý odlitek → typ).
- **Video** (viz 4) v sekci „Jak to vypadá“ – obecná sekce pro všechny nástroje: `config/tools.php`
  `'video' => ['youtube' => '<id>', 'poster' => 'img/tool-video/<tool>.jpg']`; náhledový obrázek, přehrávač se načte až
  po kliknutí (youtube-nocookie, bez cookies před souhlasem), text `tools.video.*`; VideoObject do strukturovaných dat.
- **FAQ** rozšířit: silikon vs. tištěná, kolik silikonu, jak odvzdušnit, jaké materiály lít (pryskyřice, mýdlo, vosk,
  čokoláda – jen potravinářský silikon), kolik odlitků forma vydrží. SEO texty cs/en/es.
- **Sdílecí obrázek**: `OgController` pro mold – obrázek s formou otevřenou a odlitkem vedle (render přes
  `render_tool.py`, nebo Gemini z renderu jako karty), 1200 × 630.
- Telefon: název + věta + galerie nad nahráním (viz docs/Z.md nález N1 – u této stránky to udělej rovnou).

## 4. Video (1 minuta, bez mluvení, titulky cs/en v obraze)

Skládá se ze tří zdrojů, všechny umíme vyrobit sami:
1. **Průchod nástrojem** (20 s): skript `scripts/tool_video.mjs` – headless Chrome / Playwright s `recordVideo`
   1280 × 720: nahrání modelu (soška z `tests/fixtures`), analýza s červeným značením, volba typu, výsledek, cena.
   Zrychlit 2×, ffmpeg. Skript obecný (parametr: nástroj + kroky), aby šel použít pro další nástroje.
2. **Otáčení formy** (15 s): 36 snímků z `render_tool.py` (díly v barvách, otevřená forma a odlitek vedle) → ffmpeg;
   pokud `render_tool` nemá otáčení, přidat parametr úhlu.
3. **Tisk a odlití** (25 s): časosběr tisku formy z farmy (Roman vytiskne jednu formu, pipeline udělá video sama) +
   10 s záběr lití a vyklopení odlitku z telefonu (Roman natočí; zadání mu řekne, co natočit: lití shora, otevření
   formy, odlitek v ruce).
Sestavení: ffmpeg concat + hudba z knihovny farmy + titulky; výstup 1080p mp4 + 1:1 a 9:16 výřezy pro sítě.
Zveřejnění: YouTube přes `FarmVideos` (po Romanově schválení jako u časosběrů), id do `config/tools.php`, Facebook/
Instagram přes `matplace:social-videos` s UTM `utm_campaign=tool_mold`; texty příspěvků cs/en do `docs/social/mold.md`
(3 varianty: „co to je“, „jak to funguje“, „zkuste si“).

## 5. Testy a hotovo

- `MoldToolTest` (stávající + nové): každý typ formy se postaví na sošce z fixtures (vodotěsné, díly se nepřekrývají,
  odvzdušnění ústí ven, objemy v ml ≈ kontrola přes trimesh), jádro = model − stěna, vanička jednodílná; stránka
  zobrazuje galerii a video sekci; `SeoTest` (VideoObject); `ToolPageTest`; `tsc`, `build`, `pint`.
- V prohlížeči na telefonu (390 px): první obrazovka = název, věta, galerie; snímky do `docs/img/mold2/`.
- **Hotovo =** nástroj s odvzdušněním, třemi silikonovými typy, jádrem a kalkulačkou; stránka s galerií, kroky,
  tabulkou, FAQ, videem a sdílecím obrázkem; video na YouTube (po schválení) a texty příspěvků; AB.md.

## 6. Co není v zadání

Sádra/keramika, přípravky, krabičky, logo na formě; automatické plánování příspěvků (Roman klikne „zveřejnit“ jako
u časosběrů); překlad videa do španělštiny (titulky jen cs/en).
