# AA · Stojánek na telefon, kolo 2: výřez tvaru v opěrce a čtyři nové typy (session B, 13. 10. 2026)

Zadání `docs/prompts/stojanek-kolo2.md` (Romanovy obrázky `docs/img/stand2/1–5`). Větev `feature/stand-round2`
z main d87e43d, worktree `C:\matplace-papel-wt`. Snímky výsledků z lokální stránky v `docs/img/stand2/out-*.png`.
`param.ts` jsem nesahal; co má stránka doplnit, je v sekci 6 (pro D).

## 1. Co vzniklo

**Stroj.** Nový modul `engines/python/stand2.py` (výřez, vlnitý plech, deska s nohou, stuha, silueta); v
`param_tool.py` jen napojení v `phone_stand()` (volba `cutout`, čtyři nové větve `style`) a meze nových polí v
`LIMITS`. Šest typů kola 1 jde touž cestou jako dřív: s výchozími parametry jsou STL **bajt po bajtu stejné**
(sha1 shodné pro `all` i `use`, ověřeno před/po), test `PhoneStandTest::test_the_six_stands_of_round_one_are_what_they_were`
drží bbox, objem a počet trojúhelníků z 13. 10. (tinker přes `build()`).

**Server.** `ParametricGenerator`: `CHOICES['phone_stand']` = `style` + čtyři typy, `cutout` (none | window | shape),
`pattern` (circles | hearts | squares | honeycomb); `FIELDS` `cutout_size` 30–75 % (55), `rib_pitch` 4–10 mm (6),
`band` 8–14 mm (10), `across` 15–40 mm (25); `FLAGS` bez `window`; `WHEN` pro všechna pole podle typu; `ARTWORK` +
`phone_stand`, `SAMPLE` = `lib:animals/cat`; `PARTS` + `stripe`; `partsOf('phone_stand')` → flatpack `body`+`stand`,
ribbon `body`+`stripe`. `clean()`: bez `cutout` dostane A‑rám `window` (a starý přepínač `window=false` → `none`),
ostatní `none`; tvar nebo silueta bez obrázku dostanou kočku z knihovny. `forTool()`: stojánek nevyžaduje obrázek
(jiné nástroje z `ARTWORK` ano). `config/tools.php`: 5 nových ukázek (A‑rám s kočkou, plech, deska se srdíčky,
stuha, silueta psa). Texty `lang/src/stand2.json` (30 klíčů cs/en/es, slité do `lang/*.json`).

**Stránka** (`param.blade.php`, jen přidáno): klíče varování a názvů dílů do seznamu i18n, `$warnAt` pro čtyři nová
varování, jednotka `%` u `cutout_size`. Výběr tvaru (knihovna/nahrání) se ukazuje jako u ostatních nástrojů
s obrázkem – u stojánku vždy, i když ho typ nepotřebuje (viz sekce 6).

## 2. Výřez v opěrce (`cutout`)

- Typy s opěrkou: `plate`, `wave`, `desk`, `wall`. `none` | `window` (dnešní okno A‑rámu; u ostatních typů nic) |
  `shape` (obrys z knihovny nebo nahraný, `artwork`, výchozí kočka) + `cutout_size` = % šířky opěrky.
- Tvar = **jen vnější obrys** (`shape2d.outer_ring` po `load(fill_holes=True)`): oči a vnitřní ostrůvky by vypadly.
  Šířka = podíl šířky opěrky, nejvýš šířka − 2 × rám; rám = max(6 mm, 2 × tloušťka). Výška ≤ 70 % výšky opěrky a
  ≤ výška − 2 × rám; co je větší, se zmenší a řekne `stand_cutout_small`. Tenké partie (`printability` pod 0,8 mm
  > 15 %) → `stand_cutout_fine`. Pod 8 mm se výřez nedělá (`stand_cutout_small`).
- Umístění: `plate` – uprostřed desky, řeže se jen deska, žebro (fin) i háčky zůstávají celé; `wave` – jen v rovné
  části opěrky (od R1 + t nad ohybem po back − t), ne v ohybu; `desk` – uprostřed opěrky, A‑rám **zůstává
  otevřený** (okno) a tvar je v jeho zadní desce, vidět skrz okno; `wall` – v zadní desce nad kapsou, se šrouby
  jen mezi sloupci otvorů (výřez je pak malý, 18 mm, a varuje).
- Geometrie: tvar se postaví naplocho, vytáhne o t + 2 mm, otočí na hranu (`rotate [90,0,0]`), nakloní o úhel
  opěrky (`rotate [0,0,a]`) a posune na přední líc opěrky; odečte se od tělesa v tiskové poloze, před otočením.
- Kam smí sahat knihovna: generátor nic neomezuje (`Artwork::REF`, celá `Artwork::library()`); pro výřez a siluetu
  dávají smysl kategorie **animals, hearts-stars, nature, holidays, transport, sport, jobs** (uzavřené siluety);
  **colour** (vícebarevné obrázky), **cookies** (vykrajovátka) a **letters-numbers** (tenké glyfy) ne. D ať nabídne
  agentovi jen ty vhodné.

## 3. Nové typy

| typ | jak se tiskne | díly | parametry | meze |
|---|---|---|---|---|
| `ribbed` vlnitý plech | naležato na boku, bez podpěr | 1 | width, device, angle (≥ 55), back, thickness, cable, rib_pitch | jako `wave` |
| `flatpack` deska s nohou | oba díly naplocho | body (deska) + stand (noha) | width, back, device, angle 45–75, thickness, pattern | drážka na nohu v dolní třetině |
| `ribbon` stuha do Z | naležato na boku, drážka nahoru; proužek naplocho vedle | body + stripe | width, device, angle (≥ 55), back, band 8–14 | proužek 1,5 mm do drážky 1,6 mm |
| `silhouette` silueta | naležato na čelní ploše | 1 | artwork, back (výška), across 15–40, device, angle 60–75, thickness (prstenec 4–8) | viz níže |

**Vlnitý plech.** Stejná osa jako vlnka (opěrka, ohyb R1, podlaha, přední rulička, sedlo) a na obou plochách pásu
půlkulatá žebra 1,2 mm vysoká každých `rib_pitch` mm (stejná místa na obou stranách, hřebeny napříč šířkou).
Odchylka od zadání („pás má v profilu drobné vlny“): tlustý pás 5 mm nemůže sledovat sinusovku s roztečí 6 mm –
vnitřní strana vlny by měla poloměr 0,76 mm a lámala se do špiček; proto rovný pás + žebra (`corrugated()`), což
dává stejný dojem jako obrázek 1 a tiskne se bez převisů (žebra leží v rovině podložky). Přední rulička je jeden
spojitý profil (oblouk přes přední stranu, pak schůdek 2,5 mm na sedlo se zaoblenými rohy); vnitřní kout mezi
opěrkou a podlahou vyplňuje `_corner_patch` (uzavírací offset by žebra zahladil). Ukázka: 70 mm, 1 h 15 min, 48 g.

**Deska se vzorem a nohou.** Deska `width × back × t`, okraj max(6, 2t), dole polička pro telefon (`device` + 4 mm
vysoká, 4 mm tlustá, s lištou vpředu zkosenou pod 45° – tiskne se naplocho bez podpěr), v dolní třetině průchozí
drážka `t + 0,5 × 12,5` mm na nohu (vůle 0,25 jako `stand_kinds.PLAY`). Vzor: dlaždice na mřížce uvnitř okraje
nad poličkou a kolem drážky (pás 4 mm), stojiny ≥ 2,4 mm: kruhy čtyř velikostí (r 5 / 3,4 / 4,4 / 2,8, rozteč
12,5 × 11 prostřídaně), srdíčka 9 mm, čtverce 8 mm zaoblené, šestiúhelníky 9 mm. Noha: trojúhelník, přední hrana
leží na zádech desky pod úhlem `angle`, zadní patka 22 mm, jazýček 12 mm vysoký prochází drážkou o t + 2 mm; v `use`
je noha 0,12 mm za deskou (dotýkající se stěny by `compose` slil do jednoho tělesa). Ukázka 70 × 100: 43 min, 25 g.

**Stuha do Z.** Pás tloušťky `band` (hull kruhů, konce kulaté = polovina tloušťky): podlaha od přední lišty (výška
max(10, device)) dozadu za těžiště telefonu (75 mm × cos a + 8), opěrka z podlahy nahoru a dozadu; vnitřní kouty
zaobleny 3 mm. Drážka 30 % tloušťky, 1,6 mm hluboká, po celé ose pásu na horní ploše (v tisku), tj. na boku
v užívání; díl `stripe` = tentýž pás o 0,25 mm užší, 1,5 mm tlustý, tiskne se naplocho vedle a zatlačí se do drážky
(druhá barva). Zákazník bez druhé barvy ho vytiskne stejnou barvou nebo vynechá – stojánek stojí i bez něj.
Ukázka: 1 h 18 min, 50 g.

**Silueta.** Vnější obrys tvaru vysoký `back` mm, prstenec `thickness` (≥ 4) dovnitř (`art − art.offset(−th)`);
spodek: nejnižší milimetr tvaru = dosed; je‑li užší než 45 % výšky, základna se protáhne kolem středu; před
tvarem (vlevo) pad 12 mm s klínovým sedlem (hloubka 9 mm, šířka `device + 1`, sklon `angle`, lišta 3 mm vpředu),
telefon se opírá o přední obrys. Vytaženo o `across`. **Přední strana je vždy vlevo** (tvar se neotáčí; pes z
knihovny se tedy dívá od telefonu) – kdyby vadilo, přidat `flip`. Varování: obrys s partiemi užšími než
prstenec → `stand_silhouette_thin` (tam je silueta plná), výška < 60 mm → `stand_silhouette_low`. Vyzkoušeno na
kočce, psovi, srdci a na nahraném PNG (test). Ukázka kočka 100 mm, across 25: 44 min, 26 g.

## 4. Testy

`tests/Feature/PhoneStandTest.php` (4 testy, 152 tvrzení, ~35 s): šest typů kola 1 proti tabulce z 13. 10. (bbox
±0,01, objem ±0,5, trojúhelníky přesně) + výchozí `cutout` podle typu; výřez na každém typu s opěrkou s kočkou
(bbox beze změny, objem menší, `notes.cutout`, rám), `cutout_size` 30 vs 75, 20 a 80 → 422, kapsa se šrouby varuje,
tvar bez obrázku = kočka, nahraný PNG s dírou se řeže jen vnějším obrysem; čtyři nové typy s výchozími parametry
(rozměry, díly `body`+`stand` / `body`+`stripe`, objem `use` = součet dílů, vzory, `band`, `rib_pitch`, `across`,
pes, srdce, meze → 422); stránka nabízí nové typy, `cutout`, `pattern`, `data-when` pro `band` a `across`, žádný
přepínač `window`, žádné syrové klíče, en/es. `ParametricToolsTest`, `StandToolsTest`, `ToolPageTest`,
`ToolsCatalogTest`, `SeoTest`, `ToolCardsTest` zelené.

## 5. Co není ověřené

- **Nic se netisklo.** Nejistá místa: žebra plechu 1,2 mm (tisk na boku: v rovině, mělo by být čisté); lišta
  poličky u desky (45° zkosení); jazýček nohy v drážce (vůle 0,25 – u PETG může být těsná); proužek stuhy 1,5 mm
  do drážky 1,6 mm (po tisku možná lepit); sedlo siluety (klín 9 mm – stejná konstrukce jako nízký klín).
- Stabilita: plech a stuha mají ocas za těžištěm jako vlnka; deska s nohou stojí na spodní hraně desky a patce nohy
  (těžiště telefonu ~22 mm za hranou, patka 60 mm); silueta 25 mm napříč je úzká – široký telefon se může kývat,
  `across` až 40 mm.
- Výřez u `wave` při malé výšce opěrky (back 60): rovná část je krátká, tvar bude malý a ohlásí se.

## 6. Pro D (stránka a návrhář) – co doplnit

1. `partsNow()` v `param.ts`: `phone_stand` → `flatpack` = `body` + `stand`, `ribbon` = `body` + `stripe` (dnes stránka
   ukazuje „2 díly“, ale jednu barvu); názvy dílů jsou v lang (`param.part.body.phone_stand`, `.stand.phone_stand`,
   `.stripe.phone_stand`) a v seznamu i18n v blade.
2. Výchozí `cutout` na stránce: server dává A‑rámu `window`, ale stránka posílá zaškrtnutou volbu (`none` je první) –
   při přepnutí na `desk` předvolit `window`, dokud se uživatel výřezu nedotkl.
3. Výběr tvaru ukázat jen když `cutout = shape` nebo `style = silhouette` (dnes vždy): `data-when` umí jen jeden klíč –
   buď rozšířit o `|` (OR), nebo malý skript v `param.ts`.
4. `KNOW['phone_stand']`: čtyři typy (kdy který: plech = dekorativní, pro tablet širší; deska s nohou = plochý balíček,
   dva díly, vzor; stuha = dvě barvy, těžší; silueta = zvíře/srdce z knihovny, úzká), pravidla výřezu (jen typy s opěrkou,
   `cutout_size` 30–75, jen `lib:` z kategorií v sekci 2), schéma odpovědi `cutout`, `artwork`, `pattern`, `band`,
   `rib_pitch`, `across`.
5. Nové fotky karet (E) až po nasazení.
