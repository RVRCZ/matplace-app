---
title: "Jak přesně umíme odhadnout cenu 3D tisku bez řezu: 400 kalkulací z farmy"
excerpt: Kalkulace ukáže cenu dřív, než slicer model rozřeže. Porovnali jsme odhad se skutečným řezem u 400 kalkulací z produkce: gramy se trefují na 6 %, minuty na 24 %, cena byla v ukázaném rozpětí v 82 % případů. Co z toho plyne.
cover:
published_at: 2026-11-10 07:00:00
updated_at: 2026-11-10 07:00:00
---
Když nahrajete model do [kalkulace](/), vidíte cenu během pár vteřin. To ještě není výsledek sliceru: ten běží na serveru a u velkých modelů trvá i minuty. První číslo je odhad z tvaru, objemu a plochy modelu, a teprve po řezu ho nahradí přesná cena z gramů a minut. Jak moc se odhad od řezu liší, jsme spočítali na 400 kalkulacích z produkce mezi 26. 9. a 25. 10. 2026. Čísla jsou z našeho exportu, nic není zaokrouhleno k lepšímu.

<!-- foto: kalkulace na telefonu s odhadem ceny a rozpětím, vedle vytištěný model -->

## Jak odhad vzniká

Model se po nahrání změří: rozměry, objem, plocha povrchu, počet trojúhelníků a podíl převisů. Z objemu a výplně 15 % vyjde hmotnost, z plochy a výšky doba tisku a z nich cena podle ceníku farmy: materiál za gram, čas za hodinu, příprava zakázky 30 Kč a nejnižší cena 99 Kč. Stránka k tomu ukáže rozpětí, ve kterém se má skutečná cena pohybovat. Slicer pak model opravdu rozřeže, spočítá dráhy a cena se přepíše na přesnou; u většiny modelů to trvá vteřiny, u sítí s miliony trojúhelníků déle.

## Gramy: medián odchylky 6 %

U hmotnosti se odhad trefuje dobře. Medián rozdílu proti sliceru byl +0,2 %, tedy odhad není soustavně nad ani pod. Medián absolutní odchylky byl 5,9 %: u poloviny kalkulací se odhad lišil o méně než 6 %. Do 10 % se vešlo 71 % kalkulací, do 20 % jich bylo 82 %. Zbytek jsou modely s velkými dutinami nebo vnitřními stěnami, kde výplň 15 % zabírá jinak, než vzorec čeká.

## Minuty: medián odchylky 24 %, odhad bývá kratší

Doba tisku je těžší. Medián rozdílu byl −11,6 %: odhad bývá kratší než skutečný řez, protože nevidí, kolik času sežerou podpěry, malé plošky a cestování hlavy mezi ostrůvky. Medián absolutní odchylky byl 23,6 %; do 20 % se vešlo 46 % kalkulací, do 30 % jich bylo 62 %.

Podle velikosti modelu to vypadá takto (medián absolutní odchylky doby tisku):

| největší rozměr | kalkulací | odchylka |
|---|---|---|
| do 50 mm | 31 | 26 % |
| 50 až 100 mm | 162 | 29 % |
| 100 až 200 mm | 178 | 13 % |
| nad 200 mm | 29 | 21 % |

Nejlépe vycházejí modely mezi 100 a 200 mm, což je většina toho, co lidé tisknou. U malých dílů dělá procenta i pár minut navíc na zahřátí a první vrstvu.

## Cena: v rozpětí v 82 % případů

Stránka u odhadu ukazovala rozpětí 0,85 až 1,25 násobku odhadu. Skutečná cena po řezu se do něj vešla u 329 ze 400 kalkulací, tedy v 82 %; 37 kalkulací skončilo nad ním, 34 pod ním. U 131 kalkulací (33 %) byl odhad přesně rovný výsledku: to jsou hlavně malé modely, kde obojí skončí na nejnižší ceně 99 Kč, a kde zaokrouhlení na desetikoruny rozdíl smaže.

Od 25. 10. 2026 proto kalkulace ukazuje rozpětí podle tvaru: plochý, vysoký, tenký, kompaktní, s podpěrami nebo bez nich. Na stejných 400 kalkulacích by se do něj vešlo 387 (96,8 %), za cenu širšího rozpětí: medián poměru horní a dolní meze stoupl z 1,53 na 2,14. Raději říkáme „110 až 250 Kč“ a trefíme se, než „170 Kč“ a vedle.

## Co lidé počítali

Ze 400 kalkulací mělo 390 kvalitu standard (vrstva 0,2 mm) a 391 výplň 15 %. Podpěry slicer použil u 65 modelů. Medián počtu trojúhelníků byl 39 656, největší síť měla 2,7 milionu. Řez trval medián 5,2 s, u desetiny modelů přes 36 s, nejdéle 272 s. Výsledná cena: medián 140 Kč, čtvrtina kalkulací do 120 Kč, čtvrtina nad 240 Kč, nejvyšší 1 750 Kč; 12 % kalkulací skončilo na minimu 99 Kč.

## Co z toho plyne pro vás

- Hmotnosti z odhadu věřte, u ceny počítejte s rozpětím, které stránka ukáže, ne s jedním číslem.
- U modelu, který potřebuje podpěry, čekejte skutečnost spíš u horní meze.
- Velký model (nad milion trojúhelníků) kalkulace rozřeže až po vašich volbách na stránce objednávky; do té doby platí rozpětí.
- Přesnou cenu vidíte vždy před objednáním; objednává se až z řezu, nikdy z odhadu.

[video: latest]

## Co dál

- [Kalkulace ceny tisku](/): nahrajte STL, 3MF, OBJ nebo STEP a uvidíte odhad, rozpětí a po řezu přesnou cenu.
- [Cena 3D tisku z modelu](/blog/cena-3d-tisku-z-modelu): z čeho se cena skládá, s příklady.
- [Vydutit model](/tools/hollow): u velkých modelů ušetří dutina stovky gramů i korun.
