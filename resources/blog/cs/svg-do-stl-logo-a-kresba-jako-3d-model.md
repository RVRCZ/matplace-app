---
title: "SVG do STL: logo nebo kresba jako 3D model k tisku, zdarma a online"
excerpt: Jak z vektorového loga, ikony nebo kresby udělat STL s přesnými milimetry, co si pohlídat v SVG před nahráním a kolik stojí vytištěná silueta. Příklad s kočkou 80 mm ze skutečné kalkulace.
cover:
published_at: 2026-11-03 07:00:00
updated_at: 2026-11-03 07:00:00
---
Logo firmy, ikona z Noun Project, silueta zvířete nebo kresba obtažená v Inkscapu: všechno jsou ploché tvary, které se dají vytáhnout do výšky a vytisknout. Nástroj [SVG do STL](/tools/svg-to-stl) to udělá v prohlížeči, bez účtu a zdarma, a dá vám uzavřený model s rozměry v milimetrech. Tenhle článek říká, co SVG musí splňovat, co z něj vznikne a kolik výtisk stojí.

<!-- foto: vytištěná silueta kočky ze SVG vedle vytištěného loga, obě na stole -->

## Proč SVG a ne PNG

SVG je vektor: tvar je zapsaný jako křivky, ne jako mřížka bodů. Při převodu do 3D se proto hrany nezubatí a otvory uvnitř tvaru (vnitřek písmene O, oko ve tvaru zvířete) zůstanou otvory. PNG nebo JPG jde nahrát také, nástroj z něj obrys vysleduje v mřížce nejvýše 360 bodů na delší straně; na jednoduchou siluetu to stačí, na logo s drobným písmem už ne. Když máte vektor, použijte ho.

## Jak převod funguje

Nástroj vezme ze souboru vyplněné tvary a vytáhne je kolmo do výšky, kterou zadáte: od 0,6 mm (tři vrstvy po 0,2 mm, štítek nebo nálepka) do 50 mm (tvar, který stojí sám). Šířku nastavíte od 20 do 250 mm a druhý rozměr se dopočítá podle poměru stran, takže se tvar nezkreslí; na jednotkách zapsaných v SVG nezáleží. Výsledek je jedno uzavřené těleso s dnem, víkem a stěnami, které slicer vezme bez opravy. Nástroj předem upozorní na čáry tenčí než 0,8 mm, které tryska 0,4 mm nevytiskne čistě, a napíše, z kolika oddělených kousků se tvar skládá. Horní hranu můžete nechat zkosit.

## Příklad: kočka 80 mm

Silueta kočky z naší knihovny, šířka 80 mm, výška 6 mm. Z nástroje vyšel model 80 × 81 × 6 mm s 2 568 trojúhelníky a objemem 17,7 cm³. Kalkulace na něj počítá 9,6 g PLA a 17 minut tisku; u nás stojí 99 Kč, což je nejnižší cena zakázky, bez dopravy.

[calc: lfa9iggn3hls]

Při 3 mm výšky by stejná kočka měla 5 g a tiskla se 9 minut, cena by zůstala na minimu. Teprve u tvarů nad zhruba 100 mm a 10 mm výšky, nebo u více kusů, se cena od 99 Kč odlepí; kalkulace ji ukáže po každé změně rozměru.

## Co si v SVG pohlídat

- **Výplň, ne čára.** Nástroj bere jen vyplněné tvary. Čáru bez výplně vynechá a řekne to. V Inkscapu označte objekt a použijte Cesta → Obrys na cestu (Stroke to Path); v Illustratoru Objekt → Cesta → Obrysový tah.
- **Text na křivky.** Písmo z vašeho počítače v souboru není. Před uložením převeďte text na cestu (Inkscape: Cesta → Objekt na cestu).
- **Jeden kus, nebo destička.** Vytažený obrys nemá nic, co by oddělené části spojilo: písmena nápisu vyjdou jako samostatná tělesa. Mají-li držet pohromadě, přepněte provedení na reliéf na destičce, nebo tvary v editoru spojte.
- **Velikost souboru do 400 kB.** Obrázek vložený do SVG jako bitmapa se nepočítá; uložte čistý vektor.

## Co s hotovým STL

Model stáhnete zdarma jako STL nebo jako projekt 3MF pro OrcaSlicer, Bambu Studio či PrusaSlicer s nastavením z kalkulace. Dál ho můžete poslat do dalších nástrojů: [Vykrajovátko](/tools/cookie-cutter) z téhož obrysu udělá vykrajovátko na těsto, [Razítko](/tools/stamp) zrcadlené razítko, [Puzzle](/tools/puzzle) ho rozřeže na dílky. Kdo tiskárnu nemá, nechá tvar vytisknout na naší farmě v barvě, která je právě založená v tiskárnách; výtisk posíláme Zásilkovnou.

[video: latest]

## Co dál

- [SVG do STL](/tools/svg-to-stl): nahrajte SVG, PNG nebo napište text a stáhněte STL s přesnými milimetry.
- [Jak připravit STL soubor pro 3D tisk](/blog/jak-pripravit-stl-soubor-pro-3d-tisk): kontrola a oprava modelu, než půjde na tiskárnu.
- [Cena 3D tisku z modelu](/blog/cena-3d-tisku-z-modelu): z čeho se cena skládá a proč je nejnižší 99 Kč.
