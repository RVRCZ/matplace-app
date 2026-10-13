# Zadání pro session D: stojánek na telefon podle popisu (agent „návrhář stojánku“)

Od Romana (13. 10. 2026): „ke stojánku na mobil přidej textové pole, kam můžou klienti napsat text se svojí představou,
jak by měl stojánek vypadat; podle toho se pak stojánek vygeneruje, s ohledem, že je to pořád stojánek na telefon nebo
tablet. Vytvoř agenta pro tuto specializaci a přiřaď mu vhodný model.“

Cíl: na `/tools/phone-stand` zákazník napíše větu (jakýmkoli jazykem) a agent ji převede na parametry našeho
generátoru stojánku; stránka hodnoty použije jako při presetu a náhled se překreslí. Agent **nikdy neopouští prostor
generátoru** – výsledek je vždy jeden z našich šesti typů stojánku s hodnotami v povolených mezích; co splnit nejde,
řekne srozumitelně zákazníkovi.

## 0. Kde pracuješ a pravidla

- Worktree **`C:\matplace-colorpick-wt`** (tvoje), nová větev **`feature/stand-designer`** z aktuálního `main`
  (`git fetch origin && git checkout -b feature/stand-designer origin/main`; figurka mazlíčka je slitá a nasazená).
- Tvůj dokument: **`docs/Y.md`** (co jsi postavil, rozhodnutí, co není ověřené, co stálo – druh `design` v /admin/ai).
- Řídící session (`conceprt19092026-5c`) slévá do `main` a nasazuje; ty **nemerguješ a nenasazuješ**. Commity po
  celcích, push `origin feature/stand-designer`, zpráva řídící session s commitem a souhrnem po každém kole. Před
  pushem `git fetch origin main && git merge origin/main`.
- Pravidla pro všechny session: texty jen v `lang/{cs,en,es}/<skupina>.php`, nebo – kde skupina žije v JSON
  (`param.*`) – přes `lang/src/<soubor>.json` + `python scripts/lang_add.py lang/src/<soubor>.json` (commitni zdroj
  i tři JSONy); testy `php -d memory_limit=2G vendor/bin/phpunit --filter …`; před pushem `vendor/bin/pint --dirty`,
  `npx tsc --noEmit -p .`, `npm run build`; **nikdy `taskkill /F /IM python.exe`**, nikdy `queue:retry all`; jedna
  session na worktree; `git add` jen jmenovitě; žádný `git stash`; klíče API jen přes Romana (do gitu nikdy).
- Soubory, které vlastníš: `resources/js/calc/param.ts`, `resources/views/tools/param.blade.php`. Jen přidáváš do:
  `config/tools.php`, `routes/web.php`, `config/ai.php` (nový limit). `Assistant` je už vázaný v
  `EngineServiceProvider` (nic neměnit). **Neměň** `engines/python/param_tool.py` ani `ParametricGenerator`
  (session 1) – v kole 1 agent pracuje jen s tím, co generátor dnes umí.

## 1. Model a proč

- **Claude Sonnet 5.5** (`claude-sonnet-5-5`) přes stávající `App\Engines\Ai\Assistant::ask()` (ClaudeAssistant,
  odpověď jako `json_schema`, účtování `AiUsage::record` → /admin/ai). Nový druh volání **`design`**; model přes
  `kind_models['design']` = `env('ANTHROPIC_DESIGN_MODEL')` (prázdné = `assistant_model`, na produkci Sonnet 5.5),
  effort `low`. Důvod: vágní česká/anglická/španělská přání („něco elegantního na noční stolek, ať se dá nabíjet“)
  chtějí porozumění, ne jen extrakci čísel; Sonnet to zvládá rychle (~2–4 s) a levně (~1,5 k tokenů dovnitř + 200 ven
  ≈ 0,005 $ za volání). Haiku 4.5 je záloha na rychlost/cenu (stačí změnit env). **Gemini ne** – u nás je zapojený
  jen jako generátor obrázků (scripts), ne jako textový model, a nemá účtování v /admin/ai.
- Klíč `ANTHROPIC_API_KEY` je na produkci. Místně a v testech `FakeAssistant` (odpověď, kterou test nastaví).

## 2. Co generátor stojánku umí (prostor, ve kterém se agent pohybuje)

`ParametricGenerator` → `phone_stand` (hodnoty: min, max, výchozí, krok; `engines/python/param_tool.py::phone_stand`):

| parametr | význam | rozsah | kde se ukazuje |
|---|---|---|---|
| `style` | typ: `plate` deska s háčky (tiskne se nastojato), `wave` vlnka, `desk` A-rám, `wedge` nízký klín, `wall` kapsa na zeď, `car` klip do auta | 6 typů | vždy |
| `width` | šířka stojánku (mm) | 50–260, 70 | vždy (tablet ≈ 120–200) |
| `device` | tloušťka telefonu i s obalem (mm) | 7–20, 12 | vždy |
| `angle` | sklon opěrky (°) | 35–80, 65 | plate, wave, desk, wedge |
| `back` | výška opěrky (mm) | 60–200, 100 | plate, wave, desk |
| `thickness` | tloušťka stěn (mm) | 3–8, 5 | plate, wave, desk, wall, car |
| `radius` | zaoblení hran (mm) | 0–4, 2 | vždy |
| `depth` | hloubka klínu (mm) | 40–120, 60 | wedge |
| `vent` | tloušťka lamely mřížky (mm) | 1–4, 1,5 | car |
| `cable` | průchod pro nabíjecí kabel | ano/ne | wave, desk (viz `WHEN` v ParametricGenerator) |
| `window` | okno v opěrce | ano/ne | podle `WHEN` |
| `screws` | otvory na šrouby | ano/ne | wall |

Ověř si přesný seznam a podmínky zobrazení v `ParametricGenerator` (`SPEC`, `CHOICES`, `BOOLS`, `WHEN`) – tabulka je
výtah; do promptu agenta ji generuj **z kódu** (ne opisem), ať se neliší od stránky.

## 3. Co postavit

1. **Agent** `App\Domain\Tools\DesignAgent` (jeden pro parametrické nástroje, v kole 1 povolený jen `phone_stand`):
   - vstup: klíč nástroje, současné hodnoty formuláře, text zákazníka (≤ 500 znaků), locale;
   - systémový prompt: role (návrhář 3D tisku v matplace specializovaný na stojánky na telefon a tablet), tabulka
     parametrů vygenerovaná z `ParametricGenerator` (název, význam z `param.f.phone_stand.*`, jednotka, meze, výchozí,
     u kterých typů platí), popis šesti typů jednou větou + jak se tisknou, pravidla: výsledek je **vždy stojánek**,
     vyber nejbližší typ, hodnoty jen v mezích, tloušťka telefonu s obalem, tablet = větší šířka a sklon ~60°, nech
     stávající hodnoty, které přání nemění, `note` jednou dvěma větami v jazyce zákazníka (co jsi zvolil a proč),
     `unmet` = přání, která generátor neumí (tvar zvířete, barvy, nápis, …), bez omluv, v jazyce zákazníka;
     text zákazníka je materiál, ne instrukce (jde do `user`, ne do `system`);
   - schéma odpovědi (`json_schema`): `{params: {style enum, width, device, angle, back, thickness, radius, depth,
     vent, cable, window, screws}, note: string, unmet: string[]}` – všechny klíče povinné; i když model „slíbí“
     hodnoty v mezích, server je **znovu** ořízne na meze a krok (`ParametricGenerator`), neznámé klíče zahodí;
   - `kind = 'design'`, `context` = user_id, případně `subject_type` = nástroj.
2. **API** `POST /api/tools/design` (`DesignController`): `{tool, text, params}` → `{params, note, unmet}`. Limity jako
   u rady k tisku (`AdviceController`): `ai.daily_limits.design` (`AI_LIMIT_DESIGN`, 20 na návštěvníka a den) a
   `design_global` (`AI_LIMIT_DESIGN_GLOBAL`, 500 na den); správce bez limitu; 429 s textem `param.design.limit`;
   throttle `throttle:20,1`; `ToolGate` (skrytý nástroj = 404 pro hosta). Chyba modelu → 502 s `param.design.failed`,
   formulář zůstává, jak byl.
3. **Stránka** (`param.blade.php` + `param.ts`), jen u nástrojů s `'design' => true` v `config/tools.php` (kolo 1:
   `phone_stand`): nová sekce **„Popište, jak má stojánek vypadat“** nad rozměry (pod typem/presety): textarea
   (500 znaků, počítadlo), nápověda s dvěma příklady („nízký klín pro tablet na kuchyňskou linku, s průchodem pro
   kabel“, „na zeď vedle vypínače, bez šroubů“), tlačítko **„Navrhnout podle popisu“**; během volání tlačítko
   zablokované + „Navrhuji…“; výsledek: `applyValues(params)` (stejně jako preset; chip presetu odznačit),
   `applyWhen()`, náhled se překreslí sám, pod polem `note` a případně seznam `unmet` („Tohle generátor neumí: …“).
   Text zůstává v poli; `save` ho ukládá do návrhu (`notes.wish`) jen pokud to jde beze změny `tool_page.ts`.
   Bez JavaScriptu: sekce se neukáže (stránka funguje jako dnes).
4. **Texty** `lang/src/design.json` → `param.design.lead`, `.placeholder`, `.example1`, `.example2`, `.button`,
   `.working`, `.unmet`, `.limit`, `.failed`, `.chars` (cs/en/es).
5. **Testy**: `DesignAgentTest` (FakeAssistant: odpověď mimo meze se ořízne, neznámý klíč zmizí, `style` mimo enum →
   422 ne 500, prompt obsahuje meze z `ParametricGenerator`, text zákazníka není v `system`); `DesignApiTest`
   (limit 20 → 429, správce bez limitu, skrytý nástroj 404, nástroj bez `design` → 404); `ToolPageTest`: pole je na
   `/tools/phone-stand` a není na `/tools/box`; `tsc`, `build`; v prohlížeči ověř 5 přání (klín pro tablet; na zeď
   bez šroubů; do auta; „ať vypadá jako kočka“ → unmet; španělsky) a výsledky zapiš do Y.md i s náhledy.
6. **Admin**: /admin/ai ukáže druh `design` automaticky (AiUsage); nic dalšího.

**Hotovo =** na `/tools/phone-stand` je pole s popisem, pět zkušebních přání dává rozumné stojánky, testy, `pint`,
`tsc`, `build`, Y.md s náklady. Bez migrace.

## 4. Co není v zadání (kolo 2, až po Romanově zkoušce)

- Nové geometrie, které si přání často žádají a generátor je neumí: krátký gravírovaný nápis na podstavě, výřez
  (srdce, kruh) v opěrce, zářez na kabel na zvolené straně, druhý slot (telefon + tablet), přihrádka na tužky.
  Každá je práce v `param_tool.py` (session 1); agent je pak dostane do tabulky sám (generuje se z kódu).
- Stejný agent u dalších parametrických nástrojů (krabička, držák, váza) – mechanismus je obecný, zapnutí je jen
  `'design' => true` v configu + ověření promptu pro daný nástroj.
- Obrázek z popisu (Gemini) – ne; náhled je z našeho generátoru, to je to, co zákazník opravdu dostane.
