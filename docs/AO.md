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
