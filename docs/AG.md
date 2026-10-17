# AG – blogový článek „Jak vzniká busta z fotky“ (TODO #17)

Session 4, 17. 10. 2026, větev `feature/blog-bust` (z `main` bdbaaaf), zadání `docs/prompts/blog-clanek.md`.
Nahrazuje první článek blogu (PrintPal.io, post id 6). Do produkce ho vkládá řídící session přes
`/admin/content/posts`; nic se nemerguje ani nenasazuje.

## 1. Co je hotové

- `docs/blog/jak-vznika-busta-z-fotky.md`: slug, a pro cs, en, es vždy `title`, `excerpt` a `body` (markdown).
  Tělo začíná `###` (nadpis článku dělá šablona) a obsahuje 6 oddílů, tabulku, 5 obrázků a 3 odkazy. Ověřeno
  převodem přes `Str::markdown(…, ['html_input' => 'strip'])`, stejně jako v `Post::html()`: v každém jazyce vyjde
  1 tabulka, 5 `<img>`, 3 `<a>` a 6 `<h3>`.
- Odkazy: `/tools/figure`, `/en/tools/figure`, `/es/tools/figure` (úvod), `/privacy`, `/en/privacy`, `/es/privacy`
  a závěr s `?utm_source=blog&utm_medium=article&utm_campaign=busta` v jazykové verzi nástroje. Všechny adresy
  vrátily 200 na produkci.
- Rozsah: cs asi 900 slov, en a es asi 1 100 až 1 150 (wc s tabulkou, bez alt textů a adres). Obsah je ve všech
  třech jazycích stejný. Angličtina a španělština jsou prostě delší, proto jsem je nekrátila pod 900 na úkor obsahu.
- Obrázky v `public/img/blog/busta/` (webp, všechny pod 100 kB):

| soubor | rozměr | co to je | zdroj |
|---|---|---|---|
| `obalka.webp` | 1600 × 800 | obálka: tři vygenerované busty vedle sebe | rendery `docs/img/bust-fixes-sample2-after.png`, `-after-antique.png`, `-sample3-after.png` |
| `moderni-busta.webp` | 700 × 700 | moderní busta (sokl), muž s vousy | `bust-fixes-sample3-after.png` |
| `moderni-busta-2.webp` | 700 × 700 | moderní busta, dlouhé vlasy a brýle | `bust-fixes-sample2-after.png` |
| `anticka-busta.webp` | 700 × 700 | antická busta (tabulka a sokl) | `bust-fixes-after-antique.png` |
| `telefon-fotka-{cs,en,es}.webp` | 716 × ~1340 | krok „Fotka“ nástroje na telefonu | snímek produkce 17. 10., 390 px při 2×, cookie lišta skrytá |
| `telefon-podstavec-{cs,en,es}.webp` | 716 × ~1575 | krok „Podstavec“ se jménem Babička / Grandma / Abuela | totéž |

Rendery jsou náhledy modelů z oprav bust (`docs/BUST-FIXES.md`), ne fotky výtisků. Generátor obrázků:
`scratchpad/blog_images.py` (není v repu; obálka je trojice renderů zmenšená na 740 px na pozadí renderu).

**Nepoužité a proč:**
- `bust-fixes-after-front.png` a `-after-side.png`: testovací rendery roztrženého řezu bez hlavy, do článku se nehodí.
- Fotka karty `public/img/tools/figure-800.jpg`: vytvořil ji Gemini (`scripts/card_images.py`, prompt „grey printed
  bust … JAXON“). Není to náš výtisk ani skutečný člověk. V článku o tom, jak busta vzniká, by působila jako fotka
  reálného výtisku, proto jsem ji vynechala.

## 2. Odkud jsou čísla (cena, čas)

Kalkulace na produkci přes `POST /api/calculations` (`material: PLA`, výchozí kvalita, počet 1), 17. 10. 2026
kolem 14:25 UTC po hotfixu ce7d49c. Farma počítá s nejlevnějším založeným materiálem, štítek „Tisk u nás (PLA+)“.
Čas je `slicer.minutes`, tedy to, co ukazuje kalkulace. Řádek ceny má vlastní `minutes` s `time_factor` 1,07
(63/114/169), ten v článku není.

Busta `3591a079-17d0-41f1-b1fd-59ae75df022a` (zakázka 3. 10., moderní busta, 80 mm):

| výška (scale) | g | min | cena | kalkulace |
|---|---|---|---|---|
| 80 mm (1) | 14,3 | 59 | 99 Kč (minimum; mezisoučet 74,85) | https://matplace.com/c/vxauvlvidinz |
| 120 mm (1,5) | 40,1 | 107 | 130 Kč | https://matplace.com/c/gnqnqzws7ahv |
| 150 mm (1,875) | 71,9 | 158 | 200 Kč | https://matplace.com/c/ccwijw6xwnku |

Busta se širšími rameny `fdfff703-bea7-4ac9-80e3-9b7e43fbfe53` (30. 9., bunda): 80 mm 16,5 g / 64 min / 99 Kč
(https://matplace.com/c/dpi1irc9763y), 150 mm 81,7 g / 177 min / 220 Kč (https://matplace.com/c/gqo0rfbaaqnh).
Ani jedna nepotřebovala podpěry (`supports_used: false`). Vrstva 0,2 mm = 80 mm / 400 vrstev.

V článku je „cena tisku u nás, bez dopravy“. Jestli je v ceně DPH, z odpovědi API nepoznám
(`margin_pct` 0), proto to článek neříká. Pokud se ceník farmy změní, je potřeba upravit tabulku a větu
„Ve 150 mm … 200 Kč místo 99 Kč“ (v každém jazyce v oddílech 4 a 5).

Ostatní fakta pocházejí z textů nástroje (`lang/*.json` figure.*, `lang/*/tools_seo/figure.php`), `config/ai.php`
(limity 1/3), `config/uploads.php` (30 dní), `PedestalChanger` (co jde změnit bez generování) a `config/farm.php`
(Zásilkovna, osobní převzetí vypnuté).

## 3. Odchylky od zadání a věci k rozhodnutí

1. **Lidé na renderech.** Obálka a tři obrázky ukazují obličeje ze zakázek na produkci (22. 9., 30. 9., 3. 10.).
   Zadání je jmenuje, ale **před zveřejněním je potřeba ověřit, že vyfocení s použitím v blogu souhlasí** (Roman ví,
   čí jsou). Pokud ne, článek obstojí jen se snímky telefonu a obálkou bez obličejů.
2. **Farma není veřejná** (`farm.public` false, FarmGate::offered jen pro admina). Článek proto říká „Tisk na naší
   farmě zatím otevíráme postupně. Kde už ho kalkulace nabízí, objednáte ho tlačítkem „Vytisknout“…“. Až se farma
   otevře, stačí první větu odstavce smazat (cs/en/es, oddíl 4, poslední odstavec).
3. **Výška** je v nástroji 30 až 250 mm (výchozí 80), ne 80 až 150. Článek uvádí rozsah a 80 až 150 mm jako
   doporučení pro portrét.
4. **„Výběr stylu“**: busta nemá umělecké styly (ty má jen mazlíček), stylem je provedení podstavce (moderní,
   antická, sochařský řez + 6 jednoduchých). Článek to tak popisuje.
5. **Děti**: v nástroji o dětech žádný text není. Článek radí „dítě foťte jen jako jeho rodič, nebo s jeho
   souhlasem“. Návrh pro Romana: doplnit totéž do `figure.consent` nebo do tipů pod fotkou.
6. **Srovnání dvou výtisků**: fotky skutečných výtisků busty nemáme. Článek proto srovnává velikosti 80 a 150 mm
   čísly z kalkulace. Až Roman nafotí výtisk, patří fotka do oddílu 5.
7. **Matný vs. lesklý**: článek doporučuje PLA+ („matný povrch skryje vrstvy, lesklý zvýrazní tvar“), ale matný
   výslovně nepreferuje. Důvod je TODO #13 (podpěry na matných materiálech se rozpadají).
8. **Doprava**: ceny Zásilkovny (99/149 Kč v `config/farm.php`) v článku nejsou, protože nastavení farmy na produkci
   je může přepsat. Článek píše jen „Zásilkovnou na výdejní místo nebo domů“.
9. **Ceny v en/es** jsou v CZK s poznámkou, že kalkulace ukáže i eura (kurz si článek nevymýšlí).

## 4. Vedlejší nález

17. 10. kolem 14:15 UTC selhávala kalkulace na produkci u každého souboru, i u kostky 20 mm („Slice failed
(no gcode): run found error, return -5“). Nahlásila jsem to řídící session, ta to opravila hotfixem ce7d49c
(utržená uvozovka v `engines/orca/profiles/filament_pla.json`) a nasadila. Při ověřování jsem na
produkci nahrála dva soubory přes `/api/uploads` (`tests/fixtures/mold/bust.stl` → 331ae317…, kostka 20 mm →
1fecbef8…). Jsou anonymní, takže je za 30 dní smaže `matplace:prune`. Kalkulace z tohoto ověřování (failed i done)
zůstávají v tabulce calculations. Provoz šel s user agentem obsahujícím `matplace-blog-calc` / `matplace-blog-shot`
(TODO #10, značení našeho provozu).

## 5. Vložení do produkce (řídící session)

1. Nahrát obrázky: `public/img/blog/busta/*` musí být na serveru. Buď s mergem této větve, nebo ručně do
   `/var/www/matplace-app/public/img/blog/busta/`.
2. `/admin/content/posts` → nový článek, formát markdown, slug `jak-vznika-busta-z-fotky`, pro cs/en/es pole
   title/excerpt/body z `docs/blog/jak-vznika-busta-z-fotky.md` (body = vše pod `body:` po další `## `), obálka
   `obalka.webp`.
3. Zveřejnit, zkontrolovat `/blog`, `/en/blog`, `/es/blog` (obálka, tabulka, obrázky) a pak PrintPal (post 6)
   `published_at = null`.
