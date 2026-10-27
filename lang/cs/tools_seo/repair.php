<?php

// Stránka pro dotaz „oprava stl souboru online“ (docs/AP.md §1, §3). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Oprava STL souboru online zdarma: díry i převrácené plochy',
    'heading' => 'Oprava STL souboru online zdarma',
    'description' => 'Nahrajete STL, 3MF nebo OBJ, které slicer odmítá. Zacelíme díry, otočíme převrácené plochy, spojíme rozpadlá tělesa a opravený STL stáhnete zdarma.',
    'h1' => 'Co oprava modelu opraví, co nechá být a jak dlouho trvá',
    'intro' => [
        'Nástroj je pro model stažený z Printables, Thingiverse nebo MakerWorldu, pro sken a pro soubor vyexportovaný s chybou, který slicer odmítá nebo tiskne s dírami. Nahrajete STL, 3MF nebo OBJ do 120 MB a oprava proběhne sama, nic se nenastavuje. Zacelí díry v povrchu, otočí převrácené plochy, odstraní zdvojené plochy, plochy s nulovou plochou a drobná smítka mimo model a srovná hrany, které sdílí víc než dvě plochy. Každé těleso v souboru opravuje zvlášť, takže krabička s víčkem zůstane dvěma díly. Opravu, která by posunula rozměr tělesa o víc než 2 %, zahodí a těleso nechá, jak bylo: tvar ani milimetry se nemění. Dostanete zprávu, co bylo špatně a co se změnilo, s tabulkou před a po: otevřené hrany, hrany sdílené více plochami, počet těles a trojúhelníků. Původní soubor zůstává beze změny.',
        'Příklad z 27. 10. 2026: busta 60 × 52 × 78 mm se 4 053 trojúhelníky, do které jsme vyřízli tři díry, 25 ploch zdvojili a 40 otočili. Slicer takovou síť hlásí jako neuzavřenou. Oprava trvala 1,4 vteřiny: zacelila 36 otevřených hran, srovnala 75 hran sdílených více plochami, odstranila 25 zdvojených ploch a 51 oddělených úlomků spojila do jednoho uzavřeného tělesa o objemu 80,6 cm³. Rozměry zůstaly 60 × 52 × 78 mm. Opravený STL si stáhnete zdarma a bez účtu, nebo ho jedním tlačítkem otevřete v kalkulaci: u nás by se tiskl asi hodinu a stál kolem 110 Kč (PLA, bez dopravy); kdo má vlastní tiskárnu, stáhne si projekt pro slicer.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Soubor STL, 3MF nebo OBJ do 120 MB vyberte nebo přetáhněte do pole. Nic se nenastavuje.'],
        ['name' => 'Počkejte na opravu', 'text' => 'Běžný model je hotový za pár vteřin, soubor s miliony trojúhelníků do minuty. Stránka říká, co se právě děje.'],
        ['name' => 'Přečtěte si zprávu', 'text' => 'Výsledek (opraveno, zlepšeno, beze změny), seznam úprav a tabulka „Před“ a „Po opravě“: otevřené hrany, hrany sdílené více plochami, tělesa, trojúhelníky, objem.'],
        ['name' => 'Stáhněte, nebo objednejte tisk', 'text' => 'Tlačítkem „Stáhnout opravený STL“ soubor uložíte. Druhým tlačítkem ho otevřete v kalkulaci, kde vidíte gramy, dobu tisku a cenu.'],
    ],
    'faq' => [
        ['q' => 'Slicer hlásí „non‑manifold“, „open edges“ nebo „mesh is not watertight“. Pomůže to?', 'a' => 'Ano, přesně na tyhle hlášky je oprava. Otevřené hrany jsou díry v povrchu, non‑manifold jsou hrany, které sdílí víc než dvě plochy, a obojí nástroj spraví. Ve zprávě pak vidíte, kolik hran zacelil a kolik srovnal.'],
        ['q' => 'Změní oprava tvar nebo rozměry modelu?', 'a' => 'Ne. Díry zacelí v rovině jejich okraje a plochy jen otočí. Když by oprava posunula rozměr tělesa o víc než 2 %, zahodí ji a těleso nechá, jak bylo; zpráva to řekne.'],
        ['q' => 'Co nástroj neopraví?', 'a' => 'Chybějící velkou část povrchu nemá z čeho doplnit a plochu bez tloušťky (jedinou stěnu bez objemu) nemá jak uzavřít. Ve zprávě pak stojí „zlepšeno“ nebo „beze změny“ a zbytek je na ruční úpravu v Blenderu, Meshmixeru nebo CADu.'],
        ['q' => 'V jakém formátu opravený model dostanu?', 'a' => 'Vždy jako STL, i když jste nahráli 3MF nebo OBJ. STL nese jen tvar; barvy, názvy dílů a nastavení tisku z 3MF se do něj nepřenesou.'],
        ['q' => 'Je oprava zdarma a potřebuji účet?', 'a' => 'Oprava i stažení jsou zdarma a bez účtu, bez vodoznaku a bez limitu na počet souborů. Nahraný soubor slouží jen k opravě a kalkulaci a nikomu ho neposíláme.'],
        ['q' => 'Proč se mi zpráva před opravou neukázala objem?', 'a' => 'Objem se dá spočítat jen z uzavřené sítě. Dokud má model díry, objem a tedy ani gramy a cena nedávají smysl; po opravě je kalkulace spočítá a slicer dostane čistý soubor.'],
    ],
    'examples' => [],
];
