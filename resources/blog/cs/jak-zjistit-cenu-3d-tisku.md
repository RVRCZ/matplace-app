---
title: "Jak zjistit cenu 3D tisku: z čeho se skládá a jak ji vidět předem"
excerpt: Cena výtisku je materiál, doba tisku a příprava, nejnižší zakázka 99 Kč. Jak ji vidíte hned po nahrání modelu, co ji zvedá a co srazí, a proč u velkého modelu ukážeme nejdřív rozpětí.
cover:
published_at: 2026-11-27 07:00:00
updated_at: 2026-11-27 07:00:00
---
„Kolik by stál tisk tohohle?“ je nejčastější otázka, kterou dostáváme. Odpověď na ni nemusíte psát do mailu: nahrajete model, nebo ho za minutu vytvoříte v prohlížeči, a cenu vidíte dřív, než se k čemukoli zavážete, bez registrace. Tenhle článek říká, z čeho se cena skládá, co ji mění a jak si ji ověřit u vlastního modelu.

<!-- foto: obrazovka kalkulace s cenou vedle vytištěného modelu -->

## Z čeho se cena skládá

Cena u nás má tři části, které kalkulace sčítá z řezu modelu ve sliceru, tedy ze stejného programu, který pak řídí tiskárnu:

- **Materiál**: gramy filamentu podle druhu. PLA je nejlevnější a má nejvíc barev; PETG, ASA a TPU stojí o něco víc. Kalkulace bere nejlevnější druh daného materiálu, který máme na farmě.
- **Doba tisku**: minuty, které tiskárna na modelu stráví. Dlouhé tenké díly a vysoké modely stojí tiskárnu víc času než jejich gramy napovídají.
- **Příprava zakázky**: 30 Kč za nasazení tisku a zabalení, jednou za zakázku, ne za kus.

Součet se zaokrouhlí a nejnižší cena zakázky je 99 Kč: drobnost za pár korun materiálu stojí 99 Kč, protože ji někdo musí nasadit, sundat a zabalit. Příklad ze stojícího nápisu HOME: materiál 19 Kč, doba tisku 6 Kč, příprava 30 Kč, celkem 55 Kč, tedy nejnižší cena 99 Kč.

[calc: bkdbbtgjavzn]

Čtyři misky do zásuvky už přes minimum přejdou: materiál 226 Kč, doba tisku 49 Kč, příprava 30 Kč, celkem 310 Kč.

[calc: fsgkqtoncl9f]

Ceny jsou s DPH a bez dopravy; dopravu přes Zásilkovnu vidíte v objednávce, než zaplatíte.

## Jak cenu vidět u vlastního modelu

1. Nahrajte STL, 3MF, OBJ nebo STEP na [hlavní stránce](/). Model se zkontroluje a nařeže; u malého modelu to trvá desítky sekund.
2. Zvolte materiál, kvalitu (vrstvu 0,28, 0,20 nebo 0,12 mm), výplň a počet kusů. Cena se přepočítá po každé změně.
3. Odkaz na kalkulaci si uložte nebo pošlete: je to stránka `/c/…`, kterou vidí každý bez přihlášení. Z ní se objednává.

Kdo model nemá, vytvoří ho v [nástrojích](/tools): ze jména, z fotky, z obrázku nebo z rozměrů zásuvky. Náhled a rozměry vidíte hned, cena přijde po „Pokračovat“.

## Co cenu zvedá a co ji srazí

- **Počet kusů.** Příprava se platí jednou, takže šest perníčků stojí 140 Kč, ne šestkrát 99. U dárků pro celý tým zadejte kusy do kalkulace, cena za kus klesne.
- **Dutý model.** Plný válec 120 × 200 mm stál 910 Kč, vydutý s otvory 500 Kč. Nástroj [Vydutit model](/tools/hollow) ukáže ušetřené gramy a koruny hned.
- **Měřítko.** Zmenšení na 80 % sráží gramy zhruba na polovinu; rozměry po změně vidíte v kalkulaci.
- **Podpěry.** Převisy potřebují podpěry, které se tisknou a pak vyhodí. Model položený na největší rovnou plochu je bez nich levnější i hezčí.
- **Kvalita.** Jemná vrstva 0,12 mm prodlouží tisk zhruba o 60 % a cenu zvedne o část za dobu tisku; hrubá 0,28 mm ho o čtvrtinu zkrátí. Na organizér do šuplíku stačí 0,20 mm.
- **Materiál.** Na věci do interiéru stačí PLA. PETG a ASA volte tam, kde je teplo, slunce nebo zátěž; stojí víc, ale výtisk vydrží.
- **Rozdělení.** Model větší než podložka 250 mm rozdělíme na díly s kolíky; [Rozdělit model](/tools/split) ukáže díly i cenu, třeba 1 440 Kč za válec 320 mm ve dvou dílech.

## Velký model: nejdřív rozpětí, pak přesná cena

U velkého nebo složitého souboru trvá řez minuty, proto ukážeme nejdřív rozpětí od do, spočítané z objemu, výšky a tvaru modelu, a přesnou cenu doplníme, jakmile řez doběhne. Rozpětí jsme nastavili na 400 skutečných kalkulacích tak, aby se přesná cena vešla dovnitř v 95 % případů; podrobnosti jsou v článku [jak přesně odhadneme cenu bez řezu](/blog/jak-presne-odhadneme-cenu-bez-rezu-400-kalkulaci). Když se přesná cena nakonec liší, platí přesná, a vidíte ji dřív, než objednáte.

## Čím se ceník liší od ceníků jinde

Většina tiskáren na zakázku cenu posílá mailem po dni nebo dvou, často jako hodinovou sazbu plus materiál, a bez modelu před očima. U nás cenu spočítá stejný řez, který pak jede na tiskárně, takže nepřekvapí ani nás, ani vás. A protože se počítá z gramů a minut, nemá smysl ji „vyjednávat“: levnější je model vydutit, zmenšit, položit bez podpěr nebo tisknout víc kusů najednou.

Takhle vypadá tisk na farmě, za který platíte:

[video: latest]

## Co dál

- [Kalkulace](/): nahrajte model a cenu vidíte hned, bez registrace.
- [Vydutit model](/tools/hollow): méně gramů, nižší cena, otvory na prášek nebo pryskyřici.
- [Rozdělit model](/tools/split): model větší než podložka na díly s kolíky, cena za všechny díly.
