---
title: "Generování 3D modelů pomocí AI: Z fotky nebo nápadu k hotovému STL během minuty"
excerpt: Jak AI vytvoří 3D model z popisu nebo fotky, co od výsledku čekat a kdy je lepší nástroj, do kterého zadáte rozměry. Praktický postup od nápadu po výtisk na matplace.com, s cenami z kalkulace.
cover: blog/20260523_130435_e0f041a2.png
published_at: 2026-05-23 13:01:42
updated_at: 2026-10-18 20:00:00
---
Ještě před pár lety platilo: chcete‑li si něco vytisknout, musíte stáhnout hotový model, nebo se naučit modelovat. Dnes stačí napsat větu nebo nahrát fotku a za minutu máte 3D model. Tento článek vysvětluje, jak generování funguje, co od něj čekat a kdy je lepší jiná cesta.

## Co AI generování umí

AI generátor převede **popis** („sedící liška, stylizovaná, výška 8 cm“) nebo **fotku** na trojrozměrné těleso. Učil se na velkém množství existujících modelů, takže odhadne tvar, objem i strany, které na fotce nejsou vidět. Výsledkem je síť trojúhelníků, kterou jde uložit jako STL a vytisknout.

Co z toho plyne:

- **Tvar je odhad.** Na figurky, dekorace a dárky to stačí. Na díl, který má přesně pasovat, ne.
- **Rozměr si nastavíte sami.** AI neví, jak velká věc má být.
- **Jemné detaily zmizí.** Prsty, tenké obroučky brýlí nebo jednotlivé prameny vlasů se na malém výtisku neprojeví.

## AI na matplace.com

Tvar z fotky nebo popisu vytvoří generátor, u busty je to náš dodavatel Tripo. Bustu pak sami upravíme tak, aby šla vytisknout: uzavřeme díry, spojíme ji do jednoho plného tělesa a postavíme na podstavec.

- **Z popisu nebo fotky předmětu:** na [hlavní stránce](/) zvolte „Mám fotku nebo nápad“. Nejdřív hledáme hotový model. Když se nenajde, vytvoříme z popisu nebo fotky hrubý model a hned spočítáme cenu tisku.
- **Busta nebo figurka člověka:** [Busta nebo figurka z fotky](/tools/figure), jedna fotka zepředu a za minutu model s podstavcem a jménem. Postup popisuje článek [Jak vzniká busta z fotky](/blog/jak-vznika-busta-z-fotky).
- **Pes nebo kočka:** [Figurka mazlíčka z fotky](/tools/pet-figurine), tři styly a jméno na podstavci.

Generování má denní limit: jeden model denně zdarma bez účtu, víc s účtem.

## Kdy AI nepotřebujete

Spousta věcí, které lidé tisknou, potřebuje přesné rozměry, ne odhad. Na ně jsou nástroje, do kterých zadáte čísla a text a model vznikne hned, zdarma a bez generování:

- [Organizér na míru](/tools/organizer) a [krabička na míru](/tools/box) přesně do zásuvky nebo na věc (návody: [organizér](/blog/organizer-do-supliku-na-miru), [krabička](/blog/krabicka-na-miru)),
- [stojánek na telefon](/tools/phone-stand) a [držák na cokoliv](/tools/holder) (návody: [stojánek](/blog/stojanek-na-telefon-na-miru), [držák](/blog/drzak-na-zed-na-miru)),
- [cedulka, jmenovka, klíčenka](/tools/sign) a [3D nápis](/tools/text) (návod: [cedulka a klíčenka](/blog/cedulka-jmenovka-a-klicenka-se-jmenem)),
- [váza a obal na květináč](/tools/vase) (návod: [váza](/blog/vaza-a-obal-na-kvetinac-na-miru)),
- [3D mapa města nebo krajiny](/tools/map) z OpenStreetMap (návod: [3D mapa](/blog/3d-mapa-mesta-z-openstreetmap)).

Všechny nástroje najdete na stránce [Nástroje](/tools).

## Postup od nápadu k výtisku

1. **Zadejte nápad.** Popište ho konkrétně: místo „pes“ raději „sedící zlatý retrívr, stylizovaný, bez podstavce“. Fotka: jeden předmět, světlé pozadí, ostrý snímek.
2. **Prohlédněte si model ve 3D.** Otočte ho a zkontrolujte proporce a místa, kde něco chybí.
3. **Nastavte velikost a materiál.** Kalkulace hned přepočítá dobu tisku, spotřebu a cenu.
4. **Stáhněte, nebo nechte vytisknout.** STL nebo projekt pro svou tiskárnu stáhnete zdarma. Nebo vyberete barvu z cívek, které jsou právě v tiskárnách, a model vytiskneme na naší farmě.

Pro představu: busta 80 mm z PLA+ stojí 99 Kč, 120 mm 130 Kč. Stojánek na telefon 120 Kč. Doprava Zásilkovnou na výdejní místo v Česku je 99 Kč. Víc příkladů je v článku [Kolik stojí 3D tisk na zakázku](/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026).

## Tipy pro lepší výsledek

- **Buďte konkrétní.** Styl, poloha, rozměr a co nemá model mít.
- **Jeden předmět na fotce.** Čisté pozadí pomůže AI oddělit předmět od okolí.
- **Malá figurka, jednoduchý tvar.** U výšky 3 cm se jemné rysy stejně neprojeví.
- **Pozor na spodek.** Odstávající části potřebují podpěry a po jejich odlomení mohou zůstat stopy.
- **Pamatujte na práva.** Model podle cizí postavy nebo značky si můžete vytisknout pro sebe, prodávat ho ale bez svolení nesmíte.

## Co AI zatím neumí

Mechanické díly s tolerancemi, závity a přesnými otvory AI generátory nevymodelují spolehlivě. Na ně je lepší CAD nebo parametrický nástroj. Pokud vám chybí rozbitý díl, na hlavní stránce zvolte „Potřebuji náhradní díl“, přidejte fotky a rozměry a poptejte modelování i výrobu.

Model z jiného generátoru nebo ze skenu nejdřív zkontrolujte. Jak, popisuje článek [Jak připravit STL soubor pro 3D tisk](/blog/jak-pripravit-stl-soubor-pro-3d-tisk). Jak vzniká model z jedné nebo mnoha fotek, srovnává článek [Vytvoření STL z fotek pomocí AI](/blog/vytvoreni-stl-z-fotek-pomoci-ai-kompletni-navod-pro-3d-tisk-2026).

## Co dál

- [Vytvořit model z popisu nebo fotky](/): zvolte „Mám fotku nebo nápad“ a za minutu uvidíte model i cenu tisku.
- [Busta nebo figurka z fotky](/tools/figure): portrét s podstavcem a jménem z jedné fotky.
- [Všechny nástroje](/tools): organizéry, krabičky, stojánky a cedulky podle vašich rozměrů, bez AI a zdarma.
