# Prodejní cesta, část farma (platba, doprava na stránce zakázky, čísla farmy, měření) – session 2

Zadal Roman 25. 10. 2026 večer (celé zadání je v `docs/prompts/prodejni-cesta.md`, body 2, 3, 6, 7 a 13). Tohle je
tvoje část. Session 3 dělá katalog, kalkulaci, nástroje, SEO a trychtýř (`docs/prompts/prodejni-cesta-web.md`), řídící
dělá hlavní stránku a bezpečnostní hlavičky. Piš česky, krátce, bez pomlček.

**Hlavní cíl:** víc dokončených objednávek tisku. Nic nového bez vazby na tento cíl. Nic nemaž a nevypínej, dokud
neověříš, že to nepoužívá jiná část webu.

## Pracoviště

- Worktree `C:\matplace-gcode-wt`, nová větev `feature/sales-path-farm` z `origin/main` (3219dee nebo novější, nejdřív
  `git fetch origin`). Nemerguješ ani nenasazuješ, to dělá řídící (conceprt19092026-91). Po dokončení push a zpráva
  řídící přes `SendMessage`; `PublicStats` (úkol 3) pushni samostatně co nejdřív, session 3 na ni čeká.
- Deník `docs/AL.md` §12.
- Texty jen v `lang/{cs,en,es}/farm.php` (nebo `lang/src/<soubor>.json` + `python scripts/lang_add.py`), každý JSON
  ověř `json.load`. Před pushem `vendor/bin/pint --dirty`, `npx tsc --noEmit -p .`, `npm run build`, testy farm/order
  na pozadí (7 až 10 min, nikdy dvě sady naráz, během běhu neupravuj soubory). Nový stav nebo enum porovnej s MariaDB
  (`FarmStatusColumnsTest`).
- Nedotýkej se: `lang/*/pages.php`, kalkulace (`resources/js/calc/calculator.ts`, `calculator/*`), `/tools`, hlavní
  stránka (`calculator/home.blade.php`), nginx a server.

## Co řídící ověřila 25. 10.

- Platba dnes: `OrderController::pay` z kreditu; `OrderController::checkout` kartou = `Payment` na přesně chybějící
  částku `ceil(missing)` (celé koruny nebo eura), Stripe Checkout, po návratu se připíše jako kredit a hned zablokuje pro
  zakázku (`Track::paidByCard`), drobný zbytek do celé koruny zůstane jako kredit. Je to tedy fakticky přímá platba
  kartou, ale texty říkají jinak: `farm.lead` „zaplatíte kreditem“, tlačítko `pay_short` „Chybí :missing kreditu – dobít
  kartou“ zní jako povinné předplacení, `credit.lead` „Kreditem platíte tisky…“, `terms` „platí se z kreditu“. `cta_hint`
  už říká „kreditem nebo kartou“. Na webu (FAQ, Jak to funguje) sjednocuje session 3 podle znění níže.
- Doprava: velké číslo na zakázce je cena tisku bez dopravy, pod ním řádek podle zvoleného doručení, bez volby „Doprava
  podle způsobu doručení níže“, celek až v bloku platby (AH.md §9.3). Zákazník s malým výtiskem (99 Kč) tak vidí
  celkových 198 Kč až u tlačítka.
- Čísla farmy na produkci: 223 zakázek, 104 zaplacených (57 adminů, 47 od 5 zákazníků), stavy done 45, handed_over 58,
  failed 44, cancelled 38; 31 zveřejněných videí (`FarmVideo` published). Farma je veřejná (`farm.public` true).
- Měření (`App\Support\Track`): `order_started` při POST /farm/orders, `checkout_started` (kind card), `order_created`,
  `order_paid` (kredit i karta), `failed` step checkout. Dobití kreditu (`/account/credit`, `Wallet::topUp`) nemá
  žádnou událost.

## Úkoly (v tomhle pořadí)

1. **Platba bez povinného kreditu v textech i tlačítku.** Tlačítko podle stavu: kredit stačí → „Zaplatit z kreditu
   a spustit tisk“; nula kreditu → „Zaplatit kartou :amount a spustit tisk“; část → „Doplatit kartou :amount
   (:balance z kreditu) a spustit tisk“ (je). Pod tlačítkem jedna věta: „Kartou platíte přesně :amount; pár haléřů navíc
   zůstane jako kredit.“ jen když zaokrouhlení něco nechá. Oprav `farm.lead`, `credit.lead`, `terms`, hlášky
   `card_kept`, e-maily, aby nikde nebylo „musíte dobít“. Ověř minimální částku brány (Stripe, menší zakázky než
   15 Kč) a chování v eurech. Mechanismus (Payment, hold, Wallet) neměň, jen když najdeš chybu; `expected_total` a
   kontrola „Cena se mezitím změnila“ zůstávají. en a es věrně.
2. **Celková cena dřív na stránce zakázky.** Při otevření zakázky předvyber doručení (poslední použité zákazníka, jinak
   nejlevnější pro jeho zemi z `config/farm.php`: Zásilkovna, nebo odběr, je‑li zapnutý), aby řádek dopravy i „Celkem
   k zaplacení“ byly vidět hned, s označením odhad, dokud nepřijde nabídka dopravce. Výpočet podle země a hmotnosti
   (`Shipping::price`, `offer`) neměň. Změna doručení přepočítá jako dnes. Ověř `order_timeline.ts`/`order_price.ts`
   kontroly při buildu.
3. **`App\Domain\Farm\PublicStats`** (pro blok důvěry session 3, jen čtení, keš 1 h):
   ```php
   PublicStats::summary(): array{printed: int, handed_over_days_median: ?float, last_30_days_printed: int, videos_published: int}
   ```
   `printed` = zakázky s `paid_at` ve stavu done nebo handed_over; medián dní od `paid_at` k události předání
   (`farm_order_events`, přechod do handed_over) za posledních 50 předaných; `videos_published` = `FarmVideo` published.
   Testy s factory. Pushni hned, až to je, a napiš session 3 (conceprt19092026-01) i řídící.
4. **Měření dobití a platby.** `topup_started` (POST /account/credit, s částkou a měnou v `value`/`currency`),
   `topup` při připsání (`Wallet::topUp`, s `event_id` platby proti dvojímu započtení), `failed` step pay u odmítnuté
   platby kreditem (`InsufficientCredit` jde do karty, to není chyba). Ověř, že `order_paid` nese `value` a `currency`
   u obou cest a že `Track::SHARED` pouští do GA jen to, co má (bez osobních údajů, bez názvů souborů). Nové typy přidej
   do `Track::ACTIONS`, aby je trychtýř session 3 viděl.
5. **Texty kroků zakázky**: u každého kroku (volby, barva, doručení, platba) jedna věta, co udělá dál a zda už platí;
   pryč opakování. Dodrž slovník AH.md §9.4 (model vs výtisk, objednat tisk, zaplatit).

## Jednotné znění o platbě (cs; en a es věrně; session 3 má totéž)

- Krátce: „Platíte až na konci objednávky, kartou nebo z kreditu.“
- Dlouze: „Cenu znáte před zaplacením. Zaplatíte kartou (Stripe) nebo z kreditu na účtu. Kartou platíte přesně cenu
  zakázky zaokrouhlenou nahoru na celé koruny nebo eura; pár haléřů navíc zůstane jako kredit na další tisk. Kredit si
  můžete dobít i předem (100 až 20 000 Kč, 5 až 800 €). Částka se při zaplacení zablokuje a strhne až po dotisknutí;
  když tisk nezačne nebo se nepovede naší vinou, vrátí se celá.“

## Ověření (zapiš do AL.md §12)

- `FarmOrderFlowTest`, `FarmBigModelFlowTest`, testy platby s `FakeGateway` (nula kreditu, část, dost, eura, změna
  ceny mezitím), `AuditRestTest` chybějící klíče ve 3 jazycích, `scripts/check_order_timeline.mjs` při buildu.
- Stránka zakázky v cs/en/es, 390 px a 1280 px: celek s dopravou vidět před rolováním k tlačítku.
- Žádná nová migrace bez porovnání šířek sloupců s MariaDB.

## Výstup pro řídící

Seznam změněných souborů, co se skutečně změnilo, jak otestováno, výsledky testů a buildu, co zůstalo nevyřešené;
odděl implementované změny od doporučení. Nic nehlásit jako hotové bez ověření.
