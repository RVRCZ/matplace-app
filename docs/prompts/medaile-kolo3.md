# Zadání pro session 1 (+ session 2): medaile, kolo 3: zarovnaný motiv k podložce, velikost motivu, lem ano/ne, nové výchozí barvy

Od Romana (19. 10. 2026, nad nasazeným kolem 2, snímek: zlatá medaile s červenou jedničkou a 40 články):
„Medaile je pěkná, ale chtěli jsme i volbu **zarovnaného motivu** (čísla) s plackou směrem k podložce, tedy dole,
aby placka a motiv byly zároveň, protože zeshora to při tisku není tak pěkné. Teď se může měnit jen okraj: přidej
**změnu velikosti motivu nebo textu**. Dále **volbu zarovnaný motiv** (tiskne se motivem k podložce) **nebo vystouplý
motiv**, a **volbu lemu ano/ne**. Výchozí: jako na obrázku, ale se zarovnaným motivem a okrajem, medaile a řetěz ve
zlaté, motiv a okraj červeně, číslo o něco menší.“

Co už je: kolo 2 (P.md §8, §8a): lícem nahoru s vystouplým motivem a změnou barvy po výšce; varianta lícem dolů
s vloženým motivem je v enginu za vypnutou volbou `inlay` (+ `depth`). **Farma od 19. 10. umí jednu podložku se
dvěma až čtyřmi filamenty** (TODO #31, AL.md §10: `PrepareFarmOrder::prepareOnePlate`, cívky z jednoho stroje,
čisticí věž; S1+ACE bere `T<slot>` přímo, Kobra X přes agenta 0.2.5), takže `inlay` jde zapnout.

## 0. Kde pracuješ a pravidla

- Session 1: větev **`feature/medallion-flush`** z aktuálního `main`, `docs/P.md` §8b. Soubory jako v kole 2
  (`shape_kinds.py`, `config/tools.php`, `lang/src/medallion3.json` + `lang_add`, testy, ukázky). Nesahat: farma
  (kromě příznaku v bodě 4, který dělá session 2), ostatní nástroje.
- Session 2: větev **`feature/one-plate-required`** z `main`, AL.md §10.8: bod 4 níže. Nesahat na nástroje.
- Pravidla jako dosud; nemergovat, nenasazovat; hotovo = push + zpráva řídící session (conceprt19092026-8c).

## 1. Nástroj (session 1)

1. **Volba `motif`**: `flush` = **zarovnaný** (líc dolů, motiv ve spodních vrstvách jako vlastní díl s barvou, horní
   plocha placky a motivu zároveň = hladké obojí; dnešní `inlay`, hloubka motivu 0,6 mm) a `raised` = **vystouplý**
   (dnešní kolo 2). **Výchozí `flush`.** Text u volby: „Zarovnaný: motiv je v rovině s plackou a tiskne se k podložce,
   hladký; dvě barvy v jedné podložce (tiskárna s měničem). Vystouplý: motiv vystupuje 0,6 mm, změna barvy po výšce.“
   Náhled u `flush` ukazuje medaili lícem nahoru (jak bude vypadat), do výroby jde otočená.
2. **Velikost motivu**: posuvník „Velikost motivu“ v % průměru (text i obrázek), výchozí tak, aby číslo bylo „o něco
   menší“ než dnes (dnes vyplní plochu až po okraj 5 mm; nové výchozí ≈ 55 % průměru); „Okraj kolem obrázku“ zůstává.
3. **Lem**: volba „Lem“ ano/ne (výchozí ano), šířka 2 mm; u `flush` je lem na líci v barvě motivu a tiskne se
   k podložce (stejná barva jako motiv, stejné vrstvy); u `raised` na rubu jako dnes.
4. **Výchozí barvy předvoleb**: 1. místo = **medaile a řetěz zlaté** (PLA Silk zlatá 39), **motiv a lem červené**
   (PLA Silk červená, nebo PLA+ červená, co paleta má); 2. místo = stříbrná medaile (43), motiv červený; 3. místo =
   bronzová (marrákešská / měděná), motiv červený. Vše dál jde změnit. Výchozí nástroj: 1. místo, `flush`, lem ano,
   číslo 1 v 55 %.
5. **Výroba u `flush`**: návrh nese `multi_material: true` a nový příznak **`one_plate_required: true`** (viz bod 4
   u session 2): farma takový návrh nikdy netiskne po dílech (volná jednička), buď jedna podložka se dvěma filamenty,
   nebo srozumitelná chyba se zvolením cívek z jedné tiskárny s měničem. Ukázky a karta: zlatá medaile s červenou
   jedničkou a lemem, 40 článků (jako Romanův snímek), stříbrný štít 2, bronzová rozeta 3.
6. **Zkouška**: po nasazení vytvoř na produkci zkušební medaili (výchozí stav) a pošli odkaz na kalkulaci; Roman ji
   tiskne na S1 + ACE (zlatá + červená v jedné podložce) nebo na Kobře X (po agentovi 0.2.5).

## 2. Farma (session 2): `one_plate_required`

- `PrepareFarmOrder`: návrh s `one_plate_required` (z `tool_params` / meta modelu) → když `prepareOnePlate()` vrátí
  false, zakázka **neselže potichu do tisku po dílech**, ale skončí stavem `failed` s důvodem `one_plate_needed`
  (text zákazníkovi: „Tento návrh má dvě barvy v jedné vrstvě; zvolte cívky z jedné tiskárny s měničem (tiskárna
  s ACE/Rackem), nebo jednobarevné provedení.“), kredit se vrátí jako u jiných selhání; obsluze mail „problém“.
- Stránka zakázky: u takového návrhu nabídnout jen cívky strojů s měničem (sloty téže tiskárny), varování předem,
  když vybraná kombinace nejde (copies > 1 → nabídnout 1 ks, nebo tisk po kusech za sebou).
- Testy: FarmOnePlateTest (one_plate_required + cívky ze dvou strojů → failed s důvodem, mail; jedna tiskárna →
  jedna podložka), FarmOrderFlowTest.

## 3. Hotovo

Push obou větví, P.md §8b + AL.md §10.8, testy, pint, tsc, build; zpráva řídící session; pak odkaz na zkušební medaili.
