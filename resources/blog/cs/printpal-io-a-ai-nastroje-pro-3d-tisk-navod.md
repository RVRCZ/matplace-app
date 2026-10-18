---
title: PrintPal.io a 4 další AI nástroje, které vám vytvoří 3D model za pár minut (návod 2026)
excerpt: PrintPal.io, Meshy, Sloyd, Luma Genie a generátory kódu pro OpenSCAD. Co který nástroj umí, pro co se hodí, kde je stejná věc na matplace.com zdarma a jak hotové STL nechat vytisknout s cenou předem.
cover: blog/20260529_103838_b25cfca0.webp
published_at:
updated_at: 2026-10-18 20:00:00
---
Vlastní 3D model dřív znamenal stáhnout hotový z Printables, nebo se naučit Blender. Dnes je tu třetí cesta: AI nástroje, kterým zadáte popis, fotku nebo adresu a za pár minut máte STL k tisku. Kvalita nedosahuje práce zkušeného modeláře, ale na figurku, dárek nebo dekoraci obvykle stačí. Tady je pět nástrojů, pro co se který hodí a jak se z modelu stane výtisk.

Obecný úvod do AI generování najdete v článku [Generování 3D modelů pomocí AI](/blog/generovani-3d-modelu-pomoci-ai).

## PrintPal.io: několik generátorů na jednom místě

[PrintPal.io](https://printpal.io/explore) spojuje pod jednou střechou několik generátorů pro 3D tisk:

- **text na 3D model:** popíšete slovy, dostanete STL,
- **figurka mazlíčka z fotky,**
- **3D mapa města** podle adresy, z dat OpenStreetMap,
- **pohyblivé modely** rozdělené na klouby pro tisk v jednom kuse,
- **jmenovky a 3D nápisy,**
- **editor zacvakávacích spojů** a klipů.

Popis pište anglicky a konkrétně: místo „dragon“ raději „small flexible dragon, articulated body, smooth surface, 8 cm long“. Pro tisk se hodí styl low‑poly nebo cartoon, příliš jemné detaily malá tiskárna nevytiskne. Bezplatný plán má denní limit generování.

## Meshy: kvalitní text na 3D

[Meshy](https://www.meshy.ai) patří mezi nejkvalitnější generátory z textu i z obrázku. Hodí se na figurky, dekorace a rekvizity. S mechanickými díly, šrouby nebo ozubenými koly si poradí hůř.

## Sloyd: parametrické modely

[Sloyd](https://www.sloyd.ai) negeneruje z popisu, ale z parametrů. Vyberete druh (váza, židle, nádoba) a posuvníky nastavíte rozměry. Výsledek má přesné rozměry a čistou geometrii, výběr druhů je ale omezený.

## Luma Genie: vizuální kvalita

[Luma Genie](https://lumalabs.ai/genie) dává velmi detailní modely, vhodné hlavně pro vizualizace. Pro tisk bývá geometrie zbytečně složitá a potřebuje ruční úpravu.

## Kód místo modelu: OpenSCAD

Jazykové modely (ChatGPT, Claude) nebo služby jako [3DGPT](https://3dgpt.app) umí napsat kód pro OpenSCAD, který tvar popisuje matematicky. Zadáte rozměry v milimetrech a dostanete přesný model. Hodí se na držáky, vložky a krabičky, na figurky ne.

## Stejné úlohy na matplace.com

Na většinu těchto úloh má matplace.com vlastní nástroj zdarma, s cenou tisku hned u modelu:

| Chcete | Nástroj jinde | Na matplace.com |
|---|---|---|
| Figurku mazlíčka z fotky | PrintPal.io | [Figurka mazlíčka z fotky](/tools/pet-figurine) |
| Bustu člověka z fotky | Meshy (obrázek na 3D) | [Busta nebo figurka z fotky](/tools/figure) |
| Model z popisu | Meshy, PrintPal.io | [hlavní stránka](/), „Mám fotku nebo nápad“ |
| 3D mapu města | PrintPal.io | [3D mapa města nebo krajiny](/tools/map) |
| Jmenovku, nápis | PrintPal.io | [Cedulka, jmenovka, klíčenka](/tools/sign), [3D nápis](/tools/text) |
| QR cedulku s Wi‑Fi | PrintPal.io (jmenovky) | [QR cedulka a stojánek](/tools/qr) |
| Vázu podle rozměrů | Sloyd | [Váza a obal na květináč](/tools/vase) |
| Krabičku, držák na míru | OpenSCAD | [Krabička na míru](/tools/box), [Držák na cokoliv](/tools/holder) |

## Než model pošlete do tisku

- **Tenké stěny.** AI občas vytvoří stěny pod 1 mm, které se nevytisknou.
- **Díry v síti.** Model musí být uzavřený. [Kontrola modelu](/tools/check) chyby najde, [Oprava 3D modelu](/tools/repair) je opraví a opravený STL stáhnete zdarma.
- **Orientace.** Největší plochou stranou dolů, převisy do 45°.

Podrobně to popisuje článek [Jak připravit STL soubor pro 3D tisk](/blog/jak-pripravit-stl-soubor-pro-3d-tisk).

## Máte STL. Co teď?

Máte dvě možnosti:

- **Vytisknout doma.** Nahrajte soubor do svého sliceru, nastavte materiál a tiskněte.
- **Nechat vytisknout u nás.** Nahrajte STL na [hlavní stránku](/) a hned uvidíte dobu tisku, spotřebu materiálu a cenu. Vyberete barvu z cívek, které jsou právě v tiskárnách, zaplatíte a tiskneme na naší farmě. Výtisk pošleme Zásilkovnou, na výdejní místo v Česku za 99 Kč.

Busta 80 mm z PLA+ stojí 99 Kč, stojánek na telefon 120 Kč. Další ceny jsou v článku [Kolik stojí 3D tisk na zakázku](/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026).

## Závěr

AI nástroje nenahradí modeláře u přesných dílů, ale na figurku, dárek nebo mapu stačí pět minut místo pěti hodin v Blenderu. Na mechaniku je lepší parametrický nástroj nebo OpenSCAD. A pokud nemáte tiskárnu, hotové STL vytiskneme za cenu, kterou vidíte předem.

## Co dál

- [Figurka mazlíčka z fotky](/tools/pet-figurine): pes nebo kočka ve třech stylech, cena tisku hned u modelu.
- [3D mapa města nebo krajiny](/tools/map): budovy, ulice a voda z OpenStreetMap, do minuty a zdarma.
- [Spočítat cenu tisku](/): nahrajte STL z jakéhokoli generátoru a uvidíte dobu tisku a cenu.
