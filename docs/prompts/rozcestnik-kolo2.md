# Rozcestník, 2. kolo: méně člověka, nástroje pro obrázek, „Zkusit hned bez návrháře“ (TODO #80)

Pro session 3, 9. 11. 2026, navazuje na #78 (`docs/prompts/rozcestnik-hlavni-strana.md`, docs/AN.md §22, nasazeno
9. 11. ráno, server a5d462c6). Roman při prvním testu (7 odeslání, viz TODO 78): tři odeslání otevřela nástroj s fotkou
a rovnou vyrobila tvar, ale **u známých obrázků (obraz, motiv, logo) asistent dvakrát poslal zadání člověku**; až když
Roman do textu napsal „bez návrháře“, dostal nástroj. Roman: „návrháře by to nemělo nabízet tak často, a když, tak přidat
tlačítko, které to zkusí bez něj“. Slovník stránky po testu: **návrhář** (ne modelář, ne grafik), tlačítko **Vytvořit**.

Worktree `C:\matplace-edit-wt` (čistý po #78), nová větev `feature/home-router-2` z `origin/main` (`git fetch origin`).
Stejná pravidla jako v #78: nic nenasazovat, žádný `migrate*` ve worktree, git add jen jménem, žádný stash, commit
s `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`, texty přes `lang/src/home_router.json` + `lang_add.py`
(nový klíč přidej do téhož souboru; soubor je zdroj pravdy, hodnoty v `lang/*.json` nepřepisuj ručně), každý dotčený
JSON přes `json.load` s kontrolou duplicit, PHPUnit po jedné sadě, pint, `npm run build`, `.env` a klíče neměnit. Skutečné
API nevolej v testech; na vývoj klíč ve worktree není, pracuj s `FakeAssistant` a zlatým souborem a napiš to.

## Proč to dnes posílá člověku

- `IntakeRouter::TOOLS` zná 8 nástrojů (figure, pet-figurine, charm, medallion, map, cake-topper, coaster, earrings).
  Nástroje, které bereme **obrázek jako obrázek**, v katalogu chybí nebo jsou slabě popsané: `filament-art` (Obraz
  z filamentu), `papel-picado` (portrét vystřihovánkou), `sign`/`text` (cedulka, nápis), `keychain`, `cookie-cutter`
  (vykrajovátko z obrysu), `gingerbread`, `compose`. Známý obraz nebo motiv tak nemá kam jít a padá do `shape` → člověk.
- `shape` = fáze 1 → člověk vždy (`wants_shape`). Každý „tvar bez šablony“ tedy znamená e‑mail a čekání.
- `MIN_CONFIDENCE = 0.6`: nástroj s nižší jistotou jde k člověku místo „nástroj + otázky“.
- Věty asistenta občas nesou vnitřní klíč („navrhuji nástroj figure“), viz první ostré rozhodnutí 9. 11. 05:45.

## Co udělat

### A. Katalog a pravidla

- Doplň do `IntakeRouter::TOOLS` nástroje pro obrázek s popisem vstupu a výstupu: `filament-art` (fotka/obraz → obraz
  z vrstev filamentu, A5–A3), `coaster` (obrázek → podtácek), `earrings`, `charm`, `papel-picado` (portrét), `sign`
  a `text` (nápis, cedulka), `cookie-cutter` (obrys), `keychain`. U každého: co potřebuje (fotka / kresba / text),
  rozsah velikostí, počet barev, a kdy ho zvolit. Totéž je whitelist pro předání (`IntakeHandoff`), takže ověř u každého
  přidaného nástroje, že `intake_preload.ts` fotku vloží (`data-intake-photo`), nebo označ vstup.
- Pravidla v `system()`: **obrázek, kresba, malba, logo, známý motiv nebo postava → nejbližší nástroj pro obrázek**
  (plochý motiv: coaster / filament-art / charm / earrings / sign; portrét člověka: figure nebo papel-picado), nikdy
  člověk. **Člověk jen pro díly s rozměry, tolerancemi, závity, „aby pasovalo“, a pro věci, kde žádný nástroj nedává
  smysl ani s otázkami.** `shape` jen pro skutečně prostorovou věc bez šablony (soška zvířete, které není mazlíček,
  hračka, maskot), a i tehdy ať model vyplní `alternatives` (viz níže). Když si není jistý mezi nástroji, zvolí nástroj
  a položí otázky; jistota pod prahem u nástroje už neznamená člověka.
- `MIN_CONFIDENCE` 0.6 → 0.45 a změna významu: pod prahem se u `tool` nemění cesta, jen se do odpovědi přidá
  `questions` (když model žádné nedal, doplň výchozí „Jak velké to má být?“ / „Kolik barev?“). Člověk zůstává pro
  `route = human` od modelu, pro rozbitou odpověď a pro chybu API.
- Schéma odpovědi: nové pole `alternatives` (seznam až tří klíčů nástrojů, které by také šly, seřazené), a `reason`
  (jedna věta pro administraci, proč tahle cesta). Pozor na limit **16 polí s typem null/union** v celém schématu (API
  vrací 400, viz TODO 79): spočítej je po úpravě a napiš číslo do zprávy.

### B. Tlačítko „Zkusit hned bez návrháře“

- U odpovědi s cestou `human` nebo `shape` přibude vedle „Odeslat poptávku“ tlačítko `router.act.without_designer`
  („Zkusit hned bez návrháře“ / „Try it now without a designer“ / „Probar ahora sin diseñador“).
- `POST /intake/{token}/reroute` (stejný `throttle:intake`, počítá se do 5/h): router zopakuje rozhodnutí s pokynem
  „člověka ani tvar nenabízej, vyber nejbližší nástroj z katalogu a řekni zákazníkovi, co se změní (zploštění do reliéfu,
  jen obrys…)“. Když model přesto vrátí `human`/`shape`, vezmi první z `alternatives` původní odpovědi, jinak podle
  druhu vstupu: fotka člověka → `figure`, zvíře → `pet-figurine`, jiný obrázek → `coaster`, jen text → `sign`.
  Výsledek se uloží k témuž intake (`answer.history[]` s časem a původem `reroute`), `route`/`tool_key`/`params` se
  přepíšou, `status` zůstane. Událost `intake_rerouted {from, to, tool}`.
- Stránka vymění bublinu asistenta za novou odpověď (stejný `render`), bez nového odeslání formuláře.

### C. Věty bez vnitřních názvů

- V `system()`: „nikdy nepiš klíč nástroje; nástroj jmenuj veřejným názvem z katalogu v jazyce zákazníka“. V `decide()`
  pojistka: každý výskyt klíče z `TOOLS` v `summary` nahraď názvem nástroje (`__('tools.<key>.title')` nebo jak jsou
  názvy uložené). Test na to.

### D. Drobnosti z testu

- Text v jiném kódování než UTF‑8 → 422 s `router.text_invalid` („Text se nepodařilo přečíst, zkuste ho napsat
  znovu.“), ne 500 (`mb_check_encoding`).
- Administrace `/admin/intake/{id}`: ukázat historii rozhodnutí (původní + po tlačítku) a `reason`.
- Trychtýř: řádek „bez návrháře“ (kolikrát nabídnut člověk, kolikrát zákazník stiskl tlačítko, kam to pak šlo).

### E. Testy

- `tests/fixtures/intake/cases.json` rozšiř o obrázkové případy (známý obraz → coaster/filament-art, logo oddílu →
  charm, dětská kresba → charm/earrings, portrét → figure/papel-picado, soška draka → shape s alternativami, kolečko
  do myčky → human) a projeď je náhradou i `decide()` nad falešnými odpověďmi modelu.
- `IntakeRerouteTest`: human → reroute → tool; model trvá na human → fallback podle alternativ a druhu vstupu; limit
  5/h platí; událost zapsaná; admin vidí historii.
- Sanitizer vět, práh 0.45 s otázkami, 422 u špatného kódování. Stávající `IntakeTest`, `HomeRouterPageTest`,
  `IntakeToolPreloadTest` (rozšířit o nové nástroje) zelené.

### F. Zpráva řídící

Větev a SHA, co je hotové z A až E, počet union polí ve schématu, nové klíče textů, co má rozhodnout Roman (práh 0.45,
zda „shape“ nechat jako cestu nebo ji do fáze 2 schovat úplně za nástroje, text tlačítka). Dokumentace do docs/AN.md
§22 (nová podsekce 22.11 „2. kolo“).
