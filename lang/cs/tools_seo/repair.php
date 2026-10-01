<?php

return [
    'title' => 'Oprava 3D modelu online: STL, 3MF a OBJ zdarma',
    'description' => 'Nahrajete STL, 3MF nebo OBJ, které slicer odmítá. Zacelíme díry, otočíme převrácené plochy, ukážeme, co se změnilo, a opravený STL stáhnete zdarma.',
    'h1' => 'Opravíme model, který slicer odmítá nebo tiskne špatně',
    'intro' => [
        'Nástroj je pro modely stažené z internetu, naskenované nebo vyexportované s chybou, které slicer odmítá nebo je tiskne špatně. Nahrajete soubor STL, 3MF nebo OBJ a oprava proběhne sama. Zacelíme díry v povrchu, otočíme převrácené plochy a odstraníme zdvojené plochy, plochy s nulovou plochou i drobná smítka mimo model.',
        'Dostanete zprávu, co bylo špatně a co jsme změnili, s tabulkou hodnot před opravou a po ní. Opravený model si zdarma stáhnete jako STL, nebo ho otevřete v kalkulaci a objednáte tisk u nás. Původní soubor zůstává beze změny. Tvar ani rozměry modelu oprava nemění.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Vyberte soubor nebo ho přetáhněte do pole. Soubor může mít nejvýše 100 MB.'],
        ['name' => 'Počkejte na opravu', 'text' => 'Oprava běží sama a nic se nenastavuje. U velkých souborů trvá i minutu.'],
        ['name' => 'Přečtěte si zprávu', 'text' => 'Uvidíte výsledek, seznam provedených úprav a tabulku se sloupci „Před“ a „Po opravě“: otevřené hrany, převrácené plochy, počet těles a trojúhelníků.'],
        ['name' => 'Stáhněte, nebo objednejte tisk', 'text' => 'Tlačítkem „Stáhnout opravený STL“ si model uložíte. Druhým tlačítkem ho otevřete v kalkulaci, kde zjistíte cenu a objednáte tisk.'],
    ],
    'faq' => [
        ['q' => 'Je oprava modelu zdarma?', 'a' => 'Ano. Oprava i stažení opraveného souboru jsou zdarma a bez registrace.'],
        ['q' => 'Jaké chyby nástroj opraví?', 'a' => 'Díry v povrchu, převrácené plochy, zdvojené plochy, plochy s nulovou plochou, hrany sdílené více než dvěma plochami a drobná smítka mimo model. Každé těleso v souboru opravuje zvlášť, takže krabička a víčko zůstanou dvěma díly.'],
        ['q' => 'Změní oprava tvar nebo rozměry modelu?', 'a' => 'Ne. Opravu, která by tvar nebo rozměry změnila, zahodíme a těleso necháme, jak bylo.'],
        ['q' => 'Co když se model opravit nepodaří?', 'a' => 'Některé chyby automaticky opravit neumíme a zpráva to řekne. Zbytek potřebuje ruční úpravu v modelovacím programu. Model přesto můžete zkusit vytisknout, slicer si s drobnými vadami často poradí.'],
        ['q' => 'V jakém formátu opravený model dostanu?', 'a' => 'Vždy jako STL, i když jste nahráli 3MF nebo OBJ. Formát STL nese jen tvar, bez barev a nastavení tisku.'],
    ],
    'examples' => [],
];
