# Z · Web očima návštěvníka – kolo 1: nález a návrh (session B, 13. 10. 2026)

Zadání: `docs/prompts/web-dojem.md`. Díval jsem se na **produkci** https://matplace.com jako člověk, který přišel
poprvé: anonymní headless Chrome (čerstvý profil na každou stránku, bez cookies), **390 px** (iPhone, 2×) a
**1366 px**, cs i en, vždy první obrazovka bez skrolování. Snímky jsou v `docs/img/review/`
(`<stranka>-<sirka>-<jazyk>-<n>.webp`), čísla z `facts.json` (LCP z PerformanceObserver, požadavky a přenesené
bajty z Performance API, výška stránky). Nic v kódu jsem neměnil. Dvě zjevně rozbité věci jsem hlásil řídící
session hned (nález 2 a 4 níže).

Čísla z produkce (týden 6.–12. 10., lidé bez robotů): **179 lidí**, cenu spočítali 4, model nahráli 3, zakázku
založili 2. **Anglicky 133, česky 46.** Nejvíc vidí `/` (19), `/tools` (5); stránky nástrojů mají jednotky návštěv,
Google roste (25, minule 5). Pořadí nálezů tedy: první obrazovka `/` (telefon, cs i en) → `/tools` → vstup na nástroj
z Googlu → kalkulace.

## 1. Shrnutí pro Romana

Tři důvody, proč člověk odejde, a co s tím:

1. **Na telefonu nevidí, co dostane.** Z Googlu přijde na `/tools/qr`, `/tools/phone-stand`, `/tools/sign` a na
   první obrazovce je jen 3D prohlížeč s osmi ovládacími tlačítky („3D · Shora · Zepředu …“), modrý objekt bez
   popisu a cookie lišta přes čtvrtinu obrazovky. Název, věta „co to je“, cena a formulář jsou až pod ní; u busty
   je prohlížeč prázdný s nápisem „Model a cena“. → **Na telefonu dát nad prohlížeč název + jednu větu + cenu/akci,
   prohlížeč zkrátit a jeho tlačítka schovat do jednoho; cookie lišta na jeden řádek.**
2. **Cesta k objednávce pro hosta končí u ceny.** V kalkulaci (po „Pokračovat ke kalkulaci“ i po nahrání souboru)
   je jen „Přepočítat“, „Mám tiskárnu, stáhnout“, „Sdílet odkaz“ – **tlačítko k tisku u nás není vůbec**, ač text
   o kus níž slibuje „Tlačítkem VYTISKNOUT přejdete k objednávce“. Obyčejná kostka navíc skončí „Přesný výpočet se
   nepovedl, platí odhad.“ Trychtýř 4 → 2 je přesně tohle. → **Tlačítko „Vytisknout u nás“ i pro hosta (vede na
   přihlášení/registraci s návratem do kalkulace, nebo poptávka e-mailem) a oprava přesného výpočtu.**
3. **Hlavní stránka a katalog jsou dlouhé seznamy pro někoho jiného.** První obrazovka `/` mluví k tomu, kdo má
   soubor (menšina), nástroje a dárky jsou 3 obrazovky níž; `/` má na telefonu 7 400 px, `/tools` **22 800 px** (27
   obrazovek karet pod sebou); první položka menu „Modely“ vede na prázdnou stránku. → **Hero „vyberte, co chcete
   vyrobit“ s 4–6 nejčastějšími nástroji hned nahoře, kompaktní karty (fotka vlevo, 2 řádky) v `/tools`, „Modely“
   z menu pryč, dokud je prázdné.**

Angličtina je v pořádku (texty úplné, žádné klíče), ale má stejné tři problémy; en návštěvníků je tři čtvrtiny.
Rychlost není problém (LCP 0,3–1,1 s, 8 požadavků, ~650 kB na stránku).

## 2. Nálezy podle dopadu

Odhad: S = do 2 h, M = půl dne, L = den a víc. Vlastník podle `docs/prompts/*.md`: **řídící** (hlavní stránka,
kalkulace, layout, cookie), **E** (opravy nástrojů: stránka nástroje, katalog), **1** (tvary: cedulka, QR),
**D** (figurka/busta, barvy), **B** (mapa/papel), **4** (prodej).

### N1 · Vstup z Googlu na telefonu: první obrazovka beze slov (dopad nejvyšší)

- Snímky: `qr-390-cs-1`, `phone-stand-390-cs-1`, `sign-390-cs-1`, `box-390-cs-1`, `figure-390-cs-1`, `qr-390-en-1`.
- Kde: všechny stránky nástrojů, 390 px, cs i en. Na 1366 px je to v pořádku (`qr-1366-cs-1`: název, věta, kroky,
  formulář vlevo, model vpravo).
- Co: `h1` ani popis nejsou vidět (`h1Seen=false` u všech pěti); vidět je lišta prohlížeče (5 textových tlačítek
  + 3 ikony), model, řádek „70 × 53,6 × 95,6 mm · vejde se na 250 mm“ a cookie lišta (207 px = 25 % výšky).
  Formulář 0 polí na první obrazovce, cena až na druhé. U busty prázdná plocha „Model a cena“.
- Proč odrazuje: do 5 s nevím, co tu je a co mám udělat; tlačítka prohlížeče vypadají jako nástroj pro odborníky.
- Návrh: na `< lg` přesunout do pořadí **název + věta (1–2 řádky) → prohlížeč 45–50 vh s jedním tlačítkem „Pohled“
  (rozbalí 3D/Shora/…) → karta ceny s hlavní akcí → kroky formuláře**; u nástrojů s fotkou (busta, reliéf) místo
  prázdného prohlížeče ukázat ukázkový výtisk (máme fotky karet) a text „Nahrajte fotku, model uvidíte za minutu“.
- Odhad: **M** (jedna šablona `tools/page.blade.php` + `tool_page.ts`, platí pro všech 55 nástrojů). Vlastník: **E**.

### N2 · Host nemá jak objednat tisk (dopad nejvyšší)

- Snímky: `calc-stand-390-cs-full` (celá kalkulace po „Pokračovat ke kalkulaci“), `flow-stand-390-cs-5`,
  `calc-cube-390-cs-full`, `file-home-390-cs-1`.
- Kde: kalkulace `/c/<kód>`, 390 i 1366, cs i en, nepřihlášený.
- Co: tlačítka na stránce: Přepočítat (oranžové), Mám tiskárnu stáhnout, Sdílet odkaz, Jiný soubor. Žádné
  „Vytisknout“/„Objednat“ (`#cta-make` se hostovi nevykreslí), žádný odkaz na přihlášení v kontextu ceny. Text
  pod kalkulací říká „Tlačítkem „VYTISKNOUT“ přejdete k objednávce: vyberete barvu a způsob převzetí“.
- Proč odrazuje: člověk, který chce výtisk, dojde k ceně a neví, co dál; jediná oranžová akce je „Přepočítat“.
- Návrh: i hostovi ukázat hlavní akci **„Vytisknout u nás“** → přihlášení/registrace jedním krokem (e-mail +
  kód) s návratem do téže kalkulace; dokud farma hosty nebere, aspoň poptávka e-mailem (formulář `inquiry-panel`
  v šabloně už existuje, je skrytý). „Přepočítat“ nemá být oranžové (pravidlo: oranžová jen na hlavní akci).
- Odhad: **M** (tlačítko + návrat po přihlášení), **S** (odkrýt poptávku). Vlastník: **řídící**.

### N3 · Hlavní stránka: první obrazovka mluví k menšině, zbytek je dlouhý

- Snímky: `home-390-cs-1`, `home-390-en-1`, `home-1366-cs-1`, `home-390-cs-full`, `home-1366-cs-full`.
- Kde: `/`, 390 (7 365 px = 9 obrazovek) a 1366.
- Co: hero = „Od myšlenky k výrobku.“ + věta + chipy „Myšlenka → Návrh ve 3D → Výrobek v ruce“ + velké pole pro
  soubor; tlačítka „Nahrát soubor / Vyfotit / Popsat slovy“ jsou na telefonu pod cookie lištou. Nástroje
  („Co lidé tisknou nejčastěji“, 8 karet, nové fotky vypadají dobře) začínají na 3. obrazovce; „Jak to jde“, dlouhý
  SEO text a FAQ pod tím. Z 179 lidí nahráli soubor 3.
- Proč odrazuje: kdo přišel kvůli dárku/stojánku (většina z Googlu i YouTube), vidí nahrávání souboru a formáty
  STL, 3MF, OBJ… a odejde; na 1366 je hero v pořádku, ale tři oranžová tlačítka vedle sebe.
- Návrh: hero **„Co chcete vyrobit?“** se 4–6 dlaždicemi (fotka + název: dárek se jménem, stojánek, busta z fotky,
  QR cedulka, krabička, vlastní soubor) hned pod nadpisem; pole pro soubor až jako dlaždice „Mám soubor“; sekce
  „Jak to jde“ a FAQ zkrátit na polovinu; SEO text pod patičku nebo rozbalovací.
- Odhad: **M**. Vlastník: **řídící**.

### N4 · Přesný výpočet selhává na obyčejné kostce

- Snímky: `file-home-390-cs-1`, `file-home-390-cs-bin`, `calc-cube-390-cs-full`.
- Kde: nahrání souboru na `/` (ASCII i binární STL, 40mm kostka, 12 trojúhelníků), čekáno 20–26 s. Stojánek z
  nástroje má „Přesný výpočet 1 h 24 min“, kostka vždy **„Přesný výpočet se nepovedl, platí odhad.“** (4 z 4).
- Proč odrazuje: první věta, kterou člověk s vlastním souborem čte, je chybová; nevěří ceně.
- Návrh: najít, proč slicer kostku odmítá, a když selže, psát „Cena je odhad“ bez slova „nepovedl“ a nabídnout
  přepočet. Hlášeno řídící session 13. 10.; ta na serveru našla „Loading of a model file failed“ ze sliceru nad
  `placed.stl` u všech čtyř kostek (kalkulace 388–390, 392), stojánek z nástroje projde – podezření na umístění či
  orientaci souměrné kostky; řeší řídící session.
- Odhad: **S–M** (podle příčiny). Vlastník: **řídící** (kalkulace/slicer).

### N5 · Cookie lišta zabírá čtvrtinu telefonu a schovává hlavní akci

- Snímky: všechny `*-390-*-1` bez souhlasu; na 1366 `qr-1366-cs-1` (117 px).
- Co: 3 odstavce textu + 3 tlačítka; na telefonu 207 px, na stránkách nástrojů přes kartu ceny, na `/` přes
  „Nahrát soubor“. Zůstává, dokud člověk nezvolí.
- Návrh: jednořádková lišta „Cookies jen nezbytné · Povolit měření“ se dvěma tlačítky a odkazem; text přesunout
  na `/cookies`. Zachovat pravidlo „Povolit vše“ inkoustové.
- Odhad: **S**. Vlastník: **řídící** (layout).

### N6 · `/tools`: 27 obrazovek karet pod sebou na telefonu

- Snímky: `tools-390-cs-1`, `tools-390-cs-full`, `tools-390-cs-2`, `tools-1366-cs-1`.
- Co: první obrazovka = nadpis, hledání, **9 chipů kategorií** (3 řádky), dvě velké karty „Mám soubor / Chci něco
  vytvořit“; první nástroj až na 2. obrazovce. Každá karta má fotku 356 × 237 + název + 2 řádky + odkaz ≈ 480 px;
  53 odkazů → 22 781 px. Na 1366 (3 sloupce, 8 149 px) je to únosné. Nové fotky jsou pěkné a jednotné.
- Proč odrazuje: na telefonu se nedá přehlédnout nabídka; kategorie nahoře nic nesbalí (jen filtrují).
- Návrh: na telefonu **kompaktní karta** (fotka 96 px vlevo, název + 1 řádek vpravo) nebo 2 sloupce; sekce podle
  kategorie sbalené s počtem („Jména a dárky · 9“), hledání zůstat; chipy do jednoho vodorovně rolovacího řádku.
- Odhad: **M**. Vlastník: **E** (katalog, úkol #1/#9).

### N7 · Menu začíná „Modely“ – a tam nic není

- Snímek: `models-390-cs-1`, `models-1366-cs-1`.
- Co: `/models` = „V tomto výběru zatím není žádný model.“ + tlačítko „Vytisknout vlastní soubor“. První položka
  hlavní navigace na každé stránce.
- Návrh: položku z menu skrýt, dokud není ≥ 6 modelů (nebo přejmenovat na „Dárky“ → `/gifts`, která je plná a
  dobrá: `gifts-390-cs-1`).
- Odhad: **S**. Vlastník: **řídící**.

### N8 · Po vytvoření reliéfu na telefonu zůstane člověk u tlačítka, výsledek je 2 400 px nad ním

- Snímky: `relief-390-cs-wait`, `relief-390-cs-done` (scrollY 2396, vidět jen posuvníky a „Materiál“), pro
  srovnání `relief-1366-cs-done` (model Lincolna a cena vedle sebe).
- Co: po „Vytvořit model a ukázat ceny“ běží 40 s („Vytvářím model, pár vteřin…“), pak se naplní prohlížeč
  nahoře, ale stránka se neposune; na telefonu nic nenaznačí, že je hotovo (cena ≈ 80–130 Kč je mimo obrazovku).
  Totéž busta: „Hotovo, otevírám kalkulaci…“ a nic se neotevře (`figure-390-cs-result`).
- Návrh: po dokončení na `< lg` odskrolovat na prohlížeč + cenu (`scrollIntoView`), nebo kartu ceny zobrazit
  přilepenou dole; text „pár vteřin“ → „asi minutu“ (reliéf 40 s, busta 1–3 min).
- Odhad: **S**. Vlastník: **E** (stránka nástroje), **D** (busta).

### N9 · Busta: host má 1 model denně a dozví se to až po nahrání

- Snímky: `figure-390-cs-wait` („Zaškrtněte prosím souhlas.“), `figure-390-cs-done` („Dnešní limit (1) je vyčerpaný.
  S účtem máte 3 modely denně.“, HTTP 429).
- Co: souhlas se zpracováním fotky je nutný, ale bez zaškrtnutí jen krátká hláška u tlačítka; limit 1/den se ukáže
  až po klepnutí (napsáno malým písmem pod tlačítkem „Zdarma: 1 model denně bez účtu, 3 s účtem“).
- Návrh: souhlas jako součást tlačítka („Vytvořit model – souhlasím se zpracováním fotky“) nebo předzaškrtnutý s
  odkazem; limit a čas („asi minutu“) říct nad tlačítkem před nahráním; po vyčerpání nabídnout registraci přímo.
- Odhad: **S**. Vlastník: **D**.

### N10 · Chybová hláška v angličtině a limit 15 MB až po nahrání (reliéf)

- Snímek: `err-relief-big-390-cs-1`.
- Co: fotka 77 MB → „The photo field must not be greater than 15360 kilobytes.“ (surová Laravel validace, 422),
  česká stránka; limit nikde předem; chyba se ukáže jen v `aria-live` textu.
- Návrh: hlídat velikost v prohlížeči před odesláním (a fotku rovnou zmenšit na 2 000 px, telefony dávají
  8–12 MB), vlastní text „Fotka je větší než 15 MB, zmenšete ji“, limit napsat u pole.
- Odhad: **S**. Vlastník: **E**.

### N11 · Kalkulace na telefonu: cena až na druhé obrazovce, první věta je oranžové varování

- Snímky: `flow-stand-390-cs-4`, `flow-stand-1366-cs-4`.
- Co: po „Pokračovat ke kalkulaci“ (1 klik z nástroje, správně) je nahoře prohlížeč, oranžový box „Deska s háčky se
  tiskne nastojato…“, tlačítko „Upravit tvar a rozměry“, věta „Zdarma. Tento model počítáme sami, úpravy nestojí
  žádné AI kredity.“ – a teprve pak „Převádím formát… ≈ 57 min · ≈ 90–140 Kč“. Na 1366 je cena hned vpravo.
- Proč odrazuje: „AI kredity“ nikdo nečekal; oranžový box vypadá jako chyba; cena, kvůli které člověk klikl, není vidět.
- Návrh: na telefonu kartu ceny první (pod názvem souboru), orientační poznámku o tisku šedou a kratší, větu o AI
  kreditech ukázat jen u modelů z AI; LCP kalkulace na telefonu 1,9–3,5 s (model se stahuje) – ukázat cenu dřív
  než model.
- Odhad: **S–M**. Vlastník: **řídící**.

### N12 · Blog: první článek posílá k printpal.io a stránka má 10,6 MB

- Snímek: `trust-blog-390-cs-1`.
- Co: `/blog` v patičce každé stránky; první karta = screenshot printpal.io, titulek „PrintPal.io a 4 další AI
  nástroje…“, pod ním fialový banner „Generate. Customize. 3D print.“ (cizí). Stránka přenese **10 644 kB**
  (12 požadavků) – obrázky bez komprese; ostatní stránky 600–700 kB.
- Proč odrazuje: na našem webu propagujeme konkurenta s lepšími obrázky; na mobilních datech se blog načítá dlouho.
- Návrh: obrázky na webp ≤ 200 kB (jako karty), první článek nahradit vlastním (např. „Jak vzniká busta z fotky“ s
  našimi výtisky), nebo blog z patičky dočasně vyndat.
- Odhad: **S** (obrázky), **M** (článek). Vlastník: **řídící**.

### N13 · Stránky důvěry jsou úplné, ale bez tváře a výtisků

- Snímky: `trust-about-390-cs-1`, `trust-contact-390-cs-1`, `trust-faq-390-cs-1`, `trust-materials-390-cs-1`,
  `trust-about-390-en-1`, `home-footer-390-cs-1`.
- Co: O nás, Kontakt (s.r.o., IČO, sídlo, e-mail), FAQ (11 otázek), Materiály (tabulky), OP, reklamace, GDPR –
  vše existuje cs i en, srozumitelně. Chybí jediná fotka skutečného výtisku nebo farmy, jméno člověka, doba dodání
  v číslech na jednom místě; „ověřeno tiskem 4. října 2026“ u nástrojů je dobré, ale jen u některých.
- Návrh: na O nás 3 fotky z farmy + 1 věta kdo jsme; do patičky „Tiskneme v Praze · doručení 3–5 dní · 2 lidé“;
  štítek „ověřeno tiskem“ ke všem nástrojům, které prošly tiskem.
- Odhad: **S** (texty + fotky od Romana). Vlastník: **řídící**.

### N14 · Nejednotné drobnosti ve stylu

- Snímky: `home-1366-cs-1` (tři oranžové prvky vedle sebe: tlačítko, dva obrysy), `flow-stand-390-cs-5`
  („Přepočítat“ oranžové, „Mám tiskárnu, stáhnout“ oranžový obrys), `sign-390-cs-1` vs. `box-390-cs-1` (lišta
  prohlížeče zabírá 2 řádky, na QR stránce je pod ní ještě odstavec textu, který na telefonu nikdo nečte).
- Co: pravidlo z N.md „oranžová jen na jedné hlavní akci“ hlavní stránka a kalkulace neplní; karty nástrojů na `/`
  mají štítky „Nejčastěji tištěné“ a „ověřeno tiskem“ různě; mezery mezi kroky v panelu nástroje jsou na telefonu
  větší než mezi kartami.
- Návrh: projít `/`, kalkulaci a stránku nástroje jedním průchodem: jedna oranžová akce na obrazovku, vedlejší
  inkoustové/obrysové šedé; sjednotit štítky karet.
- Odhad: **S**. Vlastník: **řídící** + **E**.

### N15 · Prázdný text u cedulky: náhled dál ukazuje „Jana“

- Snímek: `err-sign-390-cs-1`.
- Co: smažu text → pole zčervená, tlačítko „Pokračovat ke kalkulaci“ zešedne a u modelu je „Napište text.“ – správně;
  ale model „Jana“ 53,4 × 28,7 mm a cena 80–130 Kč zůstanou, takže to vypadá, že se nic nestalo.
- Návrh: při prázdném povinném poli zešednout i cenu a model (nebo ukázat prázdnou destičku).
- Odhad: **S**. Vlastník: **1**.

### N16 · Každá stránka nese ~600 kB skriptu a písma, i 404

- Čísla: `404-390-cs-1` 7 požadavků, 605 kB; stránka nástroje 7–8 požadavků, 620–760 kB; LCP 0,2–0,5 s (na drátu).
- Co: jeden balík `app-*.js` + Inter pro všechny stránky. Není to důvod k odchodu (LCP výborné), ale na mobilních
  datech první návštěva stáhne 0,6 MB i kvůli stránce O nás.
- Návrh: 3D prohlížeč (three.js) načítat až na stránkách, které ho mají; písmo podmnožinou. Nízká priorita.
- Odhad: **M**. Vlastník: **řídící**.

### N17 · 404 je v pořádku; staré adresy nevedou nikam užitečným

- Snímky: `404-390-cs-1`, `404-390-en-1`.
- Co: přátelský text, tlačítko na úvod + odkaz Nástroje, patička. `/wp`, `/signup`, `/signin`, `/dashboard`,
  `/info` z matplace.cz končí tady.
- Návrh: přesměrovat `/signup`, `/signin`, `/dashboard` → `/login`, `/info` → `/about`, `/wp`, `/blog/*` staré →
  `/blog`. **S**, řídící.

## 3. Porovnání s printpal.io a MakerWorld MakerLab

Snímky `cmp-printpal-390-en-1`, `cmp-printpal-1366-en-1`, `cmp-printpal-390-en-full`, `cmp-makerlab-390-en-1`,
`cmp-makerlab-1366-en-1`.

**Co dělají pro první dojem lépe**

- **Obrázek dřív než formulář.** printpal: každý nástroj = fotorealistický obrázek hotového výtisku (dračí flexi,
  štěně, mapa na stole) + 1 věta + „Try It Now“; na 1366 šest karet nad ohybem. MakerLab: hero „Create Easily,
  Customize Freely“ + karusel výsledků. U nás je na telefonu první obrazovka nástroje prohlížeč bez slov a hlavní
  stránka pole pro soubor.
- **Jedna věta hodnoty u každé karty** („Type any city or landscape and create a 3D printable map model“) – naše
  karty mají 2–3 řádky popisu, často s detaily (druhy stojánků), které patří až na stránku nástroje.
- **Méně kroků k výsledku na první pohled**: karta → nástroj → hotový model; žádné „Materiál a počet kusů“ před
  cenou. (Ve skutečnosti printpal vyžaduje registraci a kredity, MakerLab účet Bambu – to na první obrazovce nevidíte.)
- **Hledání nahoře a kategorie jako záložky**, ne 9 chipů.

**Co děláme lépe**

- **Bez přihlášení a cena hned** (printpal na telefonu otevře modal „Desktop Recommended – mobile site under
  construction“; MakerLab chce účet). To je naše největší výhoda a na první obrazovce ji neříkáme.
- **Lehký web**: 8 požadavků / 0,65 MB proti 50–217 požadavkům / 1,6–2,0 MB; LCP 0,3 s proti 0,9–1,1 s.
- **Vlastní tisk a dodání** (farma, Zásilkovna, „ověřeno tiskem“), materiály vysvětlené lidsky.
- **Česky** – printpal i MakerLab jen anglicky; pro 46 cs návštěvníků týdně to je jediná volba.

Rozdíl, který stojí za pojmenování: oni prodávají **výsledek** (obrázek věci, kterou dostanete), my
**mechanismus** (prohlížeč, parametry, formáty). Kolo 2 má obrátit pořadí: výsledek první, mechanismus pod ním.

## 4. Návrh kola 2 (nejvýš 10 změn, jednotný styl má přednost)

| # | změna | nálezy | odhad | vlastník |
|---|---|---|---|---|
| 1 | Stránka nástroje na telefonu: název + věta nad prohlížečem, prohlížeč 50 vh s jedním tlačítkem „Pohled“, karta ceny s hlavní akcí hned pod ním; u fotkových nástrojů ukázkový výtisk místo prázdné plochy | N1, N8, N14 | M | E |
| 2 | Kalkulace: hlavní akce „Vytisknout u nás“ i pro hosta (přihlášení/registrace s návratem), „Přepočítat“ inkoustové; na telefonu cena první | N2, N11, N14 | M | řídící |
| 3 | Přesný výpočet: příčina selhání na kostce + lidská hláška | N4 | S–M | řídící |
| 4 | Hlavní stránka: hero „Co chcete vyrobit?“ s 6 dlaždicemi (fotky karet), soubor jako jedna z nich, SEO text a FAQ zkrátit | N3 | M | řídící |
| 5 | Cookie lišta na jeden řádek | N5 | S | řídící |
| 6 | `/tools` na telefonu: kompaktní karty, sbalené kategorie s počty, chipy v jednom rolovacím řádku | N6 | M | E |
| 7 | „Modely“ z menu, dokud je prázdné (nebo → Dárky) | N7 | S | řídící |
| 8 | Fotkové nástroje: po dokončení odskrolovat na výsledek; souhlas a limit říct před nahráním; velikost fotky hlídat v prohlížeči, česká hláška | N8, N9, N10 | S | E + D |
| 9 | Blog: obrázky ≤ 200 kB, první článek vlastní nebo blog z patičky | N12 | S | řídící |
| 10 | Důvěra: 3 fotky z farmy na O nás, řádek v patičce „Tiskneme v Praze · 3–5 dní“, „ověřeno tiskem“ všude, kde platí; přesměrování starých adres | N13, N17 | S | řídící |

Pořadí 1–4 dává největší posun; 1 a 6 jsou jedna šablona, takže se vyplatí dělat spolu (jednotný styl karet a
stránek nástrojů).

## 5. Co nešlo posoudit

- **Skutečné chování lidí na stránce** (kam klikají, kde odcházejí v rámci stránky): máme jen vstupní stránky a
  počty; bez záznamu posunu/kliků (nebo aspoň události „viděl cenu“, „klikl Pokračovat“) je N1 vs. N3 odhad podle
  snímků, ne měření. Doporučuji 4 události do GA: `tool_view`, `price_seen`, `calc_open`, `order_click`.
- **Podíl telefonů** z produkce jsem nedostal; řadím podle „z vyhledávače většina na telefonu“ ze zadání.
- **Dokončení objednávky** (farma, platba, Zásilkovna): hostovi zavřeno, nezkoušel jsem s účtem.
- **Busta – kvalita výsledku a čekání na druhé fotce**: limit 1/den, viděl jsem jeden výsledek (Lincoln, 51 × 32 ×
  80 mm, ≈ 80–130 Kč, vypadá dobře), čas 1–3 min.
- **Španělština**: jen namátkou (`/es` existuje), bez snímků; 0 návštěvníků.
- **Rychlost na skutečné mobilní síti**: čísla jsou z drátu; 0,65 MB na stránku na 3G = 3–5 s, blog 10 MB = desítky s.
- **Proč 143 lidí přišlo „přímo“**: může být YouTube aplikace, Messenger, QR z výtisků – neumím oddělit; QR cedulka
  na výtiscích by mohla mít `?src=qr`.
- **Co lidé hledali na Googlu** (dotazy): bez Search Console nevím, jestli přicházejí na „stojánek na telefon“ nebo
  na „3D tisk cena“ – podle toho se liší, co má být na první obrazovce nástroje.

## 6. Jak to bylo měřeno (pro opakování)

`C:\tmp\review.mjs` (session B, není v repu): na každou stránku čerstvý headless Chrome, emulace iPhone 390 × 844
(2×, dotyk, UA Safari) nebo 1366 × 768, `Accept-Language` podle jazyka, bez cookies (nebo s `consent=a0m0` u cest,
kde se lišta už řešila), 5 s po navigaci skript: LCP (`PerformanceObserver`, buffered), počet a bajty požadavků
(`performance.getEntriesByType`), viditelné `h1`/nadpisy/pole/tlačítka/obrázky/fixní prvky, pak `captureScreenshot`
(webp 78 %, celá stránka 55 % v poloviční velikosti). Interaktivní cesty: `DataTransfer` na `input[type=file]`,
klik na `#tool-go`, `then` skript po navigaci. Soubory: `C:\tmp\review_out\facts.json` + webp; vybrané snímky v
`docs/img/review/` (53 souborů, každý ≤ 300 kB, většina pod 100 kB).
