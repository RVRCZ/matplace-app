# AT · Partnerský program a slevové kódy (session „affiliate“, 17. 11. 2026, větev `feature/affiliate-coupons`)

Zadání `zadani-affiliate-kupony.md`. Stará stránka měla partnery s odkazem `/r/KÓD`, cookie `aff`, přehled v adminu a
promo kódy; nic se nevyplácelo automaticky. Roman rozhodl 17. 11. 2026: provize ze **všeho bez DPH**, výchozí **10 %** a
cookie **30 dní** (u každého partnera jiné), **partneři se registrují sami**, **kupóny hned s programem**.

**Oba přepínače jsou ve výchozím stavu VYPNUTÉ** (`/admin/farm/settings` → „Partneři a slevové kódy“:
`affiliate_enabled`, `promo_codes_enabled`). Vypnuto = návštěvník nevidí nic (stránka programu a partnerova stránka dávají
404, pole „Slevový kód“ na zakázce není) a **žádná cena se nemění**, ani u zakázky, na které už kód leží.

## 1. Jedna cena z jednoho místa (jádro)

Cenu tisku dělá jediné místo, `OrderService::priceFor`. Slevový kód se odečítá právě v něm (`Coupons::applyToPrice` na
hotovém rozpisu v měně zákazníka), takže stejné číslo je:

- cena na stránce zakázky (`describe()`: dlaždice barev, `price`, `total`),
- nabídka `POST /farm/orders/{o}/quote`,
- uložená cena zakázky (`farm_orders.price`, `price_total`; po použití/odebrání kódu ji přepočítá `OrderService::reprice`),
- co se drží z kreditu (`Wallet::hold` bere `price_total`, kterou `OrderFlow::pay` zapsal z téhož `priceFor`),
- co chce karta (`checkout` bere `ceil(chybějící část)` z téhož `total`; `payFromCard` volá znovu `pay`, a ten cenu
  spočítá znovu a porovná s `expected_total`; jiná cena = `price_changed`, nikdy se nestrhne potichu).

Prohlížeč nic nepočítá: pole kódu jen pošle text a dostane stav zakázky; číslo slevy v řádku je `price.discount` ze serveru.

**Jak se sleva počítá.** Z ceny tisku (s DPH, už zaokrouhlené jako vždy: celé koruny, v eurech na 10 centů), v měně, ve které
zákazník platí, **bez druhého zaokrouhlení**: procento nechá cenu tisku zaokrouhlenou nahoru (`roundUp(tisk × (100 − p) / 100)`),
pevná částka v Kč se převede jako ceny (`Money::czk()->to()`, v eurech nahoru na 10 centů) a nikdy nepřesáhne tisk. **Nesnižuje
se doprava ani odměna návrháře** (odměna se počítá z ceny tisku před slevou, `total = tisk po slevě + odměna + doprava`).
Rozpis v `price` dostává `discount`, `print_before_discount`, `coupon{code,kind,value}` a `print_total`, `total` už jsou po slevě;
bez použitelného kódu je rozpis přesně jako dřív (žádné nové klíče).

**Co kód musí splnit** (`Coupons::problem`, použité při přidání a znovu při každém výpočtu ceny): aktivní, v okně platnosti,
nevyčerpaný, tisk před slevou aspoň na minimu v Kč, „jednou na účet“. Kód, který přestal platit (vypršel, vypnutí přepínače),
cenu nesníží; stránka řekne proč (`promo.problem`) a platba s původní cenou odmítne (`price_changed`).

**Použití se počítá při zaplacení**, v transakci platby (`Coupons::redeem` v `OrderFlow::pay`, se zámkem řádku kódu): poslední
použití nejde zaplatit dvakrát (`coupon_used_up`). Vrací se (`Coupons::release`), když zakázka selže nebo se vrátí celá.

**Kód, který zakázku zlevní na 0:** přihlášený zákazník zaplatí z kreditu 0 (nic se nedrží). **Host** nemá co platit kartou
(Stripe bere aspoň 15 Kč / 0,50 €) a účet by vznikl až platbou, proto `free_needs_account`: přihlásit se nebo zaregistrovat,
zakázka zůstane. Host s e-mailem účtu, který už kód „jednou na účet“ použil, je zastaven před kartou (`problemForEmail`).

## 2. Partneři (část A)

- `/r/{KÓD}?to=/cesta` (`AffiliateController::go`, mimo jazykové skupiny, jedna adresa): spočítá klik (ne robotovi:
  `Bots::name`; návštěvník = sha1(IP|den|klíč aplikace), jednou za den), nastaví cookie `aff` (HttpOnly, Secure na https
  a v produkci, SameSite Lax, na dny partnera) a přesměruje na `to` jen když je to cesta tohoto webu (`Affiliates::safeLanding`:
  žádné `//host`, schéma, zpětné lomítko, řídicí znaky), jinak na úvodní stránku. Neznámý/vypnutý kód a vypnutý program jen
  přesměrují. `/r/` je v `robots_disallow`. **Z `config/legacy.php` jsem vyndal `r`** z `to_legacy`: staré odkazy `/r/…` šly na starý
  web, nově je to náš odkaz (kódy starých partnerů musí admin zadat znovu, jinak jen přesměrují na úvodní stránku).
- **Přiřazení:** při založení zakázky (`OrderService::create` → `Affiliates::attributeNew`) se na ni zapíše partner z cookie
  (poslední klik vyhrává). Ne pro účet samotného partnera, ne pro admina, ne pro zkušební/ukázkový tisk. Kód slevy, který
  patří partnerovi, vyhrává nad cookie (při platbě).
- **Provize** vzniká při zaplacení v **jediném místě, kam končí kredit i karta (včetně hosta): `OrderFlow::pay`** (po
  commitu, obalené `Affiliates::safely`, takže chyba v programu platbu nikdy nezhatí). Základ = tisk po slevě + doprava,
  **bez DPH** (DPH tisku z rozpisu; doprava má stejné DPH), **bez odměny návrháře** (ta je návrhářova); procento partnera
  z té chvíle se na provizi zmrazí; měna zakázky. Jedna na zakázku (`unique(farm_order_id)`).
- **Stavy:** `pending` (zaplaceno) → `payable` když je zakázka **dotištěná** (`OrderFlow::onDone`: v tu chvíli se zákazníkova
  platba stává konečnou a týmž okamžikem se připisuje odměna návrháři; pro vyzvednutí osobně se `handed_over` nikdy
  nenastaví, `done` je jediný jistý stav) → `paid` (označí admin) · `void` při selhání/vrácení celé. Částečné vrácení
  provizi úměrně sníží. Vrácení se počítá z knihy kreditu (řádky `release`/`refund` zakázky), takže je jedno, kudy peníze šly
  (selhání, zrušení, ruční vrácení v adminu, vrácení na kartu): `Affiliates::sync` z `OrderFlow::onStopped`,
  `FarmOrderController::refund`, `CardRefunds::run`. Už vyplacená provize se nemění (jen poznámka).
- Nic nevyplácí systém: admin označí provize jako vyplacené (po jedné, nebo „vše k výplatě“) s poznámkou.
- Vypnutý program (přepínač) = od té chvíle nevznikají nové provize (už vzniklé zůstávají).

## 3. Registrace a partnerova stránka (část B)

`/partner` (cs/en/es, 404 při vypnutém programu): vysvětlení, **pravdivá čísla z nastavení** (procento a dny), podmínky
(klíče `affiliate.terms.*`, verze `affiliate_terms_version` = 2026-11, uloží se k partnerovi spolu s časem souhlasu) a formulář
pro přihlášeného s ověřeným e-mailem: jméno, kód (navržený volný), kde odkaz sdílí, údaje pro výplatu, souhlas. Jeden partner
na účet (`unique(user_id)`). Nový partner je hned aktivní s výchozím procentem a dny; provozovatel dostane e-mail
(`OrderFlow::alertAdmin`, druh `partner`, vlastní přepínač `notify_partner` mezi „E-maily obsluze“).
`/account/partner`: odkaz, jednoduchá stavebnice odkazu (výběr stránky nebo vlastní cesta, `?to=`, bez skriptu), kliky po
dnech (30 dní), zakázky, provize se stavem, částky k výplatě a vyplacené (po měnách, nikdy sečtené přes měny). V účtu je
odkaz na ni jen při zapnutém programu.

## 4. Slevové kódy (část C) a admin (část D)

`/admin/promo-codes`, `/admin/affiliates` (odkazy v druhém řádku administrace farmy), nastavení v `/admin/farm/settings`.
Kód: jedinečný (A–Z 0–9 _ -, 2–32), procento nebo pevná částka v Kč, nejnižší cena tisku, od/do (do = celý den), nejvýš
použití, počet použití, jednou na účet, aktivní, partner, poznámka. Pole „Slevový kód“ na stránce zakázky je v kroku
objednávky (jen když je zakázka spočítaná a nezaplacená), s „Použít/Odebrat“, jasnou hláškou ke každému odmítnutí a větou,
že sleva se týká ceny tisku, ne dopravy. Slevový kód je na zakázce, ne na uživateli: **„Tisknout znovu“ kód nenese**
(`repeatOf` ho nepředává; testováno).

**Rozhodnutí tam, kde zadání nechalo volbu**

| Co | Rozhodnutí | Proč |
|---|---|---|
| Základ provize a odměna návrháře | tisk po slevě + doprava bez DPH; odměna návrháře mimo | „tisk a doprava“ ze zadání; odměna je návrhářova a sleva ji nesnižuje (farma nese slevu) |
| Kdy `payable` | při `done` (dotištěno) | okamžik, kdy je zákazníkova platba konečná a kdy dostává odměnu návrhář; `handed_over` u osobního odběru nenastane |
| Odměna návrháře a kód | nesníží se | zadání nechalo na mně; návrhář se nesmí zhoršit rozhodnutím o marketingu |
| Zakázka za 0 pro hosta | `free_needs_account` | bez účtu nelze zakázku potvrdit a kartou 0 nejde zaplatit |
| Částka karty | beze změny (`ceil` chybějící částky, aspoň minimum brány) | existující chování; v eurech karta bere celá eura a zbytek zůstane kreditem, viz „neověřeno“ |
| Stejný kód u přiřazení | kód partnera > cookie > nic | zadání |
| Vypnutý program | nové provize nevznikají | vypnuto = nic se neděje |

## 5. Ověření a co ověřené není

Testy: `AffiliateCouponsTest` (jedno číslo: přihlášený i host, Kč i €, minimum brány, kód na 100 %, doprava, odměna, odmítnutí,
počítání použití, vypnuto, „tisknout znovu“, šířky sloupců) a `AffiliateProgrammeTest` (odkaz, cookie, robot, otevřené
přesměrování, přiřazení, provize kredit/karta/host, stavy, vrácení, registrace, stránky, admin, přepínače). Fiktivní brána,
žádná karta se nestrhla. **Neověřeno:** skutečná platba kartou u Stripe, e-maily, účet v eurech v prohlížeči, telefon,
skutečný `Secure` cookie za proxy produkce, právní text podmínek.

**Text, který musí přečíst Roman nebo právník:** `affiliate.terms.1–8` (cs/en/es v `lang/src/affiliate_coupons.json`), zejména
bod 2 (výplata ručně bez pevného termínu), 3 (vrácení po výplatě) a 7 (daně, osobní údaje); zmínka o údajích partnera patří
i do `privacy` (zatím tam není).

**Zapnutí:** po přečtení podmínek zapnout v `/admin/farm/settings` „Partnerský program“ a/nebo „Slevové kódy“. Migrace
`2026_11_17_100000` (4 tabulky, 5 sloupců `farm_orders`) poběží při nasazení.
