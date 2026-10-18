---
title: "Vytvoření STL z fotek pomocí AI: Kompletní návod pro 3D tisk (2026)"
excerpt: Z jedné fotky vytvoří AI za minutu bustu, figurku mazlíčka nebo hrubý model předmětu. Přesnou kopii skutečné věci dá fotogrammetrie z desítek snímků. Kdy co použít, jak fotit a jak model připravit k tisku.
cover: blog/20260512_204822_96fc073b.webp
published_at: 2026-05-12 20:48:31
updated_at: 2026-10-18 20:00:00
---
Z fotek dnes vznikne 3D model k tisku dvěma cestami. **Generativní AI** odhadne tvar z jediné fotky za minutu: hodí se na busty, figurky a dárky. **Fotogrammetrie** spočítá tvar z desítek snímků kolem dokola: hodí se na přesnou kopii skutečného předmětu. Tento návod ukazuje obě cesty, jak fotit a jak hotový model dostat do tisku.

## Cesta 1: jedna fotka a AI

Generativní model se učil na velkém množství 3D modelů. Z fotky odhadne celý tvar včetně stran, které na ní nejsou vidět. Výsledek je podoba, ne milimetrově přesná kopie.

Na matplace.com to funguje v těchto nástrojích:

- **[Busta nebo figurka z fotky](/tools/figure).** Jedna fotka zepředu, volitelně z boků a zezadu. Asi za minutu je hotový model s podstavcem a jménem, výška 30 až 250 mm. Celý postup s cenami popisuje článek [Jak vzniká busta z fotky](/blog/jak-vznika-busta-z-fotky).
- **[Figurka mazlíčka z fotky](/tools/pet-figurine).** Pes nebo kočka ve třech stylech, podstavec se jménem a kontrola tenkých nohou. Obě figurky srovnává článek [Figurka z fotky](/blog/figurka-z-fotky-busta-a-mazlicek).
- **[Reliéf a litofanie z fotky](/tools/relief).** Fotka se nepromění v těleso, ale v obrázek z různě silného plastu. Proti světlu ukáže fotku, kde je plast tenčí, prosvítá víc. Víc v článku [Litofanie z fotky](/blog/litofanie-z-fotky).
- **Hrubý model předmětu.** Na [hlavní stránce](/) zvolte „Mám fotku nebo nápad“ a nahrajte fotku jednoho předmětu na světlém pozadí. Když hotový model nenajdeme, vytvoříme z fotky hrubý tvar. Rozměr pak nastavte podle skutečnosti.

Tvorba z fotky má denní limit: jeden model denně bez účtu, víc s účtem. Fotku pro bustu mažeme hned po vytvoření modelu.

### Jak fotit pro AI

- Jeden předmět nebo jedna osoba, celý v záběru.
- Světlé jednobarevné pozadí, rovnoměrné světlo bez tvrdých stínů.
- Ostrá fotka z výšky předmětu, ne shora. U portrétu odstup 1,5 až 2 metry.
- Lesklé a průhledné věci AI mate. Matný předmět vyjde lépe.

## Cesta 2: fotogrammetrie z mnoha fotek

Fotogrammetrie porovná desítky snímků stejného předmětu z různých úhlů. Najde na nich stejné body a z jejich posunu spočítá hloubku. Výsledkem je model, který odpovídá skutečnému předmětu, včetně rozměrů v poměru. Novější metody (NeRF, Gaussian Splatting) zvládnou i méně fotek a horší světlo.

Fotogrammetrii zatím nenabízíme. Použijete aplikaci v telefonu nebo program v počítači: například Kiri Engine nebo Polycam v mobilu, Meshroom nebo RealityCapture na počítači. Většina umí export do STL.

### Jak fotit pro fotogrammetrii

- Obejděte předmět dokola a foťte zhruba každých 10 až 15°.
- Malý předmět potřebuje 40 až 60 fotek, větší 80 až 150. Sousední fotky se mají z 60 až 70 % překrývat.
- Foťte ve dvou výškách: z úrovně předmětu a šikmo shora.
- Pod předmět dejte podložku se vzorem, třeba noviny. Program podle ní pozná, odkud fotíte.
- Lesklý povrch zmatněte, třeba křídovým sprejem.

## Z modelu výtisk

Model z fotek bývá v pořádku jako obrázek, ale k tisku často potřebuje doladit:

- **Měřítko.** Fotogrammetrie nezná skutečnou velikost. Změřte na předmětu jeden rozměr a nastavte ho v kalkulaci.
- **Díry a převrácené plochy.** Nástroj [Kontrola modelu před tiskem](/tools/check) je najde, [Oprava 3D modelu](/tools/repair) je opraví. Busty z našeho nástroje jsou už uzavřené a připravené k tisku.
- **Počet trojúhelníků.** Modely z fotek mívají miliony trojúhelníků. Pro tisk stačí 100 až 500 tisíc.
- **Tenké stěny.** Plochy tenčí než 1 mm se nevytisknou. Kontrola modelu je ukáže.

Podrobný postup přípravy souboru je v článku [Jak připravit STL soubor pro 3D tisk](/blog/jak-pripravit-stl-soubor-pro-3d-tisk).

Hotový model nahrajete na [hlavní stránku](/) a hned uvidíte dobu tisku a cenu. Busta 120 mm z PLA+ stojí 130 Kč, další příklady jsou v článku [Kolik stojí 3D tisk na zakázku](/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026).

## Časté otázky

### Můžu z jedné fotky vytvořit přesnou kopii předmětu?

Ne. Z jedné fotky AI tvar odhaduje: na figurky a dárky to stačí, na funkční díl, který má někam pasovat, ne. Pro přesný díl změřte rozměry a použijte fotogrammetrii nebo CAD. Pro rozbitý díl můžete na hlavní stránce zvolit „Potřebuji náhradní díl“, přidat fotky a rozměry a poptat modelování i výrobu.

### Stačí fotky z mobilu?

Ano. Důležitější než megapixely je světlo, ostrost a u fotogrammetrie překryv snímků.

### Smím vytvořit model z fotky kohokoli?

Jen sebe nebo člověka, který s tím souhlasí. Dítě foťte jako jeho rodič nebo s jeho souhlasem. Skenovat cizí sochy, designové výrobky nebo licencované figurky na prodej je riskantní.

## Co dál

- [Busta nebo figurka z fotky](/tools/figure): jedna fotka zepředu a za minutu máte model s podstavcem a cenou tisku.
- [Figurka mazlíčka z fotky](/tools/pet-figurine): pes nebo kočka ve třech stylech, se jménem na podstavci.
- [Kontrola modelu před tiskem](/tools/check): model z fotogrammetrie zkontrolujete před tiskem zdarma.
