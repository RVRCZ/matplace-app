# Zadání pro session 1: medaile s řetězem pro vítěze (nástroj `medallion`, kolo 2)

Od Romana (18. 10. 2026, podle ohlasu na YouTube: video „Medaile s řetězem“ má 933 zhlédnutí, nejvíc ze všech):
„Medaile je moc velká: v návrhu má být hned 60 mm a k tomu článků řetězu aspoň 40. Na medaili má být jednička ve zlaté
silk barvě a jednička (nebo motiv) se má tisknout hladká na podložku; články řetězu zakulacené; u motivu málo vrstev,
aby se neuvařil měnič. Do návrhu hned dát 2 stříbrnou a 3 bronzovou, různé tvary medaile. Je to přece pro vítěze.“

Dnešní stav (tvoje práce, `docs/P.md` §„Medaile s řetězem“, `engines/python/shape_kinds.py`, rodina `shape`): kruh,
hvězda nebo šestiúhelník 50–120 mm s obrázkem, číslem nebo jménem, očko 5–8 mm, 0–40 otevřených článků 30 × 18 mm
s příčkou 4 mm, všechno jeden díl `body` stejně vysoký, barvy změnou po výšce.

## 0. Kde pracuješ a pravidla

- Větev **`feature/medallion-winners`** z aktuálního `main`, dokument **`docs/P.md`** nová část „Medaile, kolo 2“.
- Soubory: `engines/python/shape_kinds.py` (+ testy enginu), `config/tools.php` (`medallion`: card, seo examples),
  `resources/js/calc/param.ts` jen přes stávající mechanismy (předvolby, pokud nástroj umí; jinak `tool_page`
  nesahat, předvolby jako hodnoty parametrů v `config/tools.php`), `lang/src/medallion2.json` + `python
  scripts/lang_add.py`, `tests/Feature/ToolPageTest.php`, `tests/Feature/ToolsFlowTest.php`, obrázky
  `public/img/tool-examples/medallion-{1,2,3}.png` (nové přes `php artisan matplace:tool-examples medallion`, nikdy
  `--card --force`). Nesahat: ostatní nástroje, kalkulace, farma.
- Pravidla: `git add` jmenovitě, žádný stash, `pint --dirty`, `tsc`, `build`, testy (`php -d memory_limit=2G
  vendor/bin/phpunit --filter …`), `PYTHON_BIN` z `.env`; nemergovat, nenasazovat; hotovo = push + zpráva řídící
  session (conceprt19092026-8c).

## 1. Co změnit

1. **Výchozí hodnoty**: průměr **60 mm** (rozsah 40–120), články řetězu **40** (rozsah 0–80; víc než se vejde na
   podložku 220 × 220 → nástroj řekne, kolik se vejde, a zbytek na druhou podložku, nebo omezí; napiš, co jde).
2. **Předvolby umístění** (tlačítka nebo volba `place`): **1. místo** = číslo 1, motiv zlatý (PLA Silk zlatá, kód
   `39_PLA_Silk_zlata_Silk_Gold`), **2. místo** = číslo 2 stříbrné (`43_PLA_Silk_stribrna_Silk_Silver`), **3. místo** =
   číslo 3 bronzové (metalická marrákešská bronzová SUNLU, když ji palette nabízí; jinak PLA Silk měděná
   `40_PLA_Silk_medena_Silk_Copper`); tělo medaile výchozí v tmavé barvě, ať číslo vynikne. Předvolba nastaví číslo
   i barvy, dál jde všechno měnit. Výchozí stav nástroje = 1. místo.
3. **Motiv hladký na podložce**: medaile leží lícem dolů; číslo/obrázek/jméno je ve **spodních vrstvách** (výchozí
   hloubka motivu 0,6 mm = 3 vrstvy po 0,2; volba 0,4–1,0), zbytek destičky, očko a řetěz v barvě těla. Motiv a tělo
   jsou v těch vrstvách dvě barvy vedle sebe → **díly po barvách** (`part_colors`, tisk po dílech / dvě cívky), ne
   změna po výšce; počet vrstev s výměnou drž malý (proto 3). Řetěz i očko se tisknou v barvě těla, bez výměn. Ověř,
   že kalkulace i farma takový návrh berou (dvě barvy ve stejné výšce = `by_parts`), a že se lícová plocha dotýká
   podložky (text zrcadlit, aby po otočení četl správně!).
4. **Články řetězu zakulacené i dole, bez podpěr** (Roman 18. 10. večer): profil článku kruhový ⌀ 4 mm, dole jen
   malá rovná ploška ~1 mm (zaoblení začíná pod úhlem, který FDM utáhne bez podpěr: převis do 50°, u tak malého
   poloměru to drží); po vytištění ploška není vidět. Ověř řezem (Orca v kalkulaci: žádné podpěry, žádná varování
   převisu) a napiš skutečný úhel. Variantu „články stojí na hraně“ nedělej (40 malých dílů na hraně se kácí). Zachovat zacvaknutí (mezera o 0,4 mm užší než příčka; `LINK` v `shape_kinds.py`); oválný tvar článku
   zůstává. Napiš, jak jsi profil udělal (sweep / zaoblení hran) a kolik přidal filamentu na 40 článků.
5. **Tvary medaile**: ke kruhu, hvězdě a šestiúhelníku přidat **štít**, **osmiúhelník**, **rozetu** (vroubkovaný
   okraj) a **srdce**; okraj medaile s lemem 2 mm (lehce vystouplý rámeček na lícové straně nepřekáží, protože líc je
   dole: lem je tedy nahoře = rub; na líci jen motiv). Očko vždy nahoře na ose.
6. **Karta a ukázky**: tři obrázky ukázek (1., 2., 3. místo v různých tvarech), karta nástroje nově (zlatá jednička
   s řetězem); texty: titulek a hint „Medaile pro vítěze: 1., 2. a 3. místo, s řetězem, tiskneme ve zlaté, stříbrné
   a bronzové“; klíčová slova (vítěz, závody, turnaj, škola, oslava, dětský den).
7. **Zkušební tisk pro Romana**: po push vytvoř na produkci jako návštěvník medaili 60 mm „1“ se 40 články a napiš
   řídící session odkaz na kalkulaci (bez objednávky); Roman ji vytiskne (Kobra X má zlatou metalickou, S1 Silk
   zlatou) a zacvaknutí článků i hladkost líce potvrdí.

## 2. Testy a hotovo

Engine: medaile 60/40 výchozí; motiv v prvních 3 vrstvách jako vlastní díl s barvou; text zrcadlený; články
s oblým profilem a mezerou; sedm tvarů; všechny díly vodotěsné, leží na Z=0; podložka 220 × 220 pro 60 mm + 40
článků. PHP: `ToolPageTest` (předvolby, texty cs/en/es), `ToolsFlowTest` (návrh → kalkulace `by_parts`, dvě barvy).
**Hotovo =** push `feature/medallion-winners`, P.md (co se změnilo, filament, co ověří tisk), snímek, zpráva řídící.
