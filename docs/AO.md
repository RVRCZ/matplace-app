# Objednávka tisku bez účtu a vrácení peněz na kartu (session G)

Větev `feature/guest-checkout` (z `main` 3b5f4da5), od 28. 10. 2026. Zadání `docs/prompts/objednavka-bez-uctu.md`
(Roman 28. 10.: „spusť to“; audit prodeje D16). Nemergováno, nenasazeno; merguje řídící (`conceprt19092026-91`).
Žádné volání Stripe naostro, testy jdou přes `FakeGateway`.

## 1. Dávka 1+2: zakázka hosta a platba kartou bez účtu

### 1.1 Co se děje

- **Zakázka hosta.** `farm_orders.user_id` je nově nullable a přibyl `anonymous_session_id` (FK na `anonymous_sessions`,
  `nullOnDelete`). Host (cookie `mp_sid`) založí zakázku z kalkulace („Objednat tisk“ teď posílá kalkulaci i za hosta),
  ze stránky `/farm` (nahrání, karta designéra, inspirace) i opakováním. `OrderService::create(?User …, ?AnonymousSession)`.
  Vlastník zakázky = účet, nebo relace, která ji založila (token zakázky je v adrese).
- **Kdo zakázku vidí** (`OrderController::authorizeOrder`): účet zakázky, admin, anonymní relace, která ji založila (i po
  zaplacení, kdy už má účet), a prohlížeč, který otevřel **podepsaný odkaz z potvrzovacího e‑mailu** (token zakázky se
  uloží do session `farm.orders`, další dotazy stránky jdou bez podpisu). Kdo nic z toho nemá, dostane 404.
- **Co je jen pro přihlášené**: platba z kreditu (`/pay`, dál `verified.email`), „Moje tisky“, kredit, přepínač
  YouTube. Host na stránce zakázky nevidí kredit ani „Moje tisky“, místo nich „Máte účet? Přihlásit se“ (přihlášení
  vrací na stránku, `User::claimSession` teď převezme i nezaplacené zakázky relace).
- **Denní limit** (`daily_slices_per_user`) počítá host po relaci (`slicesToday(User|AnonymousSession)`).
- **Farma není veřejná** (`farm.public` false): host jde na přihlášení jako dřív (`/farm` přesměruje, `store` 403).
- **Platba kartou hosta.** Ve 3. kroku pole **e‑mail** (povinné, `email:rfc`, pamatuje se v session pro další
  zakázku). `OrderController::checkout` jde stejnou cestou jako účet bez kreditu: `OrderFlow::pay` zkontroluje volby,
  cenu, doručení a adresu, `Wallet::hold` u zakázky bez účtu vždy hodí `InsufficientCredit` (host kredit nemá), takže
  vznikne `Payment` (purpose `order_pay`, `user_id` null, `context.email`, `context.locale`) na celou cenu
  (`max(ceil(cena), min_charge)`). Stripe Checkout dostane `customer_email` z účtu nebo z `context.email` a položka
  se jmenuje „Tisk „název“ (matplace)“, ne „Kredit matplace“. `verified.email` už není u `store` ani `checkout`.
- **Webhook** (`CreditController::webhook`): platba bez účtu → `GuestCheckout::attach` (pod zámkem řádku platby,
  dvojí doručení webhooku nic nezdvojí):
  - e‑mail nikomu nepatří: **nový účet** (jméno z adresy, jinak část e‑mailu před @; náhodné heslo; `email_verified_at`
    null; jazyk stránky, ze které se platilo; telefon a adresa z doručení do profilu; role customer). Účet převezme
    relaci jako registrace (`claimSession`: soubory, kalkulace, nezaplacené zakázky).
  - e‑mail patří účtu: zakázka (a její model, když byl relace) se **připojí k němu**, nic jiného; nikdo se nepřihlásí,
    relace hosta zakázku vidí dál.
  - do `payments.context.account` se zapíše `created` / `attached`, do historie zakázky událost „paid by a guest: …“.
  - pak jako u každé karty: `Wallet::topUp` + `OrderFlow::payFromCard`. Po prvním úspěšném zaplacení jde hostovi
    **`FarmGuestOrder`**: „Zakázka F26‑… je zaplacená“, tlačítko „Zobrazit zakázku“ (podepsaný odkaz platný 90 dní) a u
    nového účtu odkaz **„Nastavit heslo“** (podepsaný, 14 dní; při otevření vytvoří čerstvý reset token, protože
    reset token Laravelu platí jen 60 min, a přesměruje na formulář; po nastavení hesla se e‑mail považuje za ověřený).
    U existujícího účtu věta „E‑mail už u nás účet má, zakázku jsme k němu připojili“.
  - nezaplaceno (cena se mezitím změnila, cívka došla): peníze zůstanou jako kredit nového účtu, obsluze přijde mail
    „Platba hosta … dorazila, ale zakázka se nezaplatila (důvod)“. (Dávka 3 to změní na vrácení na kartu.)

### 1.2 Rozhodnutí (zadání je nechávalo otevřené)

- Jméno a adresa jako dnes: u osobního odběru se jméno neptá, účet dostane jméno z e‑mailu.
- U existujícího účtu se relace **nepřevádí** (soubory relace nejdou k cizímu účtu jen proto, že někdo napsal jeho
  e‑mail); jde jen zakázka a její model.
- Přebytek nad cenou (zaokrouhlení, minimum brány 15 Kč / 0,50 €) zůstane jako kredit na účtu, který platba založila.
  U hosta je to nejvýš 14 Kč nebo 0,99 €.
- `pay` (kredit) vrací hostovi 401; přihlášenému, kdo zakázku vidí jen přes relaci nebo podepsaný odkaz, 403 (kredit
  platí jen vlastník, admin dál smí jako dřív).

### 1.3 Ověření

- `tests/Feature/FarmGuestCheckoutTest.php` (5 testů, 88 asercí): host založí zakázku (bez účtu, s relací), cizí relace
  dostane 404; stav říká `guest`, kredit 0; platba bez e‑mailu 422, kreditem 401; karta s e‑mailem → `Payment` bez
  účtu na celou cenu (min. 15 Kč), cestou k bráně nevznikne nic; webhook → nový účet (neověřený, role customer), soubor
  relace převzatý, zakázka zaplacená a ve frontě, relace ji vidí dál, mail `FarmGuestOrder` s podepsaným odkazem a
  „Nastavit heslo“; podepsaný odkaz otevře zakázku v jiném prohlížeči a ten pak jde dál bez podpisu; odkaz na heslo →
  reset → heslo platí a e‑mail je ověřený, pak odkaz vede na přihlášení; druhý webhook nic nezdvojí. E‑mail
  existujícího účtu → zakázka připojená, žádný druhý účet, relace se nepřevádí, nikdo nepřihlášen, mail „připojili
  jsme“. Přihlášení převezme nezaplacenou zakázku hosta. Neveřejná farma: `/farm` → přihlášení, `store` 403. Tlačítko
  kalkulačky zakládá i za hosta.
- Dvě starší aserce šly proti zadání a jsou upravené: `AccountsTest` (neověřený účet už zakázku založit smí, dobít
  kredit dál ne) a `FarmOrderFlowTest` (host jde na přihlášení jen u neveřejné farmy).
- `FarmGuestCheckoutTest|FarmSalesPathTest|FarmCardPaymentTest|FarmOrderFlowTest|FarmAdminNotifyTest|AuditRestTest|
  FarmStatusColumnsTest|FarmPagesTest|AccountsTest|FarmBigModelFlowTest|FarmWalletTest|AuditEventsTest|SalesPathWebTest`:
  118 testů, 3 266 asercí, zelené po úpravě dvou výše (6,5 min). `pint --dirty`, `tsc`, `npm run build` prošly.
- Migrace `2026_10_28_100000_guest_orders` proběhla na místní **MariaDB 12.2** (celá sada od nuly): `user_id` u
  `farm_orders` i `payments` je `DEFAULT NULL` a cizí klíč (cascade) zůstal, `anonymous_session_id` s `ON DELETE SET NULL`.
- Stránka zakázky hosta v headless Chrome (`scripts/review_round2.mjs`, místní server na kopii DB s falešnými enginy,
  otevřeno podepsaným odkazem) na **390 a 1280 px, cs/en/es**: žádné vodorovné rolování, žádná chyba JS; místo kreditu
  „Máte účet? Přihlásit se“, ve 3. kroku pole e‑mailu s nápovědou, tlačítko „Zaplatit kartou 219 Kč a spustit tisk“,
  žádné „Dobít kredit“. Odkaz „Přihlásit se“ má výšku 20 px (stejně jako dřívější „Moje tisky“ na tom místě).

### 1.4 Soubory

`database/migrations/2026_10_28_100000_guest_orders.php`, `app/Domain/Farm/{GuestCheckout (nový),OrderService,Wallet}.php`,
`app/Http/Controllers/Farm/{OrderController,CreditController,GuestController (nový)}.php`,
`app/Http/Controllers/Auth/AuthController.php` (reset hesla ověří e‑mail), `app/Http/Controllers/Admin/FarmOrderController.php`,
`app/Mail/FarmGuestOrder.php` + `resources/views/mail/farm_guest_order.blade.php` (nové), `app/Models/{FarmOrder,User}.php`,
`app/Engines/Payment/StripeGateway.php`, `routes/web.php`, `resources/views/farm/{order,start,cta}.blade.php`,
`resources/views/admin/farm/order.blade.php`, `resources/js/calc/farm.ts`, `lang/src/guest_checkout.json` → `lang/{cs,en,es}.json`
(`farm.guest.*`, nové znění `farm.cta_hint_guest`), `tests/Feature/{FarmGuestCheckoutTest (nový),AccountsTest,FarmOrderFlowTest}.php`.

### 1.5 Nasazení

`php artisan migrate` (jedna migrace), `npm run build` (farm.ts), `view:clear`. Nic v `.env`. Stripe: nic nového
(`customer_email` a název položky jdou v `checkout.sessions.create`).

Sloučeno a nasazeno řídící 28. 10. 2026 (main e13180d2, server a31bb2a4).

## 2. Dávka 3: vrácení peněz na kartu a jeden automatický opakovaný tisk

### 2.1 Co se děje

- **Brána umí vracet.** `PaymentGateway::refund(Payment, float $amount, string $reason): string`. Stripe: z Checkout
  session vezme `payment_intent` a zavolá Refunds API (`reason` requested_by_customer, náš důvod v `metadata`,
  idempotency key podle platby, důvodu, částky a pořadí). `FakeGateway`: vrátí `fake_refund_…`; v testech
  `FakeGateway::$refundFails` simuluje odmítnutí.
- **Tabulka `payment_refunds`**: payment_id, farm_order_id, amount, currency, reason (`print_failed`, `cancelled`,
  `cancelled_running`, `not_paid`, `admin`), note (adminův důvod), gateway_ref, status (`pending`/`done`/`failed`),
  error, created_by, časy. U zakázky `refunds()`, v historii události „refunded 120.00 CZK to the card (…)“ nebo
  „card refund #… failed: …“.
- **Kde peníze jsou** (`App\Domain\Farm\CardRefunds`). Platba kartou za zakázku jde i dnes přes kredit (topUp + hold),
  proto vrácení na kartu:
  - **z kreditu**, když už zakázka peníze vrátila (`Wallet::giveBack` po selhání nebo zrušení; platba hosta, která
    zakázku nezaplatila): jeden řádek `card_refund` (−částka, bez zakázky, poznámka `#id F26-…`);
  - **za zakázku**, když jsou peníze ještě zablokované nebo stržené (admin vrací část dotištěné zakázky): řádek
    `refund` (+, u zakázky) a `card_refund` (−). Zůstatek se nehne, zakázka drží o tolik méně.
  - Na kartu jde nejvýš to, co karta za zakázku zaplatila a ještě se nevrátilo (`refundable`; víc plateb: od
    nejnovější). Co bylo placeno z kreditu, zůstane kreditem. Z kreditu jen to, co v kreditu ještě je.
  - Brána se volá v jobu **`RefundToCard`** po commitu (`afterCommit`, 1 pokus). Do účetní knihy se zapíše až po
    přijetí branou. Při odmítnutí: refund `failed` s chybou, peníze zůstanou jako kredit (nebo jak byly), admin mail
    „F26-…: vrácení na kartu selhalo“ s tím, co dál. Zakázka je na „Čeká na vás“, dokud po neúspěšném vrácení
    nepřijde úspěšné. Zákazníkovi v tu chvíli nic nepřijde.
  - Po úspěchu: zákazníkovi **`FarmCardRefund`** („Vrátili jsme vám peníze za zakázku F26-…“, proč, „Na kartu jsme
    vám vrátili :amount. Banka je připíše obvykle do několika dnů.“), měření `refund`.
- **Pravidla** (`OrderFlow`):
  - Tisk se nepovede naší vinou (`print_failed`, `print_cancelled` od agenta, `operator` od admina) u zaplacené
    zakázky zákazníka. Admin: jen když se tisklo. Agent: i když start selhal ve frontě. Zakázka jde
    `printing → failed → queued` s poznámkou „automatic reprint 1 after …“ a `farm_orders.reprints` = 1. Peníze se
    nehýbou. Zákazník dostane mail **„Zakázku F26-… tiskneme znovu“** místo „nepodařila se“ a „je ve frontě“.
    Obsluha dostane mail o selhání s řádkem „Co dál: zakázka se tiskne znovu automaticky (2. tisk), peníze zůstávají
    zablokované. Uvolněte a potvrďte podložku.“
  - Druhé selhání: `failed`, `giveBack` vrátí do kreditu a kartou placená část jde hned na kartu (celá cena včetně
    dopravy).
  - Zrušení před tiskem: celá částka na kartu (`cancelled`). Zrušení běžícího tisku: dnešní pravidlo (poplatek a
    vytištěná část zůstanou stržené), zbytek na kartu (`cancelled_running`).
  - Kdo platil z kreditu, dostane kredit jako dřív.
- **Texty zákazníkovi u zakázky placené kartou**: mail o selhání nebo zrušení má variantu `failed_card` /
  `cancelled_card` („Peníze vám vracíme na kartu…“) místo „Kredit jsme vám vrátili“. Chyba na stránce zakázky
  (`farm.refund.error.*`) taky. Stránka ukazuje zelený řádek „Vráceno na kartu: 120 Kč“ (`state.refunded`, jen
  potvrzené branou).
- **Platba hosta, která zakázku nezaplatila** (cena se změnila, cívka došla): celá platba jde zpátky na kartu
  (`not_paid`), obsluha dostane mail s řádkem „Peníze (:amount) se vracejí na kartu“. Host kredit nechtěl.
- **Admin** na stránce zakázky (`#card-refund`, jen u zakázky placené kartou): kolik je vráceno a kolik ještě jde
  vrátit, seznam vrácení (stav, důvod, poznámka, id brány, chyba). Formulář „Vrátit na kartu“ s částkou (výchozí =
  zbývající, víc nejde) a povinným důvodem, s potvrzením. `POST /admin/farm/orders/{order}/card-refund`. Kudy jdou
  peníze, pozná samo: zakázka už nic nedrží → z kreditu, jinak za zakázku. Dosavadní „Vrátit kredit“ zůstal beze změny.
- **Vypínače**: `farm.payments.auto_reprint` (`FARM_AUTO_REPRINT`, výchozí true) a `farm.payments.auto_refund`
  (`FARM_AUTO_REFUND`, true). Vypnuté = dnešní chování (hned selhání, peníze do kreditu, texty o kreditu).

### 2.2 Rozhodnutí

- „Celá částka včetně dopravy“ = `price_total` zakázky. Přebytek platby nad cenu (zaokrouhlení, minimum brány
  15 Kč) zůstane kreditem na účtu.
- Admin, který zakázku označí jako selhanou, když se netiskla (ve frontě), automatický opakovaný tisk nespustí.
  Zákazníkovi by jinak přišlo „tiskneme znovu“ u zakázky, kterou obsluha zastavila kvůli modelu.
- Opakovaný tisk čeká jako každý jiný na potvrzení volné podložky. Odměna designéra se při ručním částečném vrácení
  dotištěné zakázky nevrací (rozhodne admin).
- Brána se zkouší jen jednou: opakovaný pokus by mohl peníze poslat dvakrát. Další pokus dělá admin tlačítkem.
- Stripe webhooky pro refund (`charge.refunded`) nejsou potřeba: výsledek Refunds API je hned v odpovědi.

### 2.3 Ověření

- `FarmGuestCheckoutTest` (12 testů, 241 asercí). Nové:
  - host zaplatí, tisk selže → zakázka znovu ve frontě, reprints 1, žádné vrácení, peníze zablokované, mail
    „tiskneme znovu“ a žádný „nepodařila se“, admin mail s řádkem o opakování;
  - druhé selhání → `failed`, vrácení `done` celé ceny přes falešnou bránu, kredit drží jen zaokrouhlení, jeden řádek
    `card_refund`, maily `FarmCardRefund` a „failed_card“ bez slova o kreditu, `state.refunded`, karetní text
    chyby, událost `refund`, záznam v historii;
  - zrušení před tiskem: celá částka; zrušení při 40 %: cena − stržená část;
  - zakázka placená kreditem: kredit zpět, žádné vrácení na kartu. Smíšená (10 Kč kredit + karta): na kartu jen
    platba kartou, 10 Kč zpět do kreditu;
  - brána odmítne → `failed`, peníze v kreditu, admin mail, sekce na admin stránce a zakázka na „Čeká na vás“. Ruční
    „Vrátit na kartu“ pak projde. Víc než zbývá: chyba validace;
  - admin vrátí 20 Kč z dotištěné zakázky: zůstatek se nehne, řádek `refund` u zakázky;
  - vypnuté přepínače: hned `failed`, kredit, starý text mailu;
  - platba hosta po změně ceny: celá zpátky na kartu, kredit 0, žádné potvrzení, mail o vrácení.
- Čtyři starší testy o selhání, které zakázku končí hned (`FarmAdminNotifyTest` ×2, `FarmOrderFlowTest` ×2), mají
  `auto_reprint` vypnuté (s poznámkou). Opakování ověřuje `FarmGuestCheckoutTest`.
- `FarmStatusColumnsTest` hlídá i `payment_refunds.status`, `payment_refunds.reason` a `credit_transactions.type`.
- Sada `Farm|AuditRest|Accounts|AuditEvents|SalesPathWeb|PublicStats|Shipping` (218 testů, 12,5 min): zelená kromě
  těch čtyř, ty po úpravě znovu zelené. `pint --dirty`, `npm run build` (tsc, vite, kontroly) prošly.
- Migrace `2026_10_28_110000_card_refunds` na místní MariaDB 12.2: nahoru, zpět, znovu nahoru. Cizí klíče: payment
  cascade, zakázka a admin SET NULL. `reprints` tinyint unsigned default 0.
- Neověřeno: skutečné Stripe Refunds API (žádné volání naostro; kód podle SDK, `checkout.sessions.retrieve` +
  `refunds.create`). Admin sekce v prohlížeči (ověřena jen testem, který čte HTML). Opakovaný tisk přes agenta
  (testovaný je admin; agent volá tutéž `fail()`).

### 2.4 Soubory

Nové: `database/migrations/2026_10_28_110000_card_refunds.php`, `app/Models/PaymentRefund.php`,
`app/Domain/Farm/CardRefunds.php`, `app/Jobs/RefundToCard.php`, `app/Mail/FarmCardRefund.php` +
`resources/views/mail/farm_card_refund.blade.php`, `lang/src/card_refunds.json`.

Změněné: `app/Engines/Contracts/PaymentGateway.php`, `app/Engines/Payment/{FakeGateway,StripeGateway}.php`,
`app/Domain/Farm/OrderFlow.php` (`fail`, `printsAgain`, `onStopped`, `notifyCustomer`, `adminFailed`),
`app/Mail/FarmOrderStatus.php` (`REPRINT`, `*_card`), `app/Http/Controllers/Farm/{CreditController,OrderController}.php`,
`app/Http/Controllers/Admin/FarmOrderController.php` (`cardRefund`, `show`, `attention`),
`app/Models/{FarmOrder,Payment,CreditTransaction}.php`, `app/Support/Track.php` (`refunded`), `config/farm.php`,
`routes/web.php`, `resources/views/{admin/farm/order,farm/order}.blade.php`, `resources/js/calc/farm.ts`,
`lang/{cs,en,es}.json`, testy výše.

### 2.5 Nasazení

`php artisan migrate` (jedna migrace), `npm run build`, `config:clear`, `view:clear`. V `.env` nic povinného
(volitelně `FARM_AUTO_REPRINT`, `FARM_AUTO_REFUND`). Worker fronty musí běžet (job `RefundToCard`, jako maily).
Klíč Stripe musí smět vytvářet refundy (běžný secret key smí; omezený klíč by potřeboval právo Refunds).

## 3. Dávka 4+5: měření a omezení zneužití

### 3.1 Měření (`App\Support\Track`)

- `order_created` a `checkout_started` vznikají i za hosta (návštěva hosta, `user_id` null). `order_paid` kartou se
  připojí k návštěvě, která zakázku založila (`Track::paidByCard`, beze změny; `user_id` je už účet z platby).
- **`register` s `kind: checkout`** (`Track::registeredAtCheckout`) jednou za účet, který založila platba hosta, v
  návštěvě, která zakázku založila; `meta` `kind`, `order`, `event_id register-<id>`. E‑mail existujícího účtu
  registrace není.
- **`refund`** (`Track::refunded`, §2) s `value`, `currency`, `order`, `kind` = důvod. Není v `Track::ACTIONS`.
- Session 3 (`conceprt19092026-01`) dostala 28. 10. zprávu: registrace je v trychtýři volitelný krok, `register`
  kind `checkout` přichází až po `order_paid`.

### 3.2 Omezení

- **Za hodinu z jedné adresy** nejvýš `farm.guest.orders_per_hour_ip` zakázek hosta (`FARM_GUEST_ORDERS_PER_HOUR_IP`,
  10). Pak 429 `guest_limit` s „Z této adresy přišlo za poslední hodinu hodně objednávek bez účtu. Zkuste to za N min,
  nebo se přihlaste.“ Tlačítko kalkulačky větu ukáže u sebe. Účty se do počtu nepočítají.
- Denní limit `daily_slices_per_user` počítá hosta po relaci (§1). Dosavadní `throttle:20,1` u `store` zůstal.
- Soubor hosta se tiskne až po zaplacení (beze změny: tisk začíná stavem `paid`). Stripe Radar beze změny.
- **Úklid** (`matplace:prune`): zakázky hosta nezaplacené `farm.guest.unpaid_days` (`FARM_GUEST_UNPAID_DAYS`, 7) dní
  ve stavech awaiting_choices, uploaded, sliced a failed se zruší systémem („guest order not paid within 7 days“).
  Nic nebylo zablokované. Soubor zůstane relaci a odejde s anonymními soubory po 30 dnech (zrušená zakázka s ním,
  `model_file_id` cascade).
- **Pojistka v úklidu**: anonymní soubor, ze kterého tiskne zaplacená zakázka, se nesmaže. Smazání souboru by
  zakázku smazalo s ním. Dnes ho platba převede na účet, takže jde jen o pojistku.

### 3.3 Ověření

- `FarmGuestCheckoutTest` (15 testů). Nové:
  - `order_created` hosta bez účtu v návštěvě, `order_paid` ve stejné návštěvě, `register` kind checkout s návštěvou
    a zakázkou; e‑mail existujícího účtu žádný `register`;
  - třetí zakázka z adresy za hodinu (limit 2) → 429 `guest_limit`, účtu limit nevadí;
  - `matplace:prune`: nezaplacená zakázka hosta starší 8 dní zrušená s poznámkou, čerstvá zůstala `sliced`,
    zaplacená nezrušená a její (uměle anonymní, 40 dní starý) soubor nesmazaný.
- Sada `Farm|AuditRest|Accounts|AuditEvents|SalesPathWeb|PublicStats|Shipping|Stats|Funnel|Prune`: **235 testů,
  5 402 asercí, zelené** (3 přeskočené jako dřív, 13,6 min). `pint --dirty` a `npm run build` prošly.

### 3.4 Soubory a nasazení

`app/Support/Track.php` (`registeredAtCheckout`), `app/Domain/Farm/GuestCheckout.php`,
`app/Http/Controllers/Farm/OrderController.php` (limit v `store`), `app/Console/Commands/PruneData.php`,
`config/farm.php` (`farm.guest`), `resources/js/calc/farm.ts` (429 u tlačítka), `lang/src/guest_limits.json` →
`lang/{cs,en,es}.json`, `tests/Feature/FarmGuestCheckoutTest.php`. Nasazení: bez migrace; `npm run build`,
`config:clear`. V `.env` nic povinného.

## 4. Co zbývá a co čeká na Romana

- Rozhodnutí, jestli má být automatický opakovaný tisk a vracení na kartu hned zapnuté (výchozí ano; vypnout jde
  `FARM_AUTO_REPRINT=false` a `FARM_AUTO_REFUND=false`).
- První skutečné vrácení přes Stripe projde až na produkci: stojí za to sledovat první `payment_refunds` řádek a mail
  obsluze.
- Admin sekce „Vrátit na kartu“ v prohlížeči nikdo neviděl (ověřená jen testem, který čte HTML).

## 5. Rozložení zakázky bez skoků (28. 10. 2026, `feature/layout-sticky-order`)

Zadání `docs/prompts/rozlozeni-bez-skoku.md`. Session G má stránku zakázky, session 3 nástroj a kalkulaci (AN.md §18).
Společné kusy navrhla session 3 v commitu 19dfe46c: třídy v `app.css`, `resources/js/site/sticky.ts` a komponenta
`<x-action-bar>`. Tady jsou převzaté cherry-pickem a nijak upravené.

### 5.1 Co je jinak

- **Vlevo náhled a karta ceny.** Karta se stavem a cenou (`#farm-card`) se přestěhovala z pravého sloupce pod náhled do
  `#farm-side`. Na telefonu je pořadí stejné jako dřív: náhled, cena, volby, objednávka.
- **Přilepený je jen během výběru.** Ve stavech uploaded, awaiting_choices, sliced a failed bez čísla dá `farm.ts`
  zakázce `data-choosing` a sloupci `.stick-stage`. Od `lg` pak sloupec stojí v okně a náhled (`flex-1`, nejmíň 180 px)
  ustoupí kartě, karta ustoupit nemusí. Po zaplacení (kamera, časosběr) sloupec roluje se stránkou, jinak by se obrázky
  z kamery nevešly.
- **Vpravo kroky rolují se stránkou, ne ve vlastním posuvníku (`.stick-col`).** Při 1366×768 je nad mřížkou asi
  145 px hlavičky a nadpisu. Spodek sloupce s vlastním posuvníkem by byl po načtení pod oknem a kolečko nad sloupcem
  (overscroll-contain) by stránku neposunulo.
- **Dole lišta na všech šířkách** (`<x-action-bar for="farm-pay-btn" id="farm-bar">`):
  - Vlevo je velká částka a pod ní, co znamená: „Celkem k zaplacení“, „Celkem asi, dopravu upřesní dopravce“, nebo jen
    tisk s řádkem o dopravě, dokud doručení není vybrané. Celou větu by vedle tlačítka na 390 px nebylo vidět.
  - Vpravo je zrcadlo tlačítka Zaplatit. Stejné tlačítko zůstává i na konci formuláře.
  - Lišta je vidět jen se zaplacením na stránce, ve stavu sliced a během přepočtu. Sedí nad cookie lištou a stránka
    pod ní nechává místo (`body.has-action-bar`).
- **Zaplatit u nehotové objednávky.** Tlačítko nemá `disabled`, má `aria-disabled` a vypadá vypnuté. Po stisku řekne
  pod sebou a v liště, co chybí (`payBlocker`). Pořadí kontrol: barva, barva z jiné tiskárny, kombinace bez tiskárny,
  doručení a jeho pole, potom první neplatné pole formuláře (e‑mail hosta, souhlas s podmínkami, se zprávou
  prohlížeče). Pole dostane focus bez skoku (`focusCalm`) a vjede do okna, jen když vidět není. Roluje se až po
  dvou snímcích, protože text chyby lištu zvětší. Enter v poli dělá totéž.
- **Přepočítat a volby.** Během přepočtu zůstanou stará čísla a krabice s placením na místě, jen zašedlé
  (`opacity-50`, placení `inert`). Stránka pod prstem se nezmenší a nic neposkočí. Nová cena dostane prstenec
  (`flash`) na kartě, nová částka v liště taky. Chyba přepočtu se ukáže v kartě a na telefonu vjede do okna.
- **„Změnit“ barvy v souhrnu** roluje k barvám, jen když vidět nejsou (dřív vždy na začátek).
- **Zaplaceno z kreditu**: krabice s volbami zmizí a do okna vjede karta se stavem (dřív zůstal pohled na konci
  stránky).
- Nové texty v `lang/src/layout_order.json` (6 klíčů): krátké popisy tlačítka pro lištu, „Vyberte barvu.“, popisy
  částky. Texty kroků jsou beze změny.

### 5.2 Ověření

- Průchod v headless Chrome přes CDP (skript ve scratchpadu, zakázka hosta v sliced na lokální MariaDB) na 390, 768,
  1280 a 1366×768. Na každé šířce:
  - bez vodorovného posuvu;
  - lišta nezakryje poslední řádek patičky (24 px rezerva);
  - `scrollY` před a po kliknutí na kvalitu: beze změny; po Přepočítat: beze změny;
  - Zaplatit bez výdejního místa: stránka dojede jen k boxu výdejního místa, jeho spodek je přesně nad lištou
    (577 = 844 − 267 px lišt na 390);
  - „Změnit“ dojede k barvám, které nebyly vidět.
- Testy `ToolPageTest|ToolsFlowTest|CalculationFlowTest|FarmGuestCheckoutTest|FarmSalesPathTest|AuditRestTest|FarmOrderFlowTest`:
  **106 testů, 6 665 asercí, zelené**. `tsc` a `npm run build` prošly; PHP se neměnilo.
- Neověřeno: Android s otevřenou klávesnicí v emulaci (lišta je `fixed` nad `--consent-h`), skutečný přepočet trvající
  sekundy. Falešný slicer odpoví hned, takže zašedlý stav byl vidět jen v kódu.

### 5.3 Čeká na session 3 (sdílené `sticky.ts` a `app.css`, požádáno zprávou)

1. Tlačítko lišty dnes zrcadlí `aria-disabled` jako `disabled`. Na liště proto stisk nehotové objednávky nic neřekne,
   na tlačítku ve formuláři ano. Prosba: zrcadlit jen `aria-disabled` a klik předat.
2. Krátký popis z `data-bar-label` zdroje: `farm.ts` ho už plní („Zaplatit kartou“, „Doplatit kartou 120 Kč“). Do
   té doby lišta ukazuje celou větu a na 390 px zúží částku.
3. Výška `.stick-stage` bez spodních lišt: `calc(100vh − 1.5rem − --consent-h − --action-bar-h)`. Dnes je spodek
   karty ceny při 1366×768 pod lištami.

Po těch úpravách se na zakázce nic měnit nemusí, jen znovu projít snímky.
