# Ozdoba na brčko: text podél brčka, otočení textu, výchozí návrh, průměr brčka (session G)

Zadal Roman 29. 10. 2026 večer u `/tools/straw-topper` (nástroj `straw`, rodina `shape`, mimo katalog: `available => false` v `config/tools.php`, docs/P.md „Ozdoba na brčko“): „Pokud se zvolí text, dát možnost změnit orientaci; startovací návrh dát podél brčka, ale aby se dostatečně dotýkal, aby to drželo; průměr brčka jsem nenašel volitelný.“ Řídící session: `conceprt19092026-91`. Pravidla v `docs/prompts/koordinator.md`.

## 1. Co je dnes

- Ozdoba je destička z obrázku nebo jména (`line1`, `line2`, tvar `image` nebo `circle`), na obrysu klip: trubka kolem brčka, vůle 0,4 mm, stěna 1,6 mm, délka 14 mm, otevřená nahoře na 60 % průměru, vždy svisle vedle obrázku (`engines/python/shape_kinds.py`: `_clip`, `_placed`, větev `product == "straw"` ~ř. 630). Klip se v náhledu táhne po obrysu (`eye_pos`).
- Text běží vodorovně, brčko svisle vedle něj: ozdoba se brčka dotýká jen klipem.
- Pole `straw_d` „Průměr brčka“ 5 až 14 mm (krok 0,5, výchozí 8) je v části Rozměry pod šířkou a tloušťkou; Roman ho nenašel.
- Nástroj nikdo netiskl: P.md „K ověření tiskem“ (drží klip, nepraská, převis horních okrajů trubky).

## 2. Cíl

1. **Orientace textu.** Když je zadané jméno místo obrázku, nová volba „Text podél brčka“ / „Text napříč“ (choice, třeba `text_dir` = `along` | `across`, viditelná jen s textem, `WHEN`). Podél: text běží po délce brčka (svisle, čte se shora dolů nebo zdola nahoru, zvol tak, aby se při pití četl správně a napiš proč). Napříč: dnešní stav.
2. **Držení podél brčka.** Ozdoba s textem podél brčka leží po jeho délce a dotýká se ho tak, aby držela: ne jeden klip 14 mm v jednom bodě, ale klip prodloužený po délce textu, nebo dva klipy na koncích, nebo žlábek po celé délce hrany s klipy na koncích. Vůle 0,4 mm a stěna 1,6 mm zůstávají, otevření nahoře stejné. Navrhni, zdůvodni, zapiš rozměry. Platí i pro obrázek, když je „podél“ zvoleno.
3. **Výchozí návrh.** Stránka se otevírá jako hotová věc (TODO #52): jméno podél brčka, klipy drží, barvy předvybrané. Mechanismus: `PRESETS['straw']` + `->defaults('preset', …)` na route jako u podtácku (`golf`) a krabičky (`lidded`). Jméno zvol krátké, které se tiskne i při 12 znacích (`TEXTS['straw']`), třeba „Ela“ jako u jmenovky.
4. **Průměr brčka.** Pole dej do první části hned pod vstup a nad něj chipy běžných brček: 6 mm (tenké), 8 mm (běžné), 12 mm (bubble tea), posuvník zůstává pro jiné. Tip stránky (`straw.tip`) ať řekne, že vůle je 0,4 mm a že se průměr měří vnější.
5. Nástroj zůstává mimo katalog, dokud Roman nevytiskne zkoušku. Připrav mu zkušební kalkulaci (odkaz na `/c/…` s výchozím návrhem pro 8 mm brčko) a napiš, co má při zkoušce sledovat.

## 3. Kde a jak

- Session G, worktree `C:\matplace-guest-wt`, větev `feature/straw-along` z main. Soubory: `app/Domain/Tools/ParametricGenerator.php` (FIELDS, CHOICES, TEXTS, WHEN, PRESETS pro `straw`), `engines/python/shape_kinds.py`, `routes/web.php` (route `tools.straw`), `lang/src/*.json` přes `python scripts/lang_add.py` (cs/en/es, česky krátce, bez pomlček), `resources/views/tools/param.blade.php` jen pokud chipy průměru nejdou bez něj.
- Zápis: nová kapitola na konec `docs/P.md` („Brčko: text podél, klipy po délce, průměr“), co je ověřené a co čeká na tisk.
- Testy: `ParametricToolsTest`, `ToolPageTest` (předvolba se staví), nový test na `text_dir` a na délku klipu; `vendor/bin/pint --test`, `npx tsc --noEmit -p tsconfig.json`, `npm run build`, snímek náhledu do `docs/img/`.
- Po pushi zpráva řídící session: větev, commit, co je ověřené, odkaz na zkušební kalkulaci pro Romana. Nic nenasazuj.
