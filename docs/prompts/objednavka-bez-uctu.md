# Objednávka tisku bez účtu a vrácení peněz na kartu – session G (nová, conceprt19092026-cf)

Roman 28. 10. 2026: „spusť to“. Podklad: audit prodeje `docs/prompts/audit-2026-10-26.md` bod D16 (objednávka vyžaduje
registraci), rozhovor s řídící (návrh níže). Piš česky, krátce, bez pomlček. Jsi session pro farmu a platby po dobu
tohoto zadání; session 2 (`conceprt19092026-6f`) teď dělá YouTube (FarmVideos, TimelapseCutter, youtube:* příkazy),
jejích souborů se nedotýkej; `lang/*/farm.php` sdílíte, konflikt řeš podle pravidel koordinátora.

## Pracoviště

- Worktree **`C:\matplace-guest-wt`** (připravený: `.env`, `vendor`, `public/build`, `node_modules` je junction na
  `C:\matplace-app\node_modules`, nikdy nemazat `rm -rf`), větev `feature/guest-checkout` z main 3b5f4da5. Nejdřív si
  přečti `docs/prompts/koordinator.md` (pravidla), `docs/AL.md` §8, §11, §12 (platba, stavy zakázky, min. částka brány)
  a `docs/AH.md` §9.3 (cena a doručení). Nemerguješ ani nenasazuješ; po dávkách push + zpráva řídící
  (`conceprt19092026-91`). Deník `docs/AO.md` (nový).
- Testy farmy trvají 7 až 10 min, na pozadí, nikdy dvě sady naráz, během běhu neupravovat soubory. Nový stav nebo sloupec
  porovnej s MariaDB (`FarmStatusColumnsTest`, šířky varchar). Texty jen v `lang/{cs,en,es}/farm.php` nebo
  `lang/src/<soubor>.json` + `python scripts/lang_add.py`, JSON ověřit. Před pushem pint, tsc, build.
- Žádné volání Stripe naostro: `FakeGateway` v testech; na produkci Stripe běží v live režimu (webhook je nastavený).

## Dnešní stav (ověřeno řídící)

- Kalkulace je bez účtu; „Vytisknout u nás“ vidí i host (`farm.public` true), ale stránka zakázky `/farm/orders/{token}`
  je za přihlášením a platba (`pay`, `checkout`) za `verified.email`. Host jde přes přihlášení/registraci s návratem
  (docs/AC.md).
- Platba kartou: `OrderController::checkout` → `Payment` purpose order na `max(ceil(chybí), min_charge)` → Stripe
  Checkout → webhook `CreditController::webhook` → `Wallet::topUp` → `OrderFlow::payFromCard` (kredit se připíše a hned
  zablokuje pro zakázku; zbytek do koruny zůstane jako kredit). Minimum brány 15 Kč / 0,50 € (`farm.payments.min_charge`).
- Nepovedený tisk nebo zrušení: zablokovaná částka se vrátí **jako kredit** (`OrderFlow`, hlášky `print_failed`,
  `print_cancelled`, e-maily „Kredit jsme vám vrátili“). Brána umí jen `checkoutUrl` a webhook, vracení neumí.
- 104 zaplacených zakázek od 6 lidí; registrace je největší krok navíc proti e-shopu.

## Co má vzniknout

1. **Zakázka hosta.** Host (anonymní relace `mp_sid`, `App\Models\AnonymousSession`) založí zakázku z kalkulace bez
   přihlášení: `farm_orders.user_id` nullable + `anonymous_session_id` (FK nullOnDelete), vlastník = uživatel, nebo relace
   s tokenem zakázky v adresě. Stránka zakázky, volby, přepočet, doručení a adresa fungují pro hosta stejně; kredit se
   hostovi nenabízí (jen karta). Co je jen pro přihlášené, zůstane (kredit, historie zakázek, YouTube přepínač až po
   vzniku účtu).
2. **Platba kartou bez účtu.** U platby pole e-mail (povinné; Stripe Checkout dostane `customer_email`), jméno a adresa
   jako dnes, souhlas s podmínkami. Po zaplacení (webhook): e-mail nepatří nikomu → vznikne účet bez hesla (náhodné
   heslo, `email_verified_at` null, ale zakázka i e-maily jdou), zakázka a soubory anonymní relace se k němu připojí
   (jako při registraci `claim`), potvrzení zakázky obsahuje odkaz na zakázku (token) a odkaz „nastavit heslo“ (reset
   token); e-mail patří existujícímu účtu → zakázka se k němu připojí a e-mail s odkazem to řekne (bez automatického
   přihlášení), stránka zakázky je dál dostupná tokenem z anonymní relace. Texty tlačítka: „Zaplatit kartou :amount a
   spustit tisk“, žádné „dobít kredit“. `verified.email` jen u platby z kreditu.
3. **Vrácení peněz na kartu** (pro každou zakázku placenou kartou, host i přihlášený):
   - `PaymentGateway::refund(Payment $payment, float $amount, string $reason): string` (Stripe Refunds API, `FakeGateway`),
     tabulka `payment_refunds` (payment_id, amount, currency, reason, gateway_ref, status, created_at) a u zakázky
     událost + stav v historii.
   - Pravidlo: tisk se nepovede naší vinou → **jeden automatický opakovaný tisk** (nová úloha, e-mail „tiskneme znovu“,
     peníze se nehýbou); nepovede se podruhé, nebo zákazník zruší před začátkem tisku → **vrácení na kartu celé částky
     včetně dopravy**, e-mail „vrátili jsme vám :amount na kartu, banka to připíše do několika dnů“; zrušení běžícího tisku →
     dnešní pravidlo (poplatek za přípravu a vytištěná část), zbytek na kartu; kdo platil z kreditu, dostane kredit jako
     dnes. Když se refund nepovede (brána), zakázka dostane stav k ruční akci a admin e-mail (`alertAdmin`).
   - Admin: na stránce zakázky „Vrátit na kartu“ s částkou (výchozí = zbývající), s důvodem; vidět, kolik už vráceno.
   - Konfigurace `farm.payments.auto_reprint` (true) a `auto_refund` (true), ať to jde vypnout.
4. **Měření** (`App\Support\Track`): `order_created`/`order_paid` i bez uživatele (relace), nový `register` s `kind`
   `checkout` u účtu vzniklého při platbě, `refund` událost s `value`; napiš session 3 (`conceprt19092026-01`), ať
   trychtýř bere registraci jako volitelný krok (jen zpráva, Funnel je její).
5. **Omezení zneužití**: throttle zakládání zakázek na relaci a IP, soubor hosta se tiskne jen po zaplacení (jako dnes),
   Stripe Radar nic nového. Zakázky hosta nezaplacené 7 dní uklidí `matplace:prune` (viz dnešní úklid čekajících).

## Ověření a výstup

- Testy: `FarmGuestCheckoutTest` (host: zakázka → volby → platba kartou falešnou bránou → účet vznikl / připojen k
  existujícímu → e-maily → odkaz na zakázku; selhání tisku → opakování → druhé selhání → refund; zrušení před tiskem →
  refund; zrušení běžícího → částečný refund; platba z kreditu beze změny), `FarmSalesPathTest`, `FarmCardPaymentTest`,
  `FarmOrderFlowTest`, `FarmAdminNotifyTest`, `AuditRestTest` (klíče ve 3 jazycích), `FarmStatusColumnsTest`.
- Stránka zakázky hosta na 390 a 1280 px (headless Chrome lokálně), cs/en/es.
- Zpráva řídící po každé dávce (1+2, pak 3, pak 4+5): změněné soubory, co se změnilo, jak ověřeno, migrace, co čeká
  na Romana (nic v .env by nemělo být potřeba; Stripe webhook events pro refund jsou volitelné).
