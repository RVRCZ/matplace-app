<?php

return [
    'title' => 'Zásady ochrany osobních údajů',
    'effective' => 'Platné od 29. září 2026 · matplace s.r.o.',
    'footer' => ['privacy' => 'Ochrana osobních údajů', 'terms' => 'Podmínky tiskové farmy', 'youtube' => 'Náš YouTube kanál'],

    'sections' => [
        [
            'h' => '1. Správce osobních údajů',
            'p' => ['Správcem osobních údajů je společnost matplace s.r.o., IČO 22499008, se sídlem Rybná 716/24, Staré Město, 110 00 Praha 1, zapsaná v obchodním rejstříku vedeném Městským soudem v Praze pod sp. zn. C 417491 (dále jen „my“). Kontakt: info@matplace.com.'],
        ],
        [
            'h' => '2. Jaké údaje zpracováváme',
            'li' => [
                'Údaje o účtu – jméno, e-mail, telefon, adresa (pokud je vyplníte), propojení účtu Google nebo Facebook.',
                'Nahrané soubory a zadání – 3D modely, fotky a textové popisy, ze kterých počítáme cenu nebo generujeme model.',
                'Údaje o zakázkách – zvolený materiál, barva, cena, způsob převzetí, doručovací adresa, stav tisku.',
                'Záznam tisku – snímky z kamery v tiskárně a z nich sestavené časosběrné video. Zachycují jen tiskovou podložku a vytištěný předmět.',
                'Platební údaje – zpracovává výhradně platební brána Stripe; my ukládáme jen identifikátor a stav platby a zůstatek kreditu.',
                'Technické údaje – IP adresa, typ prohlížeče, čas přístupu, nezbytné cookies.',
            ],
        ],
        [
            'h' => '3. Proč údaje zpracováváme',
            'li' => [
                'Výpočet ceny, tisk a předání zakázky, správa účtu a kreditu – plnění smlouvy (čl. 6 odst. 1 písm. b) GDPR).',
                'Účetní a daňové povinnosti – zákonná povinnost (čl. 6 odst. 1 písm. c) GDPR).',
                'Zabezpečení služby a ochrana před zneužitím – oprávněný zájem (čl. 6 odst. 1 písm. f) GDPR).',
                'Zveřejnění časosběrného videa na YouTube – oprávněný zájem na představení naší výroby (čl. 6 odst. 1 písm. f) GDPR), proti kterému můžete kdykoli vznést námitku odškrtnutím souhlasu; výtisky podle fotografií jen s vaším výslovným souhlasem (čl. 6 odst. 1 písm. a) GDPR), viz bod 5.',
            ],
        ],
        [
            'h' => '4. Komu údaje předáváme',
            'p' => ['Údaje neprodáváme. Předáváme je jen v nezbytném rozsahu těmto zpracovatelům:'],
            'li' => [
                'Stripe, Inc. – platby kartou při dobití kreditu (stripe.com/privacy).',
                'Anthropic, PBC – rozpoznání předmětu na fotce a kontrola nahraných fotek (anthropic.com/privacy).',
                'Tripo (VAST) – generování 3D modelu z fotky nebo textu, jen když tuto funkci použijete.',
                'Google LLC (YouTube) – časosběrná videa, jejichž zveřejnění jste neodmítli.',
                'Google Ireland Ltd. (Google Analytics) a Meta Platforms Ireland Ltd. (Meta pixel) – měření návštěvnosti a reklamy, jen pokud jste to povolili v cookie liště.',
                'Packeta s.r.o. (Zásilkovna) – jméno, adresa nebo výdejní místo, e-mail a telefon pro doručení zásilky.',
                'Hetzner Online GmbH – hosting, servery v EU.',
                'Dopravci – jen jméno, adresa a telefon pro doručení zásilky.',
            ],
        ],
        [
            'h' => '5. Videa z tisku a YouTube API Services',
            'p' => [
                'Každý tisk na naší farmě natáčí kamera v tiskárně a z jejích snímků sestavíme krátké časosběrné video. Video vidíte na stránce své zakázky.',
                'Pokud zveřejnění neodmítnete, nahraje náš server video na náš vlastní YouTube kanál „Matplace – 3D“ jako soukromé; veřejné bude až po kontrole naším týmem. Políčko se souhlasem se zveřejněním je při objednání předvyplněné – když ho odškrtnete, video na YouTube nepůjde. U výtisků podle fotografií (busty, figurky, litofanie, reliéfy), které mohou zachycovat podobu osoby nebo soukromou fotku, je políčko prázdné a video zveřejníme jen tehdy, když ho sami zaškrtnete. K nahrávání používáme YouTube API Services. Na YouTube posíláme jen samotné video a jeho název a popis (materiál, barva, typ tiskárny, doba tisku); vaše jméno, e-mail ani nahrané soubory ne. U zakázky si ukládáme jen identifikátor videa na YouTube a jeho stav.',
                'Zveřejnění můžete kdykoli odmítnout nebo souhlas odvolat na stránce zakázky, i když už je video na YouTube. Video pak z YouTube automaticky smažeme. O smazání můžete požádat i e-mailem na info@matplace.com.',
                'Naše aplikace nepřistupuje k vašemu účtu Google ani YouTube a nečte žádné vaše údaje z YouTube. Přístup k YouTube API používá jen náš tým pro náš vlastní kanál.',
                'Sledováním videí na YouTube souhlasíte s podmínkami služby YouTube (https://www.youtube.com/t/terms). Na zpracování údajů společností Google se vztahují Zásady ochrany soukromí Google (https://policies.google.com/privacy). Přístup aplikací ke svému účtu Google můžete kdykoli zkontrolovat a odebrat na https://myaccount.google.com/permissions.',
            ],
        ],
        [
            'h' => '6. Předávání mimo EU',
            'p' => ['Stripe, Anthropic a Google sídlí v USA, Tripo mimo EU. Předávání probíhá na základě standardních smluvních doložek Evropské komise nebo jiného mechanismu podle kapitoly V GDPR.'],
        ],
        [
            'h' => '7. Jak dlouho údaje uchováváme',
            'li' => [
                'Účet – po dobu jeho trvání; po zrušení do 30 dnů smažeme nebo anonymizujeme.',
                'Zakázky a platby – 5 let od dokončení (účetní a daňové předpisy), doklady podle zákona až 10 let.',
                'Fotky pro rozpoznání a generování – nejdéle 1 den; anonymně nahrané soubory 30 dní.',
                'Časosběrná videa – u zakázky po dobu trvání účtu; na YouTube do odmítnutí zveřejnění, odvolání souhlasu nebo do našeho smazání.',
                'Technické záznamy – nejvýše 12 měsíců.',
            ],
        ],
        [
            'h' => '8. Vaše práva',
            'p' => ['Máte právo na přístup ke svým údajům, jejich opravu, výmaz, omezení zpracování, přenositelnost, právo vznést námitku, právo kdykoli odvolat souhlas a právo podat stížnost u Úřadu pro ochranu osobních údajů (uoou.gov.cz). Žádosti posílejte na info@matplace.com, odpovíme do 30 dnů.'],
        ],
        [
            'h' => '9. Smazání účtu a údajů',
            'p' => ['Účet smažete sami v profilu (Můj účet → Profil → Smazání účtu). Nebo napište z e-mailu svého účtu na info@matplace.com s předmětem „Smazání účtu“. Do 30 dnů smažeme nebo anonymizujeme profil, kontaktní údaje, nahrané soubory, propojení s Google/Facebook a smažeme i vaše videa z YouTube. Doklady, které musíme ze zákona uchovat, zůstanou v účetnictví anonymizované vůči vašemu profilu.'],
        ],
        [
            'h' => '10. Cookies a zabezpečení',
            'p' => ['Bez vašeho souhlasu používáme jen nezbytné cookies pro přihlášení, ochranu formulářů a zapamatování voleb. Google Analytics a Meta pixel načteme až poté, co je povolíte v cookie liště; volbu můžete kdykoli změnit odkazem „Nastavení cookies“ v patičce. Vlastní statistiku návštěvnosti vedeme na svém serveru bez dalších cookies. Přenos je šifrovaný (TLS), hesla ukládáme jako jednosměrný hash, čísla karet nikdy nevidíme.'],
        ],
        [
            'h' => '11. Změny zásad',
            'p' => ['O podstatných změnách vás budeme informovat na webu nebo e-mailem nejméně 14 dní předem.'],
        ],
    ],
];
