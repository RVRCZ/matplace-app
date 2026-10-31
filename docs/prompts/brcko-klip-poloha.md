# Ozdoba na brčko, kolo 3: klip kdekoli po obvodu, osa brčka vždy mimo motiv (session G)

Zadal Roman 31. 10. 2026 večer u `/tools/straw-topper` s logem Matplace jako obrázkem (snímek `docs/img/zadani-brcko-klip.png`): „udělat možnost zvolit polohu úchytu po obvodu motivu jako u očka na přívěsek, jen by se osa brčka přizpůsobovala tak, aby nezasahovala do motivu“. Navazuje na docs/P.md §9 a §9a (kolo 1 a 2, session G); zkušební tisk zatím nebyl, přijde až po tomto kole.

## 1. Co je dnes

- Napříč (`text_dir = across`, u obrázku vždy): bod klipu je na obrysu podle `eye_pos` (`S.along(travel, eye_pos/100)`, `engines/python/shape_kinds.py` kolem řádku 654), klip 14 mm (`CLIP_MIN`) se staví v tom bodě s normálou natvrdo vodorovnou: `_clip(M, px, py, 1.0 if px >= mid else -1.0, 0.0, straw_d)` (řádek 673). Osa brčka tedy vede svisle přes zvolený bod obrysu. Vpravo nebo vlevo na kraji motivu je to správně (snímek: klip u písmene e), ale bod nahoře nebo dole na obrysu dá brčko skrz motiv.
- Úchyt polohy se v náhledu táhne po obrysu (`outline` v odpovědi, šipka u klipu); pole `f.straw.eye_pos` „Poloha klipu po obvodu“ má `WHEN` na `text_dir = across`. Na Romanově snímku s obrázkem pole v Rozměrech vidět není, přitom úchyt v náhledu ano: ověř, zda `WHEN` schová pole, když je volba `text_dir` u obrázku skrytá (její hodnota se pak možná nečte).
- Podél (`along`): klip 15 mm (`CLIP_ALONG`) uprostřed pravé strany, úchyt schovaný (§9a).

## 2. Cíl

1. **Bod na obrysu volí výšku a stranu, ne osu brčka.** Zvolený bod obrysu (px, py) určí stranu (vlevo nebo vpravo podle `mid`, u bodu přesně nahoře nebo dole bližší strana) a výšku klipu (py). Osa brčka leží vždy mimo celý motiv: x osy = krajní x siluety motivu na té straně (přes celou výšku, ne jen v py) + vůle 0,4 mm + `straw_d`/2, pevně pro celou výšku, protože brčko je dlouhé a vede podél celé ozdoby. Klip stojí na této ose ve výšce py.
2. **Rameno k motivu.** Mezi bodem obrysu a klipem je pás (`tab`, stejný jako dnes pod klipem: tloušťka destičky, výška klipu 14 mm), který mezeru zaplní. Na kraji motivu je rameno nulové (jako dnes), jinde tak dlouhé, jak je bod obrysu od osy brčka daleko. Rameno nesmí vstoupit do prostoru brčka (prázdná trubka z §9a zůstává) a musí být spojené s motivem i přes písmena, která se nedotýkají.
3. **Rozumné meze.** Rameno delší než asi 12 mm je ošklivé a slabé: úchyt v náhledu ať se dá táhnout jen po úsecích obrysu, kde rameno vyjde kratší, nebo ať se přichytí k nejbližšímu povolenému bodu a stránka řekne proč (text přes lang, třeba `straw.clip.far`). Rozhodni podle toho, co jde v náhledu udělat čitelně; popiš v P.md.
4. **Pole „Poloha klipu po obvodu“ u obrázku vidět**, když je klip napříč (oprava `WHEN` nebo výchozí hodnoty `text_dir` u obrázku).
5. **Podél brčka** jen pokud to vyjde ze stejného kódu zadarmo: poloha klipu po délce strany (výška na ose brčka) místo pevného středu. Jinak nechat a napsat do P.md jako další krok.
6. Výchozí návrh (Ela podél, 8 mm) a uložené návrhy se nesmí změnit: napříč s bodem na kraji motivu dá stejný model jako dnes (rameno 0).

## 3. Testy

- `HeldShapesTest`: napříč s bodem na kraji = dnešní model (rozměry a objem stejné); bod nahoře na obrysu kruhu = klip na straně ve výšce bodu, osa brčka mimo kruh, rameno spojené, trubka prázdná (test trojúhelníku v trubce z §9a); logo Matplace z `public/img/logo.png` (až bude v knihovně od úkolu #57, jinak jiný barevný obrázek knihovny) s bodem u vysokého písmene: brčko mimo siluetu po celé výšce.
- `ToolPageTest` (předvolby se staví), `ParametricToolsTest`; `vendor/bin/pint --test`, `npx tsc --noEmit -p tsconfig.json` a `npm run build` při změně náhledu. Snímky: klip na kraji, klip nahoře s ramenem, do `docs/img/straw-clip-pos-*.png`.

## 4. Kde a jak

- Session G, worktree `C:\matplace-guest-wt`, větev `feature/straw-clip-pos` z main. Soubory: `engines/python/shape_kinds.py` (`_clip`, větev `product == "straw"` napříč, `outline`), `app/Domain/Tools/ParametricGenerator.php` (`WHEN` pro `eye_pos` u `straw`), náhled úchytu v `resources/js/calc/param.ts`, texty `lang/*/param.php` nebo `lang/src/param.json` + `python scripts/lang_add.py param`.
- Pořadí vůči úkolu #57 (přívěsek): brčko dřív, je menší a odemyká zkušební tisk; když už máš přívěsek rozdělaný v kódu, dokonči ho do bezpečného commitu a pak brčko.
- Zápis: `docs/P.md` §9b. Nic nenasazuj. Po pushi zpráva řídící session: větev, commit, co je ověřené, snímky, a co má Roman při zkušebním tisku sledovat (doplnit k bodům z §9).
