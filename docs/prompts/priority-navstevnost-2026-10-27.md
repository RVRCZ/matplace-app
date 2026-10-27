# Matplace — priority pro návštěvnost (ne prodej) — 27. 10. 2026

Roman 27. 10.: „předchozí audit byl pro prodej, zapiš jeho cíle, ale teď mám audit pro návštěvnost a to je teď to
hlavní“. Rozdělení řídící session: `docs/prompts/navstevnost-nastroje.md` (nová session N1: nástroje jako vstupní brány,
vánoční nástroje), `docs/prompts/navstevnost-blog-katalog.md` (nová session N2: blog, katalog Inspirace, lokální
stránka, komunita), `docs/prompts/fotobox-pozadi.md` (nová session N3: fotky z fotoboxu s automatickým pozadím),
`docs/prompts/youtube-kanal.md` (session 2: YouTube, viz `audit-youtube-2026-10-27.md`), `docs/prompts/audit-web-2.md`
§„Návštěvnost“ (session 3: sdílení, vložitelná kalkulačka, měření). Stav v `docs/TODO.md` #47. Níže původní znění.

---

Doplněk k auditům webu (26. 10.) a YouTube (27. 10.). Cíl v této fázi: **návštěvnost a opakované návraty**, prodej tisku je druhotný. Tím se mění pořadí doporučení; samotné nálezy platí.

## Co jde dolů (odložit)
- Guest checkout, doprava zdarma, kredit vs. karta, ceník v hero, tykání/vykání — konverzní věci. Nechat na dobu, kdy bude koho konvertovat.
- Full-page cache a TTFB — udělat, ale není to zdroj návštěv (pomůže Core Web Vitals, což je jen slabý ranking faktor).

## Co jde nahoru

### 1. Nástroje jako SEO vstupní brány (největší páka, obsah už existuje)
58 nástrojů zdarma bez registrace = 58 potenciálních landingů na dotazy typu „organizér do šuplíku na míru“, „litofanie z fotky online“, „QR cedulka 3D tisk“, „svg do stl online“, „3D mapa města k tisku“. Zatím cílí jen home („kalkulačka“).
- Pro každý nástroj: H1 a title na dotaz, který lidé zadávají (ne název nástroje); 300–600 slov vlastního textu pod nástrojem (co umí, limity, příklad, fotka výtisku); FAQ schema (už je u některých); interní prolinkování nástroj ↔ blog ↔ příbuzné nástroje.
- Nástroje s mezinárodním potenciálem (SVG→STL, oprava modelu, vydutit, rozdělit, G-code→model, 3D mapa, litofanie) mají v EN násobně větší hledanost než v CZ a málo konkurence zdarma-bez-registrace. EN/ES verze už existují → dotáhnout jejich texty a začít je sledovat v Search Console zvlášť.
- Měřit v Search Console per URL: imprese → kliknutí → použití nástroje.

### 2. Bezplatné nástroje jako „linkbait“
Repair, Check, Hollow, Split, SVG→STL, G-code→model jsou věci, na které se ptá každý 3D-tisk fór/Reddit/Facebook skupina týdně.
- Krátký návod v CZ skupinách (3D tisk CZ/SK na FB, printables komunita, reddit r/3Dprinting pro EN) — ne reklama, odpověď na konkrétní dotaz s odkazem.
- Přidat ke každému nástroji „Sdílet výsledek“ (odkaz /c/… už existuje) — sdílený odkaz je návštěva zdarma.
- Embeddable widget kalkulačky (iframe „Spočítej cenu tisku“) pro blogy a e-shopy s filamentem → zpětné odkazy.

### 3. Blog: méně šablony, víc dotazů
15 článků za jeden den bez fotek Google vyhodnotí jako šablonu. Pro návštěvnost:
- Každý článek navázat na reálný dotaz (Search Console → „dotazy s imprese bez kliků“; Google „Lidé se také ptají“).
- 1 fotka skutečného výtisku + vložené video + vložená sdílená kalkulace do každého.
- Obsah, který se nedá okopírovat: data z farmy („Jaké tloušťky stěn nejčastěji praskají“, „Kolik stojí 100 nejčastějších modelů z Printables“, „Skutečná spotřeba elektřiny na 1 výtisk“). To sbírá odkazy.
- Rozložit publikaci na 1–2 články týdně, ne 15 najednou.

### 4. Katalog /model — buď hodnota, nebo pryč
375 stránek převzatých modelů je teď riziko (thin content táhne dolů celou doménu). Pro návštěvnost má smysl jen pokud každá stránka má něco navíc: cena ve 3 materiálech, doba tisku, doporučené nastavení, „stáhnout projekt pro vaši tiskárnu“. Jinak noindex a nechat jen pro interní navigaci.

### 5. Lokální a brandové hledání
- Google Business Profile (Plzeň) + landing /3d-tisk-plzen — levná, stabilní návštěvnost.
- Beta doména noindex (jinak si dělíš brandové dotazy).

### 6. YouTube jako zdroj návštěv, ne značky
- Každé Shorts končí konkrétním nástrojem, ne home: „matplace.com/tools/figure“ v obraze + v připnutém komentáři. Teď vede vše na home s jedním UTM.
- `utm_content=<videoId>` → v GA vidět, které video vodí lidi.
- Trailer kanálu = ukázka nástroje z fotky (nejsilnější USP), ne časosběr.
- Shorts, které sbírají zhlédnutí (busty, sochy, medaile) → točit varianty těchto, ostatní omezit.
- Delší video 1× měsíčně na vyhledávané dotazy („jak zjistit cenu 3D tisku“, „jak opravit STL zdarma“) — to je jediný YouTube formát, který přivádí lidi dlouhodobě přes vyhledávání.

### 7. Návraty (retence návštěvnosti)
- E-mail při registraci je málo; nabídnout „pošleme vám výsledek“ u nástrojů s delším generováním (busta 2–4 min) → sbírá e-maily bez registrace.
- Měsíční newsletter „nový nástroj + 3 nejlepší výtisky měsíce“.
- Push/RSS pro nové nástroje.

### 8. Měření návštěvnosti (bez toho se nedá řídit)
- Search Console je klíčový nástroj této fáze: sledovat týdně imprese/kliky per nástroj a per jazyk.
- Serverová analytika bez souhlasu (Plausible/Umami nebo vlastní log): návštěva → použití nástroje → sdílení/stažení. GA4 jen po souhlasu ti ukáže zlomek.
- Jedna metrika pro tuto fázi: **unikátní uživatelé, kteří použili aspoň jeden nástroj / týden**.

## Pořadí prací (verze „návštěvnost“)
1. Beta noindex + rozhodnutí o /model (den)
2. Search Console + serverová analytika + UTM per video (den)
3. Texty a title/H1 na 10 nástrojů s největším hledaným potenciálem CZ+EN (týden)
4. Vánoční nástroje: texty + Shorts + 2 články (do poloviny listopadu)
5. Blog přepnout na 1–2/týden s fotkou a daty z farmy
6. Embeddable kalkulačka + sdílení výsledků nástrojů
7. Komunitní distribuce (FB skupiny, Reddit, Printables) — průběžně, 1–2 příspěvky týdně
8. Teprve potom: cache, obrázky WebP, konverzní úpravy
