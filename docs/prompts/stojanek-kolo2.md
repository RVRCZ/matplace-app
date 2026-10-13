# Zadání pro session B (geometrie) a D (návrhář): stojánek na telefon, kolo 2 – výřezy a čtyři nové typy

Od Romana (13. 10. 2026, po zkoušce stojánku podle popisu): „stojánek dobrý; u všech typů s opěrkami možnost přidat
výřez ve tvaru, který lze vybrat nebo nahrát (kočka, pejsek, …); přidej ještě typy stojánku z přiložených obrázků.“
Obrázky jsou v `docs/img/stand2/` (1–5), popis níže.

Cíl: `/tools/phone-stand` umí (a) výřez tvaru v opěrce u typů, které opěrku mají, (b) čtyři nové typy. Vše
tisknutelně bez podpěr, s náhledem a cenou jako dnes, a tak, aby to návrhář z popisu (`DesignAgent`) uměl zvolit.

## 0. Kdo co dělá a pravidla

- **B** (geometrie, konfigurace, texty, testy): worktree `C:\matplace-papel-wt`, nová větev **`feature/stand-round2`**
  z aktuálního `main`; dokument **`docs/AA.md`**. Soubory: `engines/python/param_tool.py` (jen část `phone_stand`
  a pomocné funkce k ní; `stand_kinds.py` a `shape2d.py` jen použít, případně rozšířit o obecnou funkci bez změny
  chování tří stojánků se siluetou), `app/Domain/Tools/ParametricGenerator.php` (položky `phone_stand` v `SPEC`,
  `CHOICES`, `FLAGS`, `WHEN`, `ARTWORK`, `SAMPLE`, `PARTS`), `config/tools.php` (`phone_stand`: `seo.examples`,
  `card`), texty přes `lang/src/<soubor>.json` + `python scripts/lang_add.py …`, testy `ParametricToolsTest`
  (+ nový `PhoneStandTest`). **Nesahat na `resources/js/calc/param.ts`** (vlastní D) – když by stránka něco
  potřebovala, napiš řídící session, co a proč.
- **D** (po slití B): `App\Domain\Tools\DesignAgent::KNOW['phone_stand']` – popis čtyř nových typů a pravidla
  výřezu; schéma odpovědi o `cutout`, `artwork` (jen knihovní odkazy `lib:…`, nahrání agent nenabízí), `pattern`,
  `stripe`; ověřit v prohlížeči, že návrh s `artwork` zapne na stránce výběr tvaru (param.ts) a náhled se překreslí;
  pět nových přání do `docs/Y.md` („stojánek jako kočka“, „s výřezem srdce“, „vlnitý plech pro tablet“, …).
- Řídící session (`conceprt19092026-5c`) slévá a nasazuje; nikdo nemerguje ani nenasazuje. Commity po celcích,
  push, zpráva řídící session po každém kole. Pravidla pro všechny session platí (texty jen v lang, `git add`
  jmenovitě, žádný stash, jedna session na worktree, nikdy `taskkill /F /IM python.exe`, `PYTHON_BIN` v .env).
- Dnešní typy (`plate`, `wave`, `desk`, `wedge`, `wall`, `car`) **se nesmí změnit**: test, který pro každý z nich
  s výchozími parametry porovná vygenerované STL před a po (hash nebo objem + bbox + počet trojúhelníků).

## 1. Co už je a na co navázat

- `param_tool.py::phone_stand`: boční profil (`M.CrossSection`) vytažený přes šířku; typy `plate` (deska s háčky,
  tiskne se nastojato), `wave`, `desk` (A-rám, má `window` – obdélníkové okno v opěrce), `wedge`, `wall`, `car`.
  `lean()` drží úhel v mezích typu a hlásí `param.warn.stand_angle_*`.
- Siluety: `ParametricGenerator::ARTWORK` (nástroje s tvarem z knihovny `lib:<kategorie>/<slug>` nebo z nahraného
  SVG/PNG; `Artwork::REF`, `artworkPath()`), `SAMPLE` (výchozí tvar, např. `lib:animals/cat`), `shape2d.load()`
  (SVG i rastr → CrossSection, `fill_holes`), `shape2d.printability()`, `stand_kinds.py` (silueta s patkou do
  drážky podstavce: `_figure`, `_slot`, `_together`; díly `body` + `stand`, každý svou barvu). Stránka pro nástroje
  v `ARTWORK` nabízí výběr z knihovny a nahrání sama (param.blade/param.ts, nic nového).
- Dvoudílné nástroje a barvy: `PARTS`/`partsOf()` na serveru, krok „Barvy“ a pohledy po dílech na stránce (D to
  13. 10. opravila pro tři stojánky, `partsNow()` v param.ts – **nový dvoudílný typ musí být i tam**, to zadá
  řídící session D).

## 2. Výřez v opěrce (typy `plate`, `wave`, `desk`, `wall`; `wedge` a `car` opěrku nemají)

- Nové parametry: `cutout` (volba: `none` | `window` | `shape`; dnešní `window` u `desk` přejde pod `cutout=window`
  a zůstane výchozí jen tam, kde bylo), při `shape` navíc `artwork` (knihovna/nahrání, `phone_stand` do `ARTWORK`,
  `SAMPLE` = `lib:animals/cat`) a `cutout_size` (% šířky opěrky, 30–75, výchozí 55).
- Tvar jen vnější obrys (`fill_holes=True`): vnitřní ostrůvky (oči) by vypadly. Umístění: vycentrovat v opěrce
  nad sedlem, okraj rámu ≥ max(6 mm, 2 × tloušťka) na všech stranách; výška výřezu ≤ 70 % výšky opěrky, jinak tvar
  zmenšit (a říct to `param.warn.stand_cutout_small`). Tenké partie tvaru (ocas, tlapky) pod 2 × tryska → varování
  `param.warn.stand_cutout_fine`, ne chyba.
- Stabilita: u `plate` zůstává žebro (fin) celé; u `wave` výřez jen v rovné části opěrky, ne v ohybu; u `wall`
  v zadní desce nad kapsou (otvory na šrouby mimo výřez).
- Texty: `param.f.phone_stand.cutout`, `.cutout_size`, volby `param.choice.phone_stand.cutout.*`, varování.
- Příklad do `seo.examples`: `desk` s kočkou.

## 3. Nové typy (přidat do `CHOICES['phone_stand']['style']`, názvy + popis v lang)

1. **`ribbed` – vlnitý plech do S** (obr. 1): jako `wave`, ale pás má v bočním profilu drobné vlny (rozteč ~6 mm,
   amplituda ~1,2 mm, hřebeny napříč šířkou) na obou stranách pásu; zůstává jeden profil vytažený přes šířku,
   tiskne se naležato na boku bez podpěr. Parametry jako `wave` (+ `rib_pitch` 4–10 mm, výchozí 6). Hřebeny musí
   zůstat tisknutelné (žádné převisy > 45° při tisku na boku: vlny jsou v rovině profilu, to platí samo).
2. **`flatpack` – plochá deska se vzorem a zásuvnou nohou** (obr. 2 a 3): dva ploché díly tištěné naležato
   (díly `body` = deska, `stand` = noha; každý svou barvu jako u siluet). Deska: obdélník `width × back` s okrajem
   ≥ 6 mm, uvnitř vzor `pattern` (volba: `circles` – kruhy různých velikostí jako na obr. A, `hearts`, `squares`
   se zaoblením, `honeycomb`), dole lišta/háček na telefon (výška ≥ device + 3 mm), v dolní třetině drážka na nohu;
   noha: trojúhelníková vzpěra se zářezem, zasouvá se do drážky a drží desku v úhlu `angle` (45–75°). Vůle v drážce
   0,25 mm jako `stand_kinds.PLAY`. Velikost telefon/tablet = `width` + `back`.
3. **`ribbon` – zaoblená stuha do Z ve dvou barvách** (obr. 4): tlustý pás (tloušťka 8–14 mm, zaoblení = polovina
   tloušťky) ohnutý do Z: základna → opěrka nahoru a dozadu → přední lišta; díl `stripe` = vnitřní tenčí pás
   (šířka ~30 % tloušťky) uprostřed profilu jako druhá barva (díly `body` + `stripe`, oba jeden profil → v `all`
   leží oba na desce, v `use` jsou spojené; tiskne se naležato na boku, dvoubarevně po dílech nebo jednobarevně,
   když si zákazník druhou barvu nevybere). Parametry: `width`, `device`, `angle`, `thickness` (8–14).
4. **`silhouette` – silueta zvířete s místem na telefon** (obr. 5): tvar z knihovny/nahrání (`artwork`, výchozí
   `lib:animals/cat`) jako tlustý obrys (prstenec `thickness` 4–8 mm) tištěný naležato na čelní ploše, výška
   (= šířka stojánku napříč) `width` 15–40 mm; dole rovná základna (spodek siluety seříznout, délka základny ≥ 45 %
   výšky, jinak základnu prodloužit patkou), vpředu sedlo na telefon: zářez do prstence šířky `device + 1` mm
   pod úhlem `angle` (60–75°) a lišta vpředu, aby telefon nesjel; telefon se opírá o záda siluety. Co nejde (tvar bez
   dost vysokých „zad“ nebo s moc tenkým obrysem) → `param.warn.stand_silhouette_*` a nejbližší použitelná úprava
   (zvětšit tloušťku prstence), ne chyba. Vyzkoušet na kočce, psovi, srdci a na nahrané fotce siluety.

U každého typu: `seo.examples` (1 příklad), `param.style.phone_stand.<typ>` název + věta (cs/en/es), `WHEN`
(která pole se u typu ukazují), `KNOW` pro D (viz 0), a poznámka do AA.md, jak se tiskne a kde jsou meze.

## 4. Testy a hotovo

- `PhoneStandTest`: šest dnešních typů beze změny (viz 0); každý nový typ se postaví s výchozími parametry
  (vodotěsný, bez NaN, bbox v mezích, počet dílů podle typu); výřez na každém typu s opěrkou s `lib:animals/cat`
  i s nahraným PNG; `cutout_size` mimo meze → 422; `flatpack`/`ribbon` vracejí díly `body`+`stand` / `body`+`stripe`
  a `all`/`use` pohledy; `ToolPageTest` zelený; `ParametricToolsTest` zelený.
- V prohlížeči (headless): u každého nového typu náhled a cena, u `desk` výřez kočky; snímky do `docs/img/stand2/out-*.png`.
- **Hotovo (B)** = push `feature/stand-round2`, AA.md, zpráva řídící session s tím, co má D doplnit (KNOW, partsNow).
- **Hotovo (D)** = KNOW + schéma + ověření v prohlížeči, Y.md, push `feature/stand-designer-2`.

## 5. Co není v zadání

- Nové fotky karet (E, až to bude nasazené). Zrychlení náhledu. Jiné nástroje.
- Agent navrhující vlastní tvary mimo knihovnu (agent vybírá jen z `lib:`).
